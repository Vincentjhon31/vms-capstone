<?php
/**
 * The Citizen's Charter Handbook 2026 (1st Edition), loaded into the database.
 *
 * sql/citizens-charter-2026.json is the handbook as published at
 * bongabong-charter-2026-3819ac1c.base44.app: 24 offices, each listing its
 * External and Internal services (the Agriculture Office lists its services
 * under three program groups instead), and a write-up for most of them -
 * requirements, steps, fees, processing time and who is in charge. The Office
 * of the Municipal Administrator (25th, after the Mayor's Office) was added
 * later from its own Charter document, so it has no handbook page (page 0).
 * The same file drives the eBongabong portal, so the two sites publish one Charter.
 *
 * install.php calls charter_import(); nothing else writes these tables except
 * the Manage Services screen.
 */

require_once __DIR__ . '/helpers.php';

function charter_json(): array
{
    $data = json_decode((string) file_get_contents(dirname(__DIR__) . '/sql/citizens-charter-2026.json'), true);
    if (!is_array($data) || empty($data['offices'])) {
        throw new RuntimeException('sql/citizens-charter-2026.json is missing or unreadable.');
    }
    return $data;
}

/**
 * One office's services in Charter order, each paired with its write-up when
 * there is one.
 *
 * The list names and the write-up titles do not always agree: "Document" and
 * "Documents", a trailing "(Internal Service)", and one Mayor's Office service
 * renamed outright. So a write-up is matched by name first, and one left over
 * goes to the unmatched entry at its own number in a list of the same kind.
 * A write-up with no list entry at all is added at the end, so nothing in the
 * handbook is unreachable.
 *
 * @return array<int, array{type: string, group: ?string, name: string, detail: ?array}>
 */
function charter_plan(array $office): array
{
    $key = fn (string $s) => rtrim(preg_replace('/[^a-z0-9]/', '', strtolower(str_ireplace('(Internal Service)', '', $s))), 's');

    $sections = [];
    foreach ($office['groups'] ?? [] as $group) {
        $sections[] = ['type' => 'external', 'group' => $group['name'], 'names' => $group['services']];
    }
    if (!empty($office['externalServices'])) {
        $sections[] = ['type' => 'external', 'group' => null, 'names' => $office['externalServices']];
    }
    if (!empty($office['internalServices'])) {
        $sections[] = ['type' => 'internal', 'group' => null, 'names' => $office['internalServices']];
    }
    foreach ($sections as &$section) {
        $section['items'] = array_map(fn ($name) => ['name' => $name, 'detail' => null], $section['names']);
    }
    unset($section);

    $details = $office['serviceDetails'] ?? [];
    $byKey = [];
    foreach ($details as $i => $detail) {
        $byKey[$key($detail['name'])] ??= $i;
    }
    $used = [];

    foreach ($sections as &$section) {
        foreach ($section['items'] as &$item) {
            $i = $byKey[$key($item['name'])] ?? null;
            if ($i !== null && !isset($used[$i])) {
                $item['detail'] = $details[$i];
                $used[$i] = true;
            }
        }
        unset($item);
    }
    unset($section);

    foreach ($details as $i => $detail) {
        if (isset($used[$i])) {
            continue;
        }
        $type = stripos($detail['name'], '(Internal Service)') !== false ? 'internal' : 'external';
        $pos = (int) ($detail['numbering'] ?? 0) - 1;
        foreach ($sections as &$section) {
            if ($section['type'] === $type && isset($section['items'][$pos]) && $section['items'][$pos]['detail'] === null) {
                $section['items'][$pos]['detail'] = $detail;
                $used[$i] = true;
                break;
            }
        }
        unset($section);
        if (!isset($used[$i])) {
            $at = null;
            foreach ($sections as $n => $section) {
                if ($section['type'] === $type && $section['group'] === null) {
                    $at = $n;
                }
            }
            if ($at === null) {
                $sections[] = ['type' => $type, 'group' => null, 'items' => []];
                $at = array_key_last($sections);
            }
            $sections[$at]['items'][] = ['name' => $detail['name'], 'detail' => $detail];
            $used[$i] = true;
        }
    }

    $rows = [];
    foreach ($sections as $section) {
        foreach ($section['items'] as $position => $item) {
            $rows[] = [
                'type'     => $section['type'],
                'group'    => $section['group'],
                'position' => $position + 1,
                'name'     => trim(str_ireplace('(Internal Service)', '', $item['detail']['name'] ?? $item['name'])),
                'detail'   => $item['detail'],
            ];
        }
    }
    return $rows;
}

/** The List of Offices entry (address, contact) for a Charter office, matched by name. */
function charter_directory_entry(array $office, array $directory): ?array
{
    $norm = fn ($s) => preg_replace('/[^a-z]/', '', strtolower(str_replace(["'s", 'Office of the', 'Office'], '', $s)));
    foreach ($directory as $entry) {
        if ($norm($entry['name']) === $norm($office['name'])) {
            return $entry;
        }
    }
    $short = $office['shortName'] ?? '';
    foreach ($directory as $entry) {
        if ($short !== '' && str_contains($norm($entry['name']), $norm($short))) {
            return $entry;
        }
    }
    return null;
}

/**
 * Write the handbook into charter_offices, services, service_requirements and
 * service_steps. Offices are refreshed in place; services are only added here -
 * the caller decides what happens to the ones already there. With $onlyOffices,
 * services are added for those office codes alone (an office new to the file),
 * and every office is still refreshed so the Charter order stays right.
 *
 * @param string[]|null $onlyOffices
 * @return array{offices: int, services: int, detailed: int, requirements: int, steps: int}
 */
function charter_import(?array $onlyOffices = null): array
{
    $data = charter_json();
    $count = ['offices' => 0, 'services' => 0, 'detailed' => 0, 'requirements' => 0, 'steps' => 0];
    $lines = fn ($value) => implode("\n", array_map('trim', (array) $value));

    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ($data['offices'] as $officeOrder => $office) {
            $entry = charter_directory_entry($office, $data['officeDirectory'] ?? []);
            db_run(
                'INSERT INTO charter_offices (code, name, short_name, page, address, contact, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), short_name = VALUES(short_name), page = VALUES(page),
                                         address = VALUES(address), contact = VALUES(contact), sort_order = VALUES(sort_order)',
                [$office['id'], $office['name'], $office['shortName'] ?? $office['name'], (int) ($office['page'] ?? 0),
                 $entry['address'] ?? '', $entry['contact'] ?? '', $officeOrder + 1]
            );
            $count['offices']++;
            if ($onlyOffices !== null && !in_array($office['id'], $onlyOffices, true)) {
                continue;
            }

            foreach (charter_plan($office) as $order => $row) {
                $detail = $row['detail'] ?? [];
                $serviceId = db_insert(
                    'INSERT INTO services (service_no, name, description, office, classification, transaction_type,
                                           who_may_avail, total_fee, total_time, sort_order, office_code, service_type, group_name)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int) ($detail['numbering'] ?? $row['position']), $row['name'], $detail['description'] ?? '',
                     $detail['office'] ?? $office['name'], $detail['classification'] ?? '', $detail['transactionType'] ?? '',
                     $detail['whoMayAvail'] ?? '', $detail['totalFee'] ?? '', $detail['totalProcessingTime'] ?? '',
                     $order + 1, $office['id'], $row['type'], $row['group']]
                );
                $count['services']++;
                if ($detail) {
                    $count['detailed']++;
                }
                foreach ($detail['requirements'] ?? [] as $n => $requirement) {
                    db_run(
                        'INSERT INTO service_requirements (service_id, requirement, where_to_secure, sort_order) VALUES (?, ?, ?, ?)',
                        [$serviceId, trim((string) ($requirement['name'] ?? '')), trim((string) ($requirement['source'] ?? '')), $n + 1]
                    );
                    $count['requirements']++;
                }
                foreach ($detail['steps'] ?? [] as $n => $step) {
                    db_run(
                        'INSERT INTO service_steps (service_id, client_step, agency_action, fees, processing_time,
                                                    person_responsible, sort_order)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$serviceId, trim((string) ($step['clientStep'] ?? '')), $lines($step['agencyAction'] ?? []),
                         trim((string) ($step['fee'] ?? '')), trim((string) ($step['processingTime'] ?? '')),
                         $lines($step['personsResponsible'] ?? []), $n + 1]
                    );
                    $count['steps']++;
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return $count;
}

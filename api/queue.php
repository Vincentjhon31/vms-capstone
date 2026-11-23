<?php
/**
 * Live queue state for the public strip.
 *
 * Queue numbers only. Every public page polls this on a timer, so it must never
 * be the thing that leaks who is in the building: no names, no purposes, no
 * reference numbers of other people's visits.
 *
 * The one exception is ?ref=, which reports the position of a single visit to
 * whoever can quote its reference number. That is the same bargain the tracking
 * page already strikes - the number is the credential - and it is what lets the
 * strip say "two ahead of you" instead of a queue number the visitor then has
 * to compare against their own by hand.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

/* config.php opens a session for the pages that need one. This endpoint does
   not, and every public page polls it on a timer - so hand the session lock
   straight back, or a poll in flight would make the visitor's own form
   submission queue behind it. */
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$state = queue_state();

$reference = extract_ref_no((string) ($_GET['ref'] ?? ''));
if ($reference !== '' && str_starts_with($reference, 'VIS')) {
    $state['you'] = queue_position($reference);
}

echo json_encode($state, JSON_UNESCAPED_SLASHES);

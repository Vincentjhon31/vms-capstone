# Bongabong Visitor Management System

A clone of the Municipality of Bongabong VMS, built as plain PHP 8 + MySQL so it runs on XAMPP
locally and uploads straight to Hostinger with no build step, no Composer, and no CDN.

Two jobs, as in the live system:

1. **Visitor management** - register every citizen entering the Municipal Hall, capture purpose
   and signature, work the queue from the front desk, and report on volume and demographics.
2. **Client Satisfaction Measurement & Feedback** - the CSM survey, feedback, concerns, the
   Citizen's Charter and services directory, and submission tracking.

---

## Run it locally

1. Start **Apache** and **MySQL** in the XAMPP control panel.
2. Copy this folder into `C:\xampp\htdocs\vms-bongabong` - or just run `deploy-local.cmd`,
   which does it for you.
3. Open <http://localhost/vms-bongabong/install.php>. It creates the database, applies the
   schema, loads the Citizen's Charter 2026 (25 offices, External and Internal services), and
   re-checks every totals row against its steps. Run it again after an update that changes the
   database.
4. Open <http://localhost/vms-bongabong/> for the public site.

**Administrator:** `admin` / `Password@1234` - change it at once under Settings.

Optional: sign in and open `demo.php` to fill the system with three weeks of sample traffic so
the dashboard and reports have something to show. One click removes it again.

---

## Pages

| Route | Who | What |
|---|---|---|
| `/` | public | CSM hub - survey, feedback and concern cards, floating QR panel |
| `/Register` | public | Visitor registration with consent, purpose cards and signature pad; finishes on a visitor pass carrying a QR code |
| `/CustomerSatisfactionForm` | public | The live Google Form, embedded and fillable in place |
| `/FeedbackForm`, `/ConcernForm` | public | Submissions with a printable reference number |
| `/MayorsOfficeCitizensCharter` | public | Full Charter: foreword, nine services, office information |
| `/ServicesDirectory` | public | The same services, searchable and filterable |
| `/FAQ` | public | Accordion by category, with search |
| `/TrackSubmission` | public | Status timeline for one reference number; for a visit, the queue position - this is where a scanned pass lands |
| `/Privacy` | public | Data Privacy Act notice |
| `/Admin` | public | Sign in |
| `/AdminDashboard` | admin | KPI tiles and charts |
| `/Checkpoint` | admin | Check-in desk: scan a pass or type its number, then call the visitor or record their time out |
| `/Log` | admin | Visitor log, filters, Attended / Time Out, signature, exports |
| `/ConcernList`, `/FeedbackList` | admin | Assign, respond, track history |
| `/CustomerSatisfactionList` | admin | CSM results, CSV import from the Google Sheet |
| `/Summary` | admin | Date-range report, printable and exportable |
| `/AdminServices`, `/AdminFAQ`, `/AdminCharter`, `/AdminSettings` | admin | Editable content, password, retention, audit trail |

Every admin route is guarded in PHP before the view loads, so signing out and typing the URL
gives you the login page, never the admin shell.

---

## How it is put together

```
index.php          front controller - the route table lives here
.htaccess          pretty URLs (/Register); falls back to ?r= if mod_rewrite is off
includes/          config, db, auth, helpers, icons, charts, header/footer, seed data
views/public/      the citizen-facing pages
views/admin/       the staff-facing pages
api/export.php     CSV and Excel downloads (admin only)
api/queue.php      live queue figures, polled by the strip on every public page
css/style.css      the whole design system, including the print stylesheet
js/qr.js           QR encoder written for this project - no CDN, works offline
js/pass.js         saving the visitor pass as a PNG
js/queue.js        keeps the public queue strip current
js/scanner.js      camera scanning on the check-in desk
js/app.js          signature pad, filters, repeatable rows, nav
sql/schema.sql     tables and indexes
sql/citizens-charter-2026.json  the Citizen's Charter Handbook 2026 (same file as the eBongabong portal)
includes/charter_data.php       loads that file into the database (install.php)
includes/service_view.php       Services Directory lists and the service sheet window
assets/org-chart/  the Mayor's Office Organizational Chart (/OrganizationalChart): the original
                   PNG for Download, and 1600/2800px WebP copies for the page
install.php        installer + Charter totals audit
demo.php           sample data for training
```

Notable choices:

- **The CSM survey is embedded, not rebuilt.** The live form is the ARTA-harmonised instrument:
  29 pages with a separate question path per service. Retyping it risks changing prescribed
  wording and numbering, which would make results non-reportable.
- **The QR code is generated in the browser** by `js/qr.js` (byte mode, error correction M,
  versions 1-10). Its output was verified module-for-module against the reference Python
  `qrcode` library. It encodes the site's own address, so the printed poster is correct on
  localhost and on the live domain alike.
- **A visitor's reference number is short and random**: `VIS-0903-4817` - the prefix, the
  month and day, then four random digits. A pass lives for one afternoon, so a four-digit
  year in the middle of it tells the holder nothing; and a gapless counter printed on a
  receipt would tell them exactly how many people registered before them, and let them guess
  a neighbour's number and open that visit on the tracking page. Concerns and feedback keep
  the year-based `CON-2026-00123`, because those are quoted back weeks later. Both shapes are
  accepted for ever - `extract_ref_no()` in `includes/helpers.php` is the single door.
- **The pass encodes the tracking URL, not the bare number.** One code does two jobs: a
  visitor pointing a phone camera at it lands on their own status page, and the desk's
  scanner has the number read back out of the URL it receives.
- **The check-in desk prefers `BarcodeDetector`** and falls back to the vendored `jsQR`,
  fetched only when someone presses Start camera. Chrome does not implement `BarcodeDetector`
  on Windows, which is the machine the front desk runs, so the native path alone would have
  been a scan button that never worked where it was needed. Neither is required: typing the
  number is the supported path, and a USB barcode scanner types into that same field.
- **Charts are hand-rolled SVG** rendered server-side - nothing to break offline, and they
  survive being printed to PDF.
- **PDF export is the browser's print dialog** driven by the print stylesheet, so no PDF
  library is needed on shared hosting. CSV and Excel come from `api/export.php`.

---

## Security and compliance

- PDO prepared statements everywhere; every value printed through `e()`.
- CSRF token on every POST; honeypot field and per-IP hourly rate limit on public forms.
- `password_hash()` for the administrator; sessions expire after two hours.
- Audit log of every administrative action, visible under Settings.
- **RA 10173 (Data Privacy Act):** consent is required and enforced server-side, `/Privacy`
  explains what is collected and why, and Settings can dispose of visitor records past the
  retention period set in `includes/config.php`.
- **RA 11032:** the Charter, services, fees, processing times and FAQ are all admin-editable,
  and each service's totals row is re-checked against the sum of its steps on every save.

Before going live: change the admin password, and delete `install.php` and `demo.php`.

---

## The known gap

CSM responses land in the Google Sheet linked to the form, not in this database. So
`/CustomerSatisfactionList` does two things: it links staff to the form and sheet, and it
accepts the sheet's CSV export, which then feeds the per-dimension averages, the overall score
and the dashboard's satisfaction tile. Set `GOOGLE_SHEET_URL` in `includes/config.php` to have
the "Open the sheet" button appear.

---

## Design system

Tokens live in `css/tokens.css`, named the way shadcn/ui names them (`--background`,
`--foreground`, `--card`, `--muted`, `--border`, `--ring`, `--radius`). No pure black or
white anywhere. Inter and Manrope are self-hosted in `assets/fonts/` so the PWA renders
correctly with no outbound network.

Motion (motion.dev, the vanilla build of Framer Motion) is vendored at `js/vendor/motion.min.js`
and loaded only where a page sets `$useMotion`. Everything a CSS transition can do is left to
CSS: transitions keep running while the main thread is busy, which matters on a municipal PC.

The seal is `assets/seal.png` (with `seal-256.png` and `seal-512.png` for larger uses) and the
gold M is `assets/logo-m.png`. Replace those files and every page picks the new artwork up.

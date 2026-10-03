# JCEC Academy production readiness

Audit date: 3 October 2026. This checklist is a **deployment gate**, not evidence that the target production environment has already passed it. The repository and `.env.example` were inspected; no production secrets, infrastructure inventory, mail provider, video host, or live browser QA results were supplied. Keep all secret values out of tickets, screenshots, and this document. See `docs/requirements-gap-audit.md` for the requirement matrix and P0–P3 plan.

**Current verdict: NO-GO for public launch.** Close the five P0 work packages and obtain an explicit product decision on certificate eligibility/approval. Then close or formally scope the P1 original requirements, perform the manual QA matrix, and re-run the gate below against a production-like staging environment. A limited internal pilot can be considered only with documented exclusions and non-public access; it is not equivalent to public launch approval.

## Release decision record

| Gate | Current evidence | Required release evidence / owner |
| --- | --- | --- |
| Original feature scope | 81 traceability rows; 22 `NOT_IMPLEMENTED`, 40 `PARTIAL`, 1 `BLOCKED` | Product owner accepts scope and resolves certificate conflict; engineering closes launch items |
| Security | Focused code audit: 0 Critical, 1 High, 3 Medium, 1 Low, 1 Informational; not a penetration test | Security owner verifies media host, permissions, file/download controls and production settings |
| Automated tests | Laravel 421/1,924 and Vue 319 pass; Vite/Pint/diff pass | Same checks on release commit in CI/staging |
| Browser QA | Authenticated end-to-end evidence not available | Signed Arabic/English × desktop/mobile matrix, including uploads/QR/financial retries |
| Production environment | Only local example/config inspected | Ops owner records approved values and checks without exposing secrets |
| Recovery/operations | No verified backup/restore, mail delivery, alerting or incident runbook | Restore rehearsal, mail/queue/failed-job test, monitoring and on-call owner |

## Safe deployment checklist

### 1. Before provisioning

- [ ] Freeze an exact Git commit and dependency lockfiles; confirm `composer show --direct` and `package.json` versions against that commit. Do not change dependencies just to deploy.
- [ ] Obtain the approved domain, TLS certificate, public site/SPA origin, API origin, email sender, base ISO currency, storage retention policy, privacy/terms/refund copy, certificate policy, and production media host. These are decisions, not Laravel defaults.
- [ ] Confirm the public scope excludes genuinely deferred items (live/hybrid, future gateway, WhatsApp/SMS, later multi-currency, optional watermark/library) and does not advertise them. Confirm which P1 original items must precede launch.
- [ ] Back up current database and private files before any migration or seed. Rehearse restore to a separate environment and record recovery time/data-loss expectations.
- [ ] Use a distinct production database, storage root/bucket, session store, cache and queue. No local/demo accounts, orders, certificates or media should be copied as production fixtures.

### 2. Environment, HTTPS and browser session

- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, a persistent private `APP_KEY`, correct `APP_NAME`, HTTPS `APP_URL`, and approved locale/fallback. Never publish `.env`; avoid `config:show` output containing secrets in deployment logs.
- [ ] Serve SPA and `/api/v1` over HTTPS. If same-origin, retain Axios `baseURL: '/'`, `withCredentials`, `withXSRFToken`; if origins differ, explicitly test credentials, CORS, CSRF and stateful domains. The current app is built for first-party cookie sessions, not bearer-token browser storage.
- [ ] Set `SANCTUM_STATEFUL_DOMAINS` to the exact production hostnames/ports (no schemes). Set `SESSION_DOMAIN` intentionally, `SESSION_SECURE_COOKIE=true`, suitable `SESSION_SAME_SITE`, `SESSION_LIFETIME`, and trusted proxy/HTTPS forwarding. Verify cookie flags and login/logout/session expiry in a real browser. `bootstrap/app.php` enables stateful API and JSON API exceptions.
- [ ] Do not ship with Vite hot-file/dev-server URLs. Run the production build and serve `public/build/manifest.json`; verify the page loads through HTTPS without mixed content or CORS errors. `.env.example` is explicitly local and is not a production template.
- [ ] Validate API error handling for 401/403/404/419/422/429/500: no stack traces at `APP_DEBUG=false`, no sensitive DB/file paths, clear translated user messages, and safe one-time CSRF retry.

### 3. Data, money and migrations

- [ ] Use a supported production MySQL/MariaDB version and charset/collation, with least-privilege DB credentials and tested connection limits. Run `php artisan migrate:status --no-interaction` before and after migrations; take a restorable backup first. The audit made no migrations.
- [ ] Set `JCEC_COMMERCE_CURRENCY` to the approved three-letter **actual** ISO 4217 currency. `CommerceCatalogService` enforces only a three-letter shape and blocks checkout when unset; confirm the selected code independently. Do not rely on a silent fallback.
- [ ] Confirm `DECIMAL(12,2)` money fields and snapshot/unique constraints survived migration on the target DB. Perform a staging checkout with identical idempotency key, package edit after checkout, manual payment approval retry, paid-vs-completed reconciliation, expired/suspended access, and retry after interrupted provisioning.
- [ ] Decide coupon, timed-discount, invoice and refund rules before advertising them. Current checkout records `discount_total='0.00'`; the `Refunded` enum is not a completed refund workflow. Do not use a manual order status edit as a substitute for financial reconciliation.
- [ ] Do not publish an `is_sequential=true` learning path while its public detail says “sequential” but package purchase grants all component courses at once and no next-course lock is enforced. Either implement the rule and test it or remove that promise from the offer.
- [ ] If reporting/export is in launch scope, validate data definition, access control, timezone, currency and totals against orders/payments. Avoid deriving accounting revenue merely from `Completed` count.

### 4. Files, video, certificates

- [ ] Keep assignment, support, payment proof, certificate PDF and `lesson_resources` disks **private**. `public/storage` is only for intentionally public assets and is not required for these private flows. Confirm web server cannot address `storage/app/private` or the lesson-resources root directly.
- [ ] Configure `JCEC_PAYMENT_PROOF_DISK`, `JCEC_ASSIGNMENT_FILE_DISK`, `JCEC_SUPPORT_ATTACHMENT_DISK`, `JCEC_CERTIFICATE_PDF_DISK` and PHP/web-server `upload_max_filesize`, `post_max_size`, request body and timeout limits consistently with app limits (proof 5 MB, support 10 MB each/5 files, assignment 20 MB each/5 files by defaults). Ensure storage paths are writable, backed up, retained and restorable.
- [ ] Provide a real admin/content-manager lesson-resource upload and protected admin download path before selling file-based lessons. The current admin API stores only a path/link reference and the Vue editor only creates links; manual provisioning on a server is not an acceptable routine authoring process.
- [ ] Test a real browser upload/download for assignment and support attachments. The temporary-file fallback in `UploadedFileStorage` has automated tests, not authenticated browser signoff. Test wrong owner, internal support note, expired enrollment, wrong MIME/extension, oversize and interrupted upload.
- [ ] Confirm the actual video host enforces paid access using signed/expiring playback URLs or equivalent controls. The current Vue player receives a direct `video_url`; do not assume it is protected merely because the lesson JSON requires enrollment. Verify quality/speed/fullscreen needs against the original requirements.
- [ ] Validate mPDF on production: writable `storage/app/mpdf`, required PHP extensions/fonts, logo at `public/assets/images/logo-1.png`, private PDF disk, Arabic glyphs, QR resolution and scan target. The QR target must be the public HTTPS verification page. Check issued, revoked, reissued, archived-course and unknown tokens; decide whether revoked PDF download should remain available. Use the approved canonical certificate eligibility policy, not an assumption about quiz/assignment completion.
- [ ] Review active-content risk in Office/archive uploads; confirm AV/quarantine or a justified type restriction. Normalize download names and headers (`attachment`, `nosniff`, private caching) consistently. Do not expose storage paths/disks in API resources or logs.

### 5. Seeders and role grants

- [ ] Production initialization requires schema migrations and **deliberate** role/permission provisioning. `DatabaseSeeder` calls `RolesAndPermissionsSeeder` and calls `DevelopmentSeeder` only when `app()->isLocal()`. `DevelopmentSeeder` itself also returns outside local, but a mistaken production `APP_ENV=local` would create/update demo admin, instructors, sample student, courses, package/cart/enrollment. Keep `APP_ENV=production` and never run it in production.
- [ ] `RolesAndPermissionsSeeder` uses `Permission::findOrCreate` and then `Role::findOrCreate(...)->syncPermissions(...)` for **student, instructor, content_manager, sales_support and admin**. Rerunning replaces **each built-in role's complete direct permission set** with the coded list. It does not delete custom role records or direct per-user grants, but local custom permissions assigned to any of those five roles are removed; new coded grants may also broaden access.
- [ ] Before seeding, export/back up `roles`, `permissions`, `role_has_permissions`, `model_has_roles` and `model_has_permissions`, compare coded grants with the actual production role matrix, get security/product approval, then run only the intended seeder in a maintenance window if the diff is accepted. Verify effective permissions and access tests after. If production-customized grants must persist, do **not** run `db:seed`/`RolesAndPermissionsSeeder` unchanged; change the deployment design in a later remediation phase. No seeder was executed in this audit.
- [ ] Provision the first production admin by a controlled, documented procedure with a unique secret and mandatory rotation; never use `.env.example` development credentials or `DevelopmentSeeder` sample identities.

### 6. Cache, queue, mail, scheduler and observability

- [ ] Choose durable, monitored `DB_CONNECTION`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` for the target topology. The example uses database cache/queue/session; ensure tables and worker processes exist. If email/notifications are added, run supervised queue workers with retry/failed-job alerting; a queued mail with no worker is not delivered.
- [ ] Configure a real `MAIL_MAILER`/sender and domain DNS (SPF/DKIM/DMARC as appropriate) and test receipt, reset, certificate and failure/bounce behavior. The example uses `MAIL_MAILER=log`; current application has no transactional mail triggers. Do not claim email readiness from Laravel's generic mail configuration.
- [ ] `routes/console.php` currently has only the default `inspire` command; no application scheduler task was identified. If later jobs require scheduler/expiry reminders/cleanup, document and operate them then. Do not deploy an idle scheduler as proof those features exist.
- [ ] Set production log channel/level/retention and central collection with redaction of proof/file paths, tokens, credentials and personal data. Current critical services generally throw exceptions and roll back DB/files, but no dedicated alerting or structured business-event monitoring was found for checkout failure, payment approval/provisioning failure, file storage, PDF issuance or grading. Minimum launch alerts: repeated 5xx, failed queue jobs, payment stuck in Paid without Completed, orphaned private files, PDF generation failure, and support upload errors. Give each alert an owner and response runbook.
- [ ] Configure `/up` health check and monitor DB, disk capacity, TLS expiry, failed jobs, mail deliverability, backup freshness and error rate. A green HTTP health endpoint alone does not prove commerce or storage health.

### 7. Release verification and signoff

- [ ] On the release commit, run `php artisan test --compact`, `npm run test:frontend -- --maxWorkers=2`, `npm run build`, `vendor/bin/pint --dirty --format agent`, `git diff --check`, `php artisan route:list --except-vendor`, and `php artisan migrate:status --no-interaction`. Record command output, commit SHA and environment. On 3 October 2026 the local results were 421 Laravel tests/1,924 assertions, 319 Vue tests, successful build/Pint/diff, 186 routes and all migrations Ran. The default parallel Vitest run crashed one Windows worker while Laravel tests were running; the isolated two-worker rerun passed.
- [ ] Execute the manual matrix in `docs/requirements-gap-audit.md` with real role accounts on staging, in Arabic and English, desktop and mobile. Include cookies/CSRF, file multipart, blob downloads, video host, QR/PDF, duplicate payment approval and role isolation. Record defects and retest. Unit/feature/mocked Vue tests do not replace this.
- [ ] Review the security findings and obtain an explicit risk acceptance for any remaining Medium/Low item. Do not accept the video-host High finding without proving source protection or changing the content-delivery design.
- [ ] Obtain written signoff from product, engineering, security, finance/operations and content/legal owners. Only then schedule a reversible release with backup, smoke test, rollback criteria and customer-support staffing.

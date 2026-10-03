# JCEC Academy production readiness

Audit date: 3 October 2026. This checklist is a **deployment gate**, not evidence that the target production environment has already passed it. The repository and `.env.example` were inspected; no production secrets, infrastructure inventory, mail provider, video host, or live browser QA results were supplied. Keep all secret values out of tickets, screenshots, and this document. See `docs/requirements-gap-audit.md` for the requirement matrix and P0–P3 plan.

**Current verdict: NO-GO for public launch.** Phase 15A implemented significant P0 code, but all five P0 packages retain release dependencies: approved certificate rules, approved legal copy, production mail, real browser file/purchase/access checks, and production environment signoff. Close or formally scope the P1 original requirements before public launch. A limited internal pilot needs documented exclusions and non-public access; it is not public launch approval.

## Release decision record

| Gate | Current evidence | Required release evidence / owner |
| --- | --- | --- |
| Original feature scope | 81 traceability rows; Phase 15A code state in gap audit; certificate rule still `BLOCKED` | Product owner accepts scope and resolves certificate conflict; engineering closes launch items |
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
- [ ] Configure `JCEC_PAYMENT_PROOF_DISK`, `JCEC_ASSIGNMENT_FILE_DISK`, `JCEC_SUPPORT_ATTACHMENT_DISK`, `JCEC_CERTIFICATE_PDF_DISK` and PHP/web-server `upload_max_filesize`, `post_max_size`, request body and timeout limits consistently with app limits (proof 5 MB, support 10 MB each/5 files, assignment 20 MB each/5 files and Lesson Resource 20 MB by defaults). Ensure storage paths are writable, backed up, retained and restorable.
- [x] Code provides Admin/Content Manager lesson-resource multipart upload and protected management download through private storage. The curriculum editor has a file picker. Upload is capped at 20 MB by default (`JCEC_LESSON_RESOURCE_MAX_KILOBYTES`) and restricted to configured MIME/extension list. No browser signoff yet; course-level resources remain unsupported, so do not advertise them as available.
- [ ] Test a real browser upload/download for assignment and support attachments. The temporary-file fallback in `UploadedFileStorage` has automated tests, not authenticated browser signoff. Test wrong owner, internal support note, expired enrollment, wrong MIME/extension, oversize and interrupted upload.
- [ ] Confirm the actual video host enforces paid access using signed/expiring playback URLs or equivalent controls. The current Vue player receives a direct `video_url`; do not assume it is protected merely because the lesson JSON requires enrollment. Verify quality/speed/fullscreen needs against the original requirements.
- [ ] Validate mPDF on production: writable `storage/app/mpdf`, required PHP extensions/fonts, logo at `public/assets/images/logo-1.png`, private PDF disk, Arabic glyphs, QR resolution and scan target. The QR target must be the public HTTPS verification page. Check issued, revoked, reissued, archived-course and unknown tokens; decide whether revoked PDF download should remain available. Use the approved canonical certificate eligibility policy, not an assumption about quiz/assignment completion.
- [ ] Review active-content risk in Office/archive uploads; confirm AV/quarantine or a justified type restriction. Normalize download names and headers (`attachment`, `nosniff`, private caching) consistently. Do not expose storage paths/disks in API resources or logs.

### 5. Seeders and role grants

- [ ] Production initialization requires schema migrations and **deliberate** role/permission provisioning. `DatabaseSeeder` calls `RolesAndPermissionsSeeder` and calls `DevelopmentSeeder` only when `app()->isLocal()`. `DevelopmentSeeder` itself also returns outside local, but a mistaken production `APP_ENV=local` would create/update demo admin, instructors, sample student, courses, package/cart/enrollment. Keep `APP_ENV=production` and never run it in production.
- [x] `RolesAndPermissionsSeeder` now uses additive `givePermissionTo()` for **student, instructor, content_manager, sales_support and admin**. Rerunning preserves custom role grants and is idempotent for coded grants (`PermissionSeederSafetyTest`). It still adds baseline grants; a deliberate role restriction cannot be expressed by merely removing a coded grant before reseeding.
- [ ] Before seeding, back up/export `roles`, `permissions`, `role_has_permissions`, `model_has_roles` and `model_has_permissions`, compare new baseline grants with the actual production matrix and obtain security/product approval. After `php artisan migrate --force`, run **only** `php artisan db:seed --class=RolesAndPermissionsSeeder --force` and `php artisan db:seed --class=PolicyPageSeeder --force` in a controlled maintenance window; verify grants afterward. Do not run `DevelopmentSeeder`; do not use an unreviewed `db:seed` shortcut. An explicit reviewed removal/migration procedure is needed for any unwanted permission.
- [ ] Provision the first production admin by a controlled, documented procedure with a unique secret and mandatory rotation; never use `.env.example` development credentials or `DevelopmentSeeder` sample identities.

### 6. Cache, queue, mail, scheduler and observability

- [ ] Choose durable, monitored `DB_CONNECTION`, `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` for the target topology. The example uses database cache/queue/session; ensure tables and worker processes exist. If email/notifications are added, run supervised queue workers with retry/failed-job alerting; a queued mail with no worker is not delivered.
- [ ] Configure a real `MAIL_MAILER`/sender and domain DNS (SPF/DKIM/DMARC as appropriate). Password reset now uses Laravel's notification/broker and SPA URL; test delivery, link HTTPS/host/query, expiry, one-use token and bounce/failure alerting with a real mailbox. The example uses `MAIL_MAILER=log`, which is **not** production delivery. Other transactional mail (account/order/payment/certificate) remains a separate original-requirement gap.
- [ ] `routes/console.php` currently has only the default `inspire` command; no application scheduler task was identified. If later jobs require scheduler/expiry reminders/cleanup, document and operate them then. Do not deploy an idle scheduler as proof those features exist.
- [ ] Set production log channel/level/retention and central collection with redaction of proof/file paths, tokens, credentials and personal data. Current critical services generally throw exceptions and roll back DB/files, but no dedicated alerting or structured business-event monitoring was found for checkout failure, payment approval/provisioning failure, file storage, PDF issuance or grading. Minimum launch alerts: repeated 5xx, failed queue jobs, payment stuck in Paid without Completed, orphaned private files, PDF generation failure, and support upload errors. Give each alert an owner and response runbook.
- [ ] Configure `/up` health check and monitor DB, disk capacity, TLS expiry, failed jobs, mail deliverability, backup freshness and error rate. A green HTTP health endpoint alone does not prove commerce or storage health.

### 7. Release verification and signoff

- [ ] On the release commit, run `php artisan test --compact`, `npm run test:frontend -- --maxWorkers=1` (stable Windows setting), `npm run build`, `vendor/bin/pint --dirty --format agent`, `git diff --check`, `php artisan route:list --except-vendor`, and `php artisan migrate:status --no-interaction`. Record command output, commit SHA and environment. Phase 15A local verification on 3 October 2026: 443 Laravel tests/2,033 assertions passed, 326 Vue tests/35 files passed, build/Pint/diff passed, 195 routes and all 48 migrations Ran. A two-worker Vitest attempt crashed a Windows worker without an assertion failure; one-worker rerun passed. These results are local, not release-commit or staging signoff.
- [ ] Execute the manual matrix in `docs/requirements-gap-audit.md` with real role accounts on staging, in Arabic and English, desktop and mobile. Include cookies/CSRF, file multipart, blob downloads, video host, QR/PDF, duplicate payment approval and role isolation. Record defects and retest. Unit/feature/mocked Vue tests do not replace this.
- [ ] Review the security findings and obtain an explicit risk acceptance for any remaining Medium/Low item. Do not accept the video-host High finding without proving source protection or changing the content-delivery design.
- [ ] Obtain written signoff from product, engineering, security, finance/operations and content/legal owners. Only then schedule a reversible release with backup, smoke test, rollback criteria and customer-support staffing.

## Phase 15A staging acceptance record

No authenticated production-like browser/session, approved legal copy, real mail provider or production infrastructure evidence was supplied in this work period. The following remain **QA_PENDING** and must be recorded with date, browser/device, role, disposable data ID, tester and outcome; automated tests are not substitutes.

| Workflow | Arabic desktop | Arabic mobile | English desktop | English mobile | Required evidence |
| --- | --- | --- | --- | --- | --- |
| Register Student, reject privileged role injection, logout/login | Pending | Pending | Pending | Pending | New disposable learner; 422/429, role response, cookies/CSRF |
| Forgot/reset by real delivered email, one-use/expired link, login new password | Pending | Pending | Pending | Pending | HTTPS `APP_URL` link, token/email, provider logs without secrets, session revocation |
| Manual purchase → proof → approval → access; idempotent retry | Pending | Pending | Pending | Pending | Disposable order, staff review, Paid→Completed, enrollment grant |
| Content Manager Lesson Resource upload/download; Student access; denied roles | Pending | Pending | Pending | Pending | Real multipart bytes, protected blob, no path metadata, wrong role/access 403/404 |
| Support attachment upload/download and Assignment file upload/download | Pending | Pending | Pending | Pending | PHP temp-path behavior, owner/internal isolation, wrong MIME/size |
| Privacy/terms/refund page and links | Pending | Pending | Pending | Pending | Approved Arabic/English copy, publication version, footer/auth/checkout |
| Certificate eligibility/issuance/QR/PDF/revoke | Blocked | Blocked | Blocked | Blocked | PRODUCT DECISION REQUIRED; do not issue QA credentials from an invented rule |

Production mail: the password broker is implemented, but `MAIL_MAILER=log` in `.env.example` is not delivery. Confirm sender and provider, SPF/DKIM/DMARC, retry/bounce alerting and safe link origin. With database sessions, password reset deletes that user's session rows and personal-access tokens; a different `SESSION_DRIVER` needs an equivalent cross-session invalidation design before release. Confirm `APP_URL`, `SANCTUM_STATEFUL_DOMAINS`, cookie domain/SameSite/secure flags, HTTPS forwarding and CORS on the deployed host without exposing secrets.

Private storage: web PHP `upload_max_filesize` and `post_max_size` (plus proxy body limit) must exceed the app's 20 MB Lesson Resource limit and other configured caps. Verify `lesson_resources` is outside the public web root, writable by the runtime, included in backup/restore and not symlinked into `/storage`; test file restore and disk-failure alerting. The course-level file requirement is not implemented by the lesson-scoped resource model. Production video hosting remains an independent High finding: prove signed/expiring access before selling protected video.

Permission deployment: Phase 15A changed reseeding to additive grants, not authoritative synchronization. It preserves custom grants but can introduce new baseline privileges. Diff/export actual production grants, obtain security approval, then run the exact targeted seeder commands above; verify Content Manager draft-only and Admin publication permissions, plus Student/Instructor/Sales Support denials. Preserve the backup/restore and rollback record. Do not mark this package complete merely because the seeder unit test passes.

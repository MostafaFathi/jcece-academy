# Phase 16 release acceptance — local evidence and launch gate

Date: 6 October 2026. Decision: **NO**. This record is a local acceptance pass, not staging signoff or a penetration test. The owner confirmed that no staging environment exists yet and will deploy at their own pace. **STAGING ENVIRONMENT: BLOCKED.** No production or production-like staging system was accessed, and no real financial transfer was made. A code path or passing automated test is never labelled a browser or infrastructure PASS below.

## Scope and evidence convention

The original Arabic `JCEC_Academy_Platform_Requirements_AR.docx` and the requirements gap, production-readiness, Phase 15B, commerce, financial documents/mail, reporting, frontend API and frontend development records were reviewed against the repository and local runtime. Historical statements in earlier phase documents are not treated as current implementation status where Phase 15C–15G addenda supersede them. The source requirement includes account/role separation, purchase and manual approval, protected learning, assessments, certificates, reporting, and public/legal content; live/hybrid delivery, external payment gateway, WhatsApp/SMS and free library have explicit future/conditional scope and are not silently promoted into Phase 16.

Statuses in every acceptance row are exactly one of `PASS`, `FAIL`, `BLOCKED`, `ENVIRONMENT_PENDING`, `CONTENT_PENDING`, `LEGAL_PENDING`, `NOT_APPLICABLE`. `PASS` is scoped to the specified local scenario only. `BLOCKED` means the required scenario was not executed; it does not assert a defect. `ENVIRONMENT_PENDING` identifies unavailable provider/operational infrastructure. Rows marked `CONTENT_PENDING` or `LEGAL_PENDING` require owner-approved publication, not fabricated copy. Evidence IDs below are reproducible observations, not screenshots of secrets.

| ID | Local evidence |
| --- | --- |
| E01 | `php artisan --version`, `php -v`, `npm ls vue vite vitest --depth=0`, `SELECT VERSION()` and specific safe `config:show` keys on 6 October. |
| E02 | Read-only local MySQL grant inventory before and after `php artisan db:seed --class=RolesAndPermissionsSeeder --force --no-interaction`; focused `PermissionSeederSafetyTest`/`ReportingApiTest`: 11 tests, 106 assertions. |
| E03 | In-app browser, separate temporary QA tab, guest public pages. Search `BIM` returned two Courses; paid filter plus ascending price returned 120, 180, 220 USD and URL filters. Guest `/admin/reports`, `/instructor/reports`, `/student/courses` redirected to login. |
| E04 | In-app browser after rebuilt CSS: Arabic RTL and English LTR on Home, Catalog, one Course detail, one Package detail and Login at 320/390/768/1280 px (40 page/locale/width combinations); `documentElement.scrollWidth <= clientWidth` in all sampled states. This is a sampled visual/DOM smoke check, not device or screen-reader certification. |
| E05 | In-app browser English 390 px: About and FAQ display unpublished editorial state; Contact form renders; Privacy, Terms and Refund pages display unpublished legal-policy notice. Local DB: all three `policy_pages` have version 0 and `published_at=NULL`; `site_pages` has no rows. |
| E06 | Full local Laravel suite: 516 passed / 2,488 assertions. Full Vue/Vitest: 355 passed / 41 files. Vite 8.3.0 production build succeeded with optional `fontaine` warning; Pint and `git diff --check` passed. 242 non-vendor routes; 61 migrations `Ran`, zero `Pending`. |
| E07 | Local queue inventory: `MAIL_MAILER=log`, database queue/session/cache, zero running PHP queue/scheduler workers observed, zero pending/failed jobs and zero transactional delivery rows. `schedule:list` reports no scheduled tasks; a stale-delivery recovery command exists but is not scheduled. |
| E08 | Local MySQL 5.7.33; tests in `phpunit.xml` use SQLite `:memory:`, array mail/cache/session and synchronous queue. Passing tests do not prove MySQL race behavior, real delivery, browser uploads or staging topology. |
| E09 | Arabic Home keyboard smoke: first Tab focused the visible-outline `#main-content` skip link; Enter moved focus to `<main id="main-content">`. Other controls, dialogs and assistive technologies were not exhaustively checked. |

## A. Environment inventory

| Property | Observed local value / disposition |
| --- | --- |
| Classification | `LOCAL` Laragon site; no staging target supplied; `STAGING ENVIRONMENT: BLOCKED`. Git HEAD `192d8f8` with an extensive pre-existing dirty worktree, not a frozen release artifact. |
| Runtime | Laravel 13.32.0, PHP CLI 8.4.17, Node 24.15.0, npm 11.12.1, Vue 3.5.43, Vite 8.3.0, Vitest 5.0.2. Web PHP version was not independently measured. |
| Data | MySQL 5.7.33 locally; tests use in-memory SQLite, so target-database concurrency is unaccepted. |
| Application and transport | `APP_ENV=local`, `APP_DEBUG=true`, HTTPS local application URL loaded in the browser. This is not a reviewed production TLS/proxy setup. |
| Queue/session/cache | Database drivers. No running queue worker observed; no scheduled tasks listed. Zero pending/failed jobs at inspection is not worker evidence. |
| Mail | `log` transport; **REAL TRANSACTIONAL MAIL: ENVIRONMENT_PENDING**. Password reset and delivery are not accepted. |
| Storage | Default, payment proof, assignment, support, Certificate PDF and financial PDF configured to local disk category; application uses private protected endpoints, but actual target permissions, backups and bytes were not exercised. |
| Protected video | `UnconfiguredProtectedVideoProvider` bound; playback host allowlist empty. **PROTECTED VIDEO PROVIDER: ENVIRONMENT_PENDING** for paid-video launch scope. |
| Cookies/origin | Session `secure=null`, `http_only=true`, `same_site=lax`; Sanctum stateful list includes local host; CORS credential support is false, consistent only with the tested same-origin model. Production values and cross-proxy behavior are unaccepted. |
| Logging/operations | Stack log channel; no target log rotation, queue supervision, alerting, backup schedule, restore rehearsal or incident owner evidence. |

## B. Permission deployment and capability matrix

The local pre-seed inventory contained 60 permission names and role grant counts Admin 60, Content Manager 40, Instructor 8, Sales Support 14, Student 2; direct user permission grants 0. The reviewed `RolesAndPermissionsSeeder` uses `Permission::findOrCreate` and additive `givePermissionTo`, never `syncPermissions`. Only the local development database was seeded. Afterward there are 63 names: `reports.view`, `reports.export` and `site_content.manage` were created and granted only to Admin; Admin has 63 grants, and every other role's grant count is unchanged. No role was removed or synchronized. The SQLite seeder regression confirms an injected custom Student grant survives rerunning; no custom live grant was intentionally created for this check. Production/staging still requires an exported before/after grant diff, security-owner approval, targeted seeding and direct-route retest. Obsolete grant removal remains the deliberate one-off reviewed procedure in `production-readiness.md`.

| Role | Catalog/learning | Content/assessments | Orders/payment/support | Coupons/refunds/documents | Reports/export | Authority boundary to prove on staging |
| --- | --- | --- | --- | --- | --- | --- |
| Student | Public catalog and owner-scoped learning/commerce | Own attempts, submissions, reviews, tickets | Own order and ticket only | Own receipt; no staff finance | None | No Admin/Instructor APIs, foreign owner IDs or staff grants. |
| Instructor | Assigned Courses only | Own read/results/submissions and grading, not global authoring | None by default | None | Own nonfinancial reports, no export | Foreign Instructor Course IDs denied; no global financial report. |
| Content Manager | Catalog/categories/packages/curriculum | Quiz/assignment, review and Certificate permissions; policy drafts | None by default | None | None | No financial/user-role authority; site-content management is Admin-only in current baseline and needs owner review if broader access is intended. |
| Sales Support | Catalog read and enrollment operations | No Certificate/review authority by default | Orders, manual payments, Support, limited user update | No Coupon/Refund/document authority by default | None | Do not infer original-document Coupon scope as a grant; later approved finance boundary remains restrictive. |
| Admin | All baseline permissions | All baseline permissions | All baseline permissions | Coupon/Refund/financial-document authority | `reports.view` and `reports.export` | Still requires actor/owner checks and staging security review. |

Backend direct authorization evidence is the focused report test (Student/Sales Support denied, Instructor constrained to owned nonfinancial reports and no Admin exports) and full endpoint suite E06. This is `PASS` for the specified automated tests, not the complete browser role matrix.

## C. Disposable QA data

No persistent QA accounts, orders, payments, grants, certificates or files were created in the shared local development database. It already contains seven users, three published Courses, two published Packages, three Orders and one issued Certificate; those records were not repurposed for destructive acceptance. Automated Laravel feature tests construct disposable in-memory SQLite fixtures and roll back; they cannot replace target-browser fixtures. This preparation item is `BLOCKED` until an isolated staging database and private storage are supplied. Required labels for that environment are `P16-QA-Student-A`, `P16-QA-Student-B`, `P16-QA-Instructor-A`, `P16-QA-Instructor-B`, `P16-QA-Content-Manager`, `P16-QA-Sales-Support`, `P16-QA-Admin`, plus clearly tagged draft/published/archived Courses, preview/protected Lessons and files, Quiz, Assignment, Certificate policy Course, normal/sequential Packages, valid/expired Coupons, zero/manual-payment/Refund scenarios, Support attachment and report data. Credentials must be provisioned out of the repository and never recorded here. Cleanup must target only the tagged isolated fixtures after evidence collection.

## D–AG. Acceptance evidence register

The account column says `Guest` for the browser-only public smoke, `Test roles` for disposable automated fixtures, and `None` when the required browser/operational scenario did not run. Every row has an expected result, actual result, evidence or gap, and retest disposition. `—` means no defect was established; it does not mean the path passed. The only confirmed Phase 16 defect is D16-001 below.

| Area | Status | Environment / account | Scenario and expected result | Actual result and evidence | Defect / retest |
| --- | --- | --- | --- | --- | --- |
| D Auth | BLOCKED | Local Guest; Test roles | AR/EN registration, duplicate, login/logout, bad credentials, recovery, password change, second-session invalidation and persisted language. | Login/register UI and guest redirects inspected; automated auth suite passed. No disposable browser account, real reset email or two-session run. E03, E06. | —; staging browser and real-mail retest required. |
| E Public site | CONTENT_PENDING | Local Guest | AR/EN Home, Catalog/search/filter/sort/pagination, Course/Package/Instructor, About/FAQ/Contact, legal pages, verification; published-only and truthful copy. | Sample public routes loaded; search and price sort passed locally. About/FAQ unpublished; seed Course/instructor prose is demo content. Draft/archived isolation lacks target fixture. E03, E05. | —; approved editorial population and full staging retest. |
| F Responsive/language | BLOCKED | Local Guest | 320/390/768/1280+ across public, auth, commerce, learning, assessments and workspaces, both directions. | 40 public/auth sampled combinations passed after D16-001; protected pages not checked at all widths. E04. | D16-001 fixed; broaden after fixtures. |
| G Accessibility smoke | BLOCKED | Local Guest | Keyboard/focus, labels/errors, dialogs, headings, landmark/skip link, alt and non-color states. | First Tab focused a visible skip link and Enter focused main; semantic headings and labeled catalog/contact/auth fields observed. No full keyboard/dialog/screen-reader run or WCAG claim. E03–E05, E09. | —; manual keyboard/device review. |
| H Cart/pricing | BLOCKED | None; Test roles | UI Course/Package add/remove, scheduled promotions, Coupon variants, currency, final server total and 409 `pricing_changed` review. | Server/component tests passed only; no authenticated cart/browser or price-conflict run. E06. | —; target browser retest. |
| I Purchase/payment/access | BLOCKED | None; Test roles | Student checkout → proof bytes → Admin approval → atomic provisioning → direct Course/Lesson access, retries. | No manual payment or real upload was performed; automated paths passed. E06. | —; critical staged E2E required. |
| J Zero-total order | BLOCKED | None; Test roles | Legitimate 0.00 checkout provisions without false proof, snapshots document and delivery. | Automated implementation exists; no browser/target-mail execution. E06. | —; staged E2E required. |
| K Sequential Package | BLOCKED | Local Guest; Test roles | First Course open, second locked until exact threshold, direct URL denial, expiry and historical order snapshot. | Public sample Package announces no threshold and cannot be purchased; automated sequence tests passed, no buyer browser fixture. E03, E06. | —; configure disposable Package and retest. |
| L Learning/resources | BLOCKED | None; Test roles | Navigate/progress/resume, preview/private file bytes, expiry, suspension, foreign resource denial. | Automated tests only; no actual browser download bytes. E06. | —; staged file and access retest. |
| M Protected video | ENVIRONMENT_PENDING | Local; None | Signed private playback, expiry/refresh, unauthorized/locked denial and hidden origin. | Default provider is deliberately unconfigured and host allowlist empty. Application authorization tests cannot prove CDN byte security. E01. | —; real private provider and target playback required. |
| N Quiz | BLOCKED | None; Test roles | Attempt/start/submit/result/limit/visibility; no key leak or cross-Course access. | Automated tests passed; no Student/Instructor browser attempt. E06. | —; staged role/owner retest. |
| O Assignment | BLOCKED | None; Test roles | Real multipart text/file, protected byte download, own grading/revision/correction and foreign denial. | No browser upload or download byte evidence; SQLite tests only. E06. | —; close historical multipart gap in staging. |
| P Certificate | BLOCKED | None; Test roles | Disposable configured policy → academic gates → Admin approval → issue/private PDF/QR → revoke/reissue, bilingual visual PDF. | Policy/service/PDF tests passed; no configured browser fixture, rendered target PDF or QR scan. E06. | —; staged full policy workflow. |
| Q Reviews | BLOCKED | None; Test roles | Eligible review/moderation/public aggregate/edit/history/ownership. | Public homepage shows no published reviews; automated tests passed, no browser flow. E03, E06. | —; staged moderation retest. |
| R Support | BLOCKED | None; Test roles | Student/staff multipart, actual bytes, internal-note isolation, status and foreign denial. | No authenticated browser upload; historical Support upload issue remains unclosed by browser. E06. | —; staged multipart retest. |
| S Refund | BLOCKED | None; Test roles | Partial financial-only, item-entitlement and full manual Refund; access effects, receipts, unrelated grant and Coupon history. | No real refundable QA purchase or verified external manual return; service tests passed. E06. | —; staged reconciliation and E2E. |
| T Financial documents | BLOCKED | None; Test roles | AR/EN own Order/Refund Receipt actual PDF, snapshot totals, owner/Admin access, foreign denial. | PDF implementation/test exists; no target-browser download or visual inspection this phase. Neither document is a statutory tax invoice. E06. | —; staged PDF/number reconciliation. |
| U Transactional email | ENVIRONMENT_PENDING | Local; None | Real mailbox delivery for welcome/reset/order/payment/refund/Certificate, locale, links, worker/retry/dedup. | Local `log` mail, no worker, no actual delivery. E07. | —; configure provider, worker and mailbox tests. |
| V Admin | BLOCKED | None; Test roles | Full CRUD/operations/content/audit/report browser matrix with direct authorization. | No disposable Admin browser session; endpoint tests passed. E06. | —; staged workflow retest. |
| W Instructor | BLOCKED | None; Test roles | Own dashboard/Courses/results/grading/report; other Instructor IDs and finance denial. | Report ownership endpoint test passed; no Instructor browser run. E02, E06. | —; staged two-Instructor matrix. |
| X Content Manager | BLOCKED | None; Test roles | Authorized content-only workflow and direct finance/role denials. | Baseline grants reviewed; no browser/direct target-role run. E02. | —; staged role matrix and site-content scope decision. |
| Y Sales Support | BLOCKED | None; Test roles | Orders/Support only; no Coupon, Refund, full finance, roles or Certificate by default. | Grant inventory and report denial test support current boundary; no browser full matrix. E02, E06. | —; staged role matrix. |
| Z Reports/exports | BLOCKED | Local; Test roles | Admin AR/EN filters/totals, Instructor scope, actual CSV/XLSX/PDF file content, row cap and privacy. | Local report permissions now applied; 11 focused tests/106 assertions passed. No authenticated browser download, PDF visual review or representative MySQL load/query timing. E02, E06. | —; staged files and finance reconciliation. |
| AA Audit log | BLOCKED | None; Test roles | Sensitive actions recorded without secrets/private body or paths. | Automated audit tests passed; no browser action/log inspection. E06. | —; staged representative event inspection. |
| AB Concurrency/idempotency | ENVIRONMENT_PENDING | Local SQLite tests; no target DB run | MySQL checkout, approval, grant, Certificate, last Coupon, over-Refund, Receipt and mail races. | Local MySQL exists but suite runs on SQLite and no isolated MySQL concurrency fixture exists. E08. | —; production-equivalent MySQL tests. |
| AC Backup/restore | ENVIRONMENT_PENDING | None | Restore DB, private uploads, receipts and Certificate PDFs in safe nonproduction target. | No backup/restore procedure or rehearsal evidence provided. | —; operator restore rehearsal. |
| AD Production config | ENVIRONMENT_PENDING | Local only | Debug off, HTTPS/cookies/proxy/CORS/Sanctum, queue/scheduler/mail/storage/logs/monitoring/backups/video. | Current local `APP_DEBUG=true`, `MAIL=log`, `SESSION_SECURE_COOKIE=null`, no workers/schedule and no target configuration to review. E01, E07. | —; operator target config review. |
| AE Security smoke | BLOCKED | Local Guest; Test roles | IDOR, privilege escalation, upload/XSS/rate/reset/audit/export/video privacy across roles. | Guest workspace redirects and automated policy tests passed; no complete target role/file/provider security smoke. Not a penetration test. E02, E03, E06. | —; staged security-owner review. |
| AF Database/migrations | BLOCKED | Local MySQL; None | Target baseline upgrade, no pending migration, constraints and no destructive rewrite. | Local 61 Ran/0 Pending, but no staging baseline, backup or target schema validation. E06. | —; migrate/constraint review on isolated target. |
| AG Test suites/build | PASS | Local automated | Full Laravel/Vue, production build, Pint, diff, route and migration audit. | 516/2,488; 355/41; build/Pint/diff passed; 242 routes; 61 Ran/0 Pending. E06. | D16-001 manual browser retest also passed; rerun on frozen release artifact. |

## Defect handling

| ID | Severity | Root cause and change | Before | Retest | Current status |
| --- | --- | --- | --- | --- | --- |
| D16-001 | Medium | `resources/css/app.css` forced `body` to `min-width:320px`. With a 15 px vertical scrollbar at a 320 px Windows viewport, the usable layout width was 305 px and a horizontal scrollbar appeared. Removed that minimum width; no design/module change. | English Home/Catalog/Course/Package at 320 px: `scrollWidth=320`, `clientWidth=305`, visible horizontal scrollbar. | Rebuilt Vite, reloaded, inspected screenshot and repeated 40 AR/EN × 5 representative routes × 4 widths: `scrollWidth<=clientWidth`; Catalog 320 px now `305=305`. No automated browser-layout harness is installed; this is a manual regression test, not a component-test claim. | PASS locally; staging device/browser retest remains. |

No other Critical, High, Medium or Low code defect was established by the executed local checks. Unexecuted scenarios are not evidence of defect absence. Demo editorial copy and unpublished legal/editorial pages are release blockers, not invented software bugs. No new module, migration or dependency was added in Phase 16.

## Forty-point final report

1. Environment tested: local Laragon HTTPS, Laravel 13.32.0/PHP 8.4.17/MySQL 5.7.33; no staging.
2. Acceptance accounts/fixtures: no persistent QA accounts or business fixtures created in shared local data; disposable automated fixtures only; staged fixture matrix blocked.
3. Permission deployment: local additive seed completed; Admin 60→63, other grant counts unchanged, report permissions Admin-only; staging approval/deployment pending.
4. Auth: guest protected-route redirects and automated tests observed; two-session browser and real reset-mail acceptance blocked.
5. Public site: representative guest pages, BIM search and price sort checked; legal/editorial publication incomplete.
6. Commerce E2E: not executed in an authenticated browser or staging.
7. Sequential Package: code tests pass; sample public path lacks threshold and cannot serve as buyer fixture.
8. Learning/files: automated coverage only; actual browser bytes and access states unverified.
9. Protected video: provider unconfigured; environment pending.
10. Quiz: browser attempt/result/security matrix blocked.
11. Assignment: real multipart/revision/grading browser matrix blocked.
12. Certificate: full configured policy→approval→PDF/QR/revoke/reissue browser matrix blocked.
13. Review: moderation/aggregate browser matrix blocked.
14. Support: real multipart/internal-note browser matrix blocked.
15. Refund: manual financial and entitlement scenarios blocked without disposable paid Orders.
16. Financial document: protected target PDF download/visual reconciliation blocked; neutral Receipt, not statutory invoice.
17. Real mail: `log` transport and no worker; delivery pending.
18. Admin: no disposable authenticated Admin browser run.
19. Instructor: backend own-report scope tested; full two-Instructor browser matrix blocked.
20. Content Manager: grant inventory reviewed; direct browser/role matrix blocked.
21. Sales Support: no report grants and backend report denial tested; full browser role matrix blocked.
22. Reports/exports: Admin permissions applied locally and 11 focused tests/106 assertions passed; actual browser CSV/XLSX/PDF and financial reconciliation blocked.
23. Audit: automated coverage only; staged action-by-action privacy inspection blocked.
24. Concurrency: SQLite test suite cannot certify target MySQL races; environment pending.
25. Backup/restore: no safe target restore rehearsal; environment pending.
26. Production config: only local configuration reviewed; debug on, log mail, secure-cookie unset, no workers/schedule; target review pending.
27. Security smoke: guest redirects and automated policies observed; full role/file/provider review blocked; not a penetration test.
28. Arabic/English/responsive/accessibility: 40 public/auth width-locale samples passed after CSS fix; private workspaces, keyboard/device and screen-reader acceptance blocked.
29. Defects found: one Medium, D16-001, 320 px horizontal scrollbar.
30. Defects fixed: D16-001 by removing `body` minimum width; rebuild and 40-sample browser retest passed.
31. Remaining Critical defects: none established by executed checks; untested scenarios cannot be cleared.
32. Remaining High defects: none established by executed checks; untested scenarios cannot be cleared.
33. Remaining Medium/Low defects: none established after D16-001 retest; incomplete editorial/legal data are separate blockers.
34. Remaining P0 blockers: real staging E2E/security, approved legal/editorial publication, protected paid video, real mail/reset, private-file/PDF, target config and restore.
35. Remaining P1 acceptance: sequential path, coupons/promotions/refunds, documents, reports/exports, profiles/public content, audit and real Support/Assignment multipart are locally coded but not staged/browser accepted.
36. Final Laravel suite: 516 tests, 2,488 assertions, all passed locally.
37. Final Vue suite: 355 tests in 41 files, all passed locally.
38. Vite production build, Pint and `git diff --check` passed; optional `fontaine` warning did not fail build.
39. Route audit: 242 non-vendor routes.
40. Migration status: 61 local migrations Ran, zero Pending; target baseline not reviewed.

## Release gate matrix

This matrix judges release acceptance, not code availability. There are no launch-wide PASS claims from local-only evidence.

| Gate | Status | Gate | Status |
| --- | --- | --- | --- |
| AUTH | BLOCKED | PUBLIC SITE | CONTENT_PENDING |
| LEGAL CONTENT | LEGAL_PENDING | EDITORIAL CONTENT | CONTENT_PENDING |
| RESPONSIVE | BLOCKED | ACCESSIBILITY SMOKE | BLOCKED |
| CART/PRICING | BLOCKED | PURCHASE | BLOCKED |
| PAYMENT | BLOCKED | ACCESS | BLOCKED |
| SEQUENTIAL LEARNING | BLOCKED | FILES | BLOCKED |
| PROTECTED VIDEO | ENVIRONMENT_PENDING | QUIZZES | BLOCKED |
| ASSIGNMENTS | BLOCKED | CERTIFICATES | BLOCKED |
| REVIEWS | BLOCKED | SUPPORT | BLOCKED |
| COUPONS | BLOCKED | REFUNDS | BLOCKED |
| RECEIPTS | BLOCKED | TRANSACTIONAL EMAIL | ENVIRONMENT_PENDING |
| ADMIN | BLOCKED | INSTRUCTOR | BLOCKED |
| CONTENT MANAGER | BLOCKED | SALES SUPPORT | BLOCKED |
| REPORTS | BLOCKED | EXPORTS | BLOCKED |
| AUDIT LOG | BLOCKED | PERMISSIONS | BLOCKED |
| CONCURRENCY | ENVIRONMENT_PENDING | BACKUP/RESTORE | ENVIRONMENT_PENDING |
| PRODUCTION CONFIG | ENVIRONMENT_PENDING | SECURITY SMOKE | BLOCKED |
| ARABIC | BLOCKED | ENGLISH | BLOCKED |

## Required conditions to change NO to YES

1. Provision an isolated, production-like staging environment from a frozen release commit; identify test accounts and create only clearly tagged disposable fixtures and private files. Review/apply migrations and additive permissions with exported before/after grants and security-owner approval.
2. Obtain and publish owner-approved Arabic/English legal Privacy, Terms and Refund text and real About/FAQ/Course/Instructor editorial material; remove demo claims and verify published/draft/archived boundaries.
3. Configure and prove real private paid-video delivery, real transactional/password-reset mail with supervised queue/failed-job recovery, HTTPS secure-session/proxy settings and private file storage. Do not use `log` mail or mock video as acceptance evidence.
4. Execute and record the full staged AR/EN desktop/mobile role and owner matrix, especially purchase → proof-byte upload → payment approval → access; sequential locks; lesson/assignment/support file bytes; Quiz, Certificate policy/PDF/QR/revoke/reissue; Refund and Receipts; reports/exports and audit privacy. Retest D16-001 on real devices. Resolve any Critical/High defects with regression evidence.
5. Prove MySQL concurrency/idempotency on the target topology, review representative report query timings, rehearse database/private-file/document restore, and obtain operations/security/product/legal signoff of production configuration and launch scope. Tax/VAT statutory invoicing remains a separate product/legal decision if such documents are to be advertised.

**CAN JCEC ACADEMY ENTER RELEASE CANDIDATE? NO.**

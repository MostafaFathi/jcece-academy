# JCEC Academy frontend development

The Vue 3 application is served by Laravel from the same origin. Keep API requests relative so Sanctum session and XSRF cookies work without cross-origin configuration.

## Local development

1. Configure the Laravel application and database as documented by the project environment.
2. Install dependencies with `composer install` and `npm install`.
3. With Laragon, start the site and run `npm run dev`; the current project URL is `https://jcec-academy.local`.
4. Without Laragon, run Laravel and Vite together with `composer run dev`, or run `php artisan serve` and `npm run dev` separately.
5. Open the Laravel URL, not Vite's asset-server URL.

Keep `APP_URL` aligned with the Laravel URL. The same-origin default works with `SESSION_DOMAIN=null`; `SANCTUM_STATEFUL_DOMAINS` must contain the browser host. In production, also use `SESSION_SECURE_COOKIE=true` over HTTPS.

For a Laragon HTTPS site, configure Vite with Laragon's trusted certificate and restart the Vite process after changing `.env`:

```dotenv
APP_URL=https://jcec-academy.local
VITE_DEV_SERVER_KEY=C:/laragon/etc/ssl/laragon.key
VITE_DEV_SERVER_CERT=C:/laragon/etc/ssl/laragon.crt
```

The Laravel Vite plugin then publishes an HTTPS development URL using the same host, which keeps module loading and HMR on a trusted origin instead of `http://[::1]:5173`.

Useful checks:

- `npm run test:frontend` runs the Vitest suite.
- `npm run build` creates production assets.
- `php artisan test --compact` runs backend tests.

## Production

Run `npm ci` followed by `npm run build` during deployment and serve Laravel's `public` directory through the web server. Use HTTPS and set `SESSION_SECURE_COOKIE=true`. Same-origin deployment is preferred; sibling subdomains additionally require matching `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, and credentialed CORS settings.

Laravel returns the SPA shell for frontend history routes. `/api`, `/sanctum`, `/up`, `/storage`, `/assets`, and `/build` are excluded from that fallback. The frontend uses `/sanctum/csrf-cookie` before session login and never stores tokens or sensitive session data in browser storage. Only the non-sensitive language preference is persisted.

## Current boundary

Phases 10–12C provide the public homepage, course and package catalogs/details, published course reviews, login, localized responsive navigation, dashboard shells, authenticated commerce pages, My Courses, the course learning player, student quizzes/assignments/certificates, and public certificate verification. The public application integrates these implemented endpoints:

- `GET /api/v1/categories`
- `GET /api/v1/courses` and `GET /api/v1/courses/{slug}`
- `GET /api/v1/courses/{slug}/reviews`
- `GET /api/v1/packages` and `GET /api/v1/packages/{slug}`
- the existing Sanctum session authentication endpoints

The public catalog persists supported search, filter, sort, and page values in URL query parameters. Catalog/cart prices expose configured backend currency; missing currency is shown explicitly and blocks new checkout. Money rendering preserves decimal strings without floating-point totals.

Student commerce routes live under `/student/cart`, `/student/checkout`, `/student/orders`, and `/student/orders/:id`, backed by `/api/v1/me/cart`, `/checkout`, `/orders`, order payments, and private proof download. Cart/auth/customer/payment data stays in memory. Only language preference uses localStorage and the non-sensitive checkout UUID uses sessionStorage to recover uncertain requests. See `frontend-api-contract.md` for exact retry, status, ownership, and upload behavior.

Public registration, password reset, and email verification endpoints are not implemented. Gateways, coupons, refunds, review management, support-ticket UI, instructor grading UI, admin business pages, and unrelated business screens remain outside Phase 12C.

Manual-payment account instructions still need a managed backend/configuration contract. No bank or wallet details are fabricated. Automated API/component tests do not replace desktop/mobile RTL smoke testing. Historical Phase 12A browser results are recorded below; no live order/payment was created or approved.

Phase 12B removes lesson-resource storage metadata from every shared resource response and direct model serialization. The learning UI downloads through the authenticated nested resource endpoint, never through raw paths. Configure actual files in the dedicated private `lesson_resources` disk; default/public disks are not used. The seeded worksheet is only a reference without file bytes. See `frontend-api-contract.md` for safe reference format, server-controlled filename and legacy provisioning requirements.

## Phase 12A implementation report

Paths below are relative to `resources/js/` unless noted otherwise.

| Area | Created files | Modified files |
|---|---|---|
| API/state | `api/commerce.js`, `stores/cart.js`, `utils/commerce.js` | None |
| Pages | `pages/CartPage.vue`, `pages/CheckoutPage.vue`, `pages/OrdersPage.vue`, `pages/OrderDetailPage.vue` | `pages/CourseDetailPage.vue`, `pages/PackageDetailPage.vue` |
| Components | `components/commerce/AddToCartButton.vue`, `CartSummary.vue`, `MoneyAmount.vue`, `CommerceStatus.vue`, `CommerceError.vue`, `ManualPaymentForm.vue` (all in the same commerce directory) | `components/public/CourseCard.vue`, `PackageCard.vue`, `PublicHeader.vue` |
| Routing/localization | `i18n/commerce.js` | `router/index.js`, `composables/navigation.js`, `i18n/ar.js`, `i18n/en.js` |
| Frontend tests | `tests/cart.test.js`, `commerce-api.test.js`, `commerce-pages.test.js`, `commerce-routing.test.js` | `tests/home-page.test.js`, `course-catalog-page.test.js`, `package-catalog-page.test.js`, `public-detail-pages.test.js` |
| Backend | `tests/Feature/CommerceCurrencyApiTest.php` | Currency-bearing catalog/cart resources, CartItem duration, MeOrder proof limit, shared currency resolution in CommerceCatalogService/OrderCheckoutService |
| Documentation | None | `docs/frontend-api-contract.md`, this document |

Integrated endpoints are GET/DELETE `/api/v1/me/cart`, POST `/cart/items`, DELETE `/cart/items/{id}`, POST `/checkout`, GET `/orders`, GET `/orders/{id}`, POST `/orders/{id}/payments`, and GET `/payments/{id}/proof` (all short paths are under `/api/v1/me`). No new API endpoints or dependencies were added.

Verification on 2026-09-28: full frontend suite **91 tests passed**; full Laravel suite **339 tests / 1525 assertions passed**; focused currency/cart/checkout/order/payment suite **27 tests / 160 assertions passed**. Vite production build, Pint, `git diff --check`, and the `/api/v1/me` route audit passed. Vite reports the existing optional `fontaine` font-fallback warning; it is non-blocking and no dependency was installed. Browser smoke testing remains blocked by an empty connected-browser inventory, including desktop/mobile visual verification. No real payment was submitted or approved.

Phase 12A stops here. External gateways, coupons, refunds, student learning player, quizzes UI, assignments UI, certificates UI, admin commerce pages, and unrelated business modules were not implemented.

## Phase 12B implementation report

Completed on 2026-09-29, without dependency changes or schema migrations. Laravel 13.32.0 and the installed Vue 3/Tailwind 4 APIs were used. Laravel/security/testing guidance shaped private file delivery, authorization regression coverage and server-confirmed progress; existing brand tokens and logical RTL spacing were reused.

| Area | Created | Modified |
|---|---|---|
| Backend security | `app/Services/LessonResourceFileService.php`, `app/Http/Controllers/Api/V1/Me/LessonResourceDownloadController.php` | Shared `LessonResourceResource`, hidden model fields, admin resource reference validation/create response, filesystems config, API routes |
| Access contract | `app/CourseAccessState.php` | `CourseAccessService`, Me CourseController, StudentEnrollmentResource, StudentLearningCourseResource, StudentCourseResource |
| Frontend API/state | `resources/js/api/learning.js`, `composables/useCourseLearning.js`, `utils/learning.js` | None |
| Pages | `resources/js/pages/MyCoursesPage.vue`, `CourseLearningPage.vue` | Router: `/student/courses`, `/learn/courses/:slug`; old `/student/learning` redirects |
| Components | `resources/js/components/learning/ProgressSummary.vue`, `LearningError.vue`, `LearningCurriculum.vue`, `LessonResources.vue`, `TextLesson.vue`, `VideoLesson.vue`, `FileLesson.vue`, `LinkLesson.vue` | Student navigation/locales |
| Tests | `tests/Feature/Api/V1/Me/LessonResourceDownloadTest.php`; frontend `learning-api.test.js`, `learning-pages.test.js`, `learning-routing.test.js` | Existing learning, admin curriculum and public preview regression tests |
| Documentation | None | API contract and this document |

Integrated endpoints: GET `/api/v1/me/courses`, GET `/courses/{slug}/learn`, GET `/courses/{slug}/progress`, PATCH `/courses/{slug}/lessons/{lesson}/progress`, POST `/courses/{slug}/lessons/{lesson}/complete`, and new GET `/courses/{slug}/lessons/{lesson}/resources/{resource}/download` (short paths are under `/api/v1/me`). No undo-complete endpoint was invented.

My Courses distinguishes enrollment from effective active/expired/suspended/scheduled/revoked/unavailable access, shows lifetime/current finite expiry and instructor, and gates continuation on server `has_access`. The player supports the actual text/video/file/link types, safe escaped content, a native video URL only, scoped private blob downloads, responsive curriculum and Arabic RTL/English LTR. Resume uses the server’s most recent applicable lesson, otherwise a deterministic incomplete/first lesson fallback. No learning data is stored in localStorage.

Progress mutations and navigation are serialized. The server response is confirmed before completed UI appears; `/progress` refreshes lesson records and course counts/percentage, and My Courses fetches again on re-entry. A protected 401/403/404 clears all lesson/course/video/resource content. Quiz/assignment progress and certificate eligibility were not changed.

Verification:

- Full frontend suite: **148 tests passed** (57 Phase 12B cases), 20 files.
- Full Laravel suite: **378 tests / 1663 assertions passed** (39 added cases); focused protected download suite: **37 tests / 90 assertions passed**, including a rerun after Pint.
- Vite production build passed; the existing optional `fontaine` font-fallback warning remains non-blocking. No dependency was added.
- Pint, `git diff --check`, and the authenticated course-route audit passed. `migrate:status` reports all existing migrations ran; this phase adds none.
- Security audit covered shared student/admin/nested/reorder resource serialization, hidden model JSON, scoped IDs, foreign users/courses/lessons, expiry/revocation/suspension, disabled/missing files, traversal/absolute/encoded paths, resolved paths outside the private root, default-public-disk independence, safe attachment headers and preview exclusion.

Browser smoke testing used the connected in-app browser and existing seeded Sample Student, without creating users, orders, payments or access grants. Guest navigation redirected to login; login and My Courses succeeded; the active course opened at its server resume lesson; previous/next navigation worked. Marking the seeded reading lesson complete changed the server/player/My Courses from **0/3 (0%)** to **1/3 (33.33%)**. This intentionally persists one completion for the development sample student; no real student records were changed. English LTR and Arabic RTL labels, a desktop sidebar/content layout and mobile curriculum expand/collapse were checked. DOM viewport checks found no horizontal overflow (desktop 1440px and mobile 375px client widths). The missing seeded worksheet returned 404 and the player cleared protected content and displayed its localized safe error; returning to My Courses showed the refreshed 33.33% progress. Temporary viewport overrides were reset, and Arabic My Courses was left open.

Remaining content/manual checks: successful download of an actual provisioned private worksheet; live expired/suspended/foreign-user resource checks with suitable disposable accounts; native video playback/position saving with an actual playable video URL (provider-ID-only data is intentionally not embedded). These are covered by automated API/component tests where applicable, but were not claimed as live browser passes. No private/public files were moved or deleted, and no live grants were changed. Trusted file provisioning remains required; the seeded resource reference has no bytes in the private root. Previously hosted public copies must be removed or restricted by the operator if they exist; none were found in the inspected lesson-resource locations.

Phase 12B stops here. Quiz attempt UI, assignment submission UI, certificate UI, reviews management, support-ticket UI, admin business pages, external integrations, new commerce functionality and unrelated modules were **not implemented**.

## Phase 12C implementation report

Completed on 2026-09-30 without dependency changes or migrations. The existing brand tokens, responsive Tailwind utilities and Arabic RTL/English LTR shells were reused. Student navigation now includes Certificates; quizzes and assignments appear contextually in the course player by exposed course/lesson IDs, separate from `LessonProgress`.

New frontend files: `api/assessments.js`, `i18n/assessments.js`, `pages/QuizPage.vue`, `AssignmentPage.vue`, `CertificatesPage.vue`, `CertificateVerificationPage.vue`, `components/learning/CourseAssessments.vue`, and tests `assessments-api.test.js`/`assessments-pages.test.js`. Existing router, navigation, learning page, locales and routing tests were updated. Routes are `/student/quizzes/:id`, `/student/assignments/:id`, `/student/certificates` (session-protected) and `/certificates/verify/:token` (public).

Quiz UI integrates the actual list/detail/attempt-list/start/show/result/answer-save/submit endpoints. Only actual single-choice, multiple-choice and true/false questions render. Option selections stay in memory, save as exact ID sets, and final submission requires confirmation. Client countdown uses server `expires_at` for display, never for authoritative rejection. Score/correct answers/explanation render only when present in the conditional server resource; hidden results are not reconstructed in Vue. The backend remains responsible for access, window, limit, expiry and grading.

Assignment UI integrates list/detail/submission-list/start/show/text-save/multipart-file-upload/file-delete/final-submit, plus authenticated attachment and submission-file downloads. Save Draft and Submit are separate; final submission requires confirmation. A revision-requested attempt stays read-only and a new draft is explicitly created as a new attempt. Grade, feedback, deadlines and limits come from the API; no staff grading controls or storage paths are exposed.

Certificate UI integrates owner list/detail contract, eligibility, idempotent issuance and authenticated PDF download. The eligibility response now adds the student's latest `certificate_status` (`issued`, `revoked`, or null), while owner resources add safe `course_id`; Vue does not infer eligibility from course progress. Revoked history remains visible. Public verification consumes the existing JSON API and displays only the public resource fields, with a normal unknown-token state. New server-generated QR PDFs and certificate resource URLs target the SPA `/certificates/verify/{token}`. Old QR PDFs targeting the API route still work: HTML browsers redirect to SPA; JSON API clients retain the previous response/404 contract. No client-generated PDF or QR exists.

Verification: the full frontend suite passed **180 tests**; the full Laravel suite passed **380 tests / 1677 assertions**. The Vite production build, focused certificate API suite, Pint, route audit and `git diff --check` passed. Browser smoke confirmed the unknown-token public verification page in Arabic RTL at mobile width and the old API QR URL's HTML redirect to it. The expired sample session was renewed with the seeded development account. Its enrolled course loaded the assessments section, correctly showed no currently published assessments, and displayed backend certificate ineligibility with the incomplete-lessons reason. Student Certificates showed the empty historical state; Arabic RTL and English LTR mobile layouts were visually checked, then Arabic was restored. Live quiz/assignment workflows, valid/revoked certificate pages, issuance, PDF downloads and desktop layout remain pending because the connected sample account has no published assessments or issued/eligible certificate. Automated tests exercise their contracts. No live quiz attempt, assignment submission or certificate was created by the browser check.

Phase 12C stopped here. Course Reviews UI and Support Ticket UI were implemented separately in Phase 12D; instructor grading UI, admin business-management pages, external payment gateways and unrelated modules remain outside scope.

## Phase 12D implementation report

Student reviews are contextual to My Courses and the learning player. The form uses native 1–5 radio inputs, optional title/body fields and the existing owner-scoped review endpoints. Existing reviews remain visible when access expires or enrollment is suspended; editing requires effective access and resubmits as pending. Public course pages still consume only the published-review API and the backend rating aggregate.

Student Support adds `/student/support`, `/student/support/new` and `/student/support/:id` with list pagination, owned course/order selectors, public chronological messages, explicit closed-ticket reopening, server-refreshed reply status, multipart attachments and protected blob downloads. No internal notes, activity records or storage paths are mapped into the student UI. Arabic RTL and English LTR copy is in `resources/js/i18n/reviews-support.js`.

The full frontend suite passed 207 tests, the full Laravel suite passed 380 tests / 1677 assertions, and the Vite build, Pint, route audit and `git diff --check` passed. Browser smoke on the development Sample Student confirmed desktop/mobile and Arabic/English layouts, review creation to pending, exclusion from the public course count/average, support ticket creation and a public reply. A real file-upload attempt via the connected browser failed with the local PHP error `Path must not be empty`; protected download could therefore not be exercised live. The existing Laravel attachment tests pass for valid uploads, private download, cross-user denial and internal-note denial. Publishing/editing the sample review and closed/reopen status changes were not performed in the live development data.

No migrations, dependencies, admin review/support pages, instructor grading, admin business pages, live chat, notifications or external integrations were added in Phase 12D.

## Phase 13A implementation report

The admin shell now routes to a permission-aware dashboard, category list/create/edit and course list/edit under `/admin`. Its existing responsive sidebar/mobile drawer, breadcrumbs, language switcher, user menu and logout are reused. The sidebar has only working destinations. Categories support server pagination, editable slugs, parent selection on creation, 422 validation and deletion dependency feedback. Arbitrary reparenting on edit is withheld because the backend currently does not reject descendant cycles; moving to the top level remains available. Course search/status/level filters stay in the URL, and the responsive list shows the server's currency, status and access duration. The metadata form preserves decimal price strings, sends `NULL` for lifetime access and a positive integer for limited access, omits the unavailable instructor reassignment field, and respects the separate `courses.publish` permission. It does not touch curriculum.

The admin API audit found no user CRUD, instructor directory/profile CRUD or dashboard statistics endpoint. The first two are not represented by fake pages or API calls. The dashboard uses pagination `meta.total` from category/course index requests with small page sizes, omitting unavailable user/instructor metrics. Because course creation requires a valid `instructor_id` and there is no suitable selector endpoint, its create action is explicitly unavailable rather than allowing arbitrary IDs or partial account creation. No backend contract, migration or dependency was changed. Details of this mismatch are also in `frontend-api-contract.md`.

The Phase 12D development-browser Support attachment upload issue (`Path must not be empty`) remains a manual blocker, unrelated to Phase 13A and not modified here.

Verification on 2026-09-30: **232 frontend tests passed** (28 files); **380 Laravel tests / 1677 assertions passed**. Vite production build, Pint, route audit and `git diff --check` passed. Vite's existing optional `fontaine` font-fallback warning remains non-blocking. The connected development browser's previous Sample Student session had expired; opening `/admin` redirected to login, confirming guest protection. Admin and Content Manager visual/CRUD smoke tests remain pending a confirmed suitable account session. No live category, course, account or payment records were changed for this check.

Phase 13A stops at admin dashboard/category/course metadata UI. User and instructor management await actual backend contracts; course creation awaits a safe instructor selector. Curriculum, packages, orders/payments, review moderation, support administration, certificates, assignment grading, reports/analytics, external integrations and unrelated modules were not implemented.

## Phase 13A.1 implementation report

Phase 13A.1 completes the missing Admin User and Instructor list/create/edit UI and APIs. The existing admin shell, design tokens, responsive table/card pattern, Arabic RTL/English LTR behavior, API client and permission-based navigation are reused. Users support server-side pagination/search/role/status filters with URL synchronization. Instructor management keeps account and actual profile fields in a single form, but permits profile-only edits to authorized Content Managers. Password fields are never prefilled, empty updates are omitted, and 422 errors appear beside fields. Account/role/password changes are protected by backend policy, not UI state.

The Course form now supports create and edit with a paginated/searchable lightweight instructor selector. It preserves the assigned or newly chosen instructor across option searches, and course creation sends the existing `instructor_id` foreign-key contract. Category edit now permits valid reparenting; the server rejects self-parenting and descendant cycles with a `parent_id` validation error. The dashboard uses one permission-keyed summary request rather than several list requests merely to count rows.

New backend routes under `/api/v1/admin`: `GET /dashboard-summary`; `GET/POST /users`, `GET/PUT/PATCH /users/{id}`; `GET/POST /instructors`, `GET/PUT/PATCH /instructors/{id}`; and `GET /instructor-options`. New permissions `users.manage` and `instructors.manage` belong to Admin through the role seeder. Existing `users.view/update`, `instructors.view/update`, and course create/update permissions remain separately scoped. An existing User is not converted into an Instructor by this UI/API. No delete endpoint was added. To enable the new Admin permissions on an existing local database, run the reviewed role/permission seeder as part of the deployment workflow; it synchronizes configured role grants, so review custom role changes before running it.

No migrations or dependencies were added. This phase does not implement Curriculum, Packages, Orders/Payments administration, review moderation, Support administration, certificate administration, assignment grading, reports/analytics, external integrations or unrelated modules. The earlier development-browser Support attachment upload issue (`Path must not be empty`) remains outside this phase and unresolved.

Verification on 2026-10-01: **261 frontend tests passed** (29 files); **407 Laravel tests / 1818 assertions passed**. Vite production build, Pint, `git diff --check`, admin route audit and migration status passed; all existing migrations are applied. The build retains the pre-existing optional `fontaine` fallback warning. After Pint, the modified instructor API test passed again (9 tests / 44 assertions). Manual Admin/Content Manager browser CRUD, responsive and Arabic/English checks remain pending suitable authenticated accounts; no live account, instructor, course, category or payment records were changed. The role seeder was not run against the existing development database because it synchronizes configured grants and may overwrite local permission customizations.

## Phase 13B implementation report

The Admin course list and edit form now link to a dedicated `/admin/courses/:id/curriculum` builder. It displays course → expandable sections → lessons → resources as stacked responsive cards, with Arabic RTL/English LTR labels and keyboard-accessible up/down controls. Section CRUD includes the actual title, description and active state; deletion requires confirmation and the database cascade can affect lessons/resources and learning records. Lesson CRUD uses actual `text`, `video`, `file`, `link` types with contextual fields, separate `is_published`/`is_preview` flags, and safe plain-text editing. Section, lesson and resource reorder requests send an exact permutation of IDs, block overlapping submissions and reload authoritative order on success/failure. Cross-section movement is not supported.

Resource management lists safe metadata, creates HTTP(S) links, edits metadata, deletes and reorders. There is **no browser file-upload endpoint or admin file-download endpoint** in the present backend contract. The UI never sends a client filesystem path or displays `file_path`/storage metadata; file lessons can be drafted, and trusted private-file provisioning remains an operator task. Student downloads still use the owner-scoped protected endpoint. Consequently, the requested live Lesson Resource upload comparison cannot be performed, and the earlier Support attachment `Path must not be empty` error remains unclassified (neither confirmed shared nor fixed). Do not treat it as Support-only until an independent upload flow can be exercised. No Support workflow was changed.

New Admin package pages use `/admin/packages`, `/admin/packages/new`, `/admin/packages/:id/edit` and `/admin/packages/:id/courses`. The list supports backend search/type pagination (not status filtering) and displays the server-configured currency, decimal price string, `course_count`, duration and status. The form sends `null` for lifetime access or a positive day count, and requires the separate publish permission. Composition uses server-paginated searchable courses, prevents duplicate selection, and reorders **membership IDs**, not course IDs. Changes affect only current membership and future purchases; old order snapshots and access grants are never rewritten. The public API continues to exclude draft, hidden and future-unavailable courses even within a published package.

Admin and Content Manager retain content/package write permissions. Instructor has curriculum read only; a pure Instructor can now create a course only for themselves and edit only their assigned course, enforced by `CoursePolicy` and the instructor-option endpoint. Instructor curriculum read remains permission-based rather than owner-filtered; no curriculum mutation is permitted. Permission-aware navigation avoids inaccessible modules. One package resource change exposes configured currency, and the summary endpoint exposes `total_packages` only to `packages.view` callers. There are no migrations or dependency changes.

Manual Admin/Content Manager/Instructor browser CRUD, real resource upload/download, public preview, mobile and language checks are pending suitable authenticated accounts and a resource upload contract. No development account, purchase or course was created merely for smoke testing. Before role-specific manual testing, review local role customizations and the `RolesAndPermissionsSeeder` sync behavior; the development seeder has **not** been rerun automatically, so `users.manage`/`instructors.manage` grants may not yet be present there. The outstanding Support browser upload error remains open.

Phase 13B stops here. Orders/payments administration, review moderation, Support administration, certificate administration, assignment grading, reports/analytics, external integrations and unrelated modules were not implemented.

Verification on 2026-10-01: **287 frontend tests passed** (31 files); **410 Laravel tests / 1841 assertions passed**. Vite production build, Pint, `git diff --check`, admin route audit and migration status passed; all migrations remain applied. No dependency was added. Vite retains the pre-existing optional `fontaine` warning.

## Phase 13C operations administration

Admin now has permission-gated Orders, Payment Review, Review Moderation, Certificates and Support pages. Sales Support uses its own focused workspace and routes for Support, Orders and Payments; it does not inherit Content Manager or Admin-only certificate/review actions. The new responsive pages use Arabic/English labels, server pagination and only backend-supported filters. Orders display purchase-time item and package-course snapshots. A `paid` order is explicitly distinct from `completed`; the confirmed reconciliation action calls the existing server endpoint and refetches the order. Payment approval is confirmed, guarded against duplicate clicks and never creates frontend grants. Rejection collects the backend-required reason. Proofs, certificate PDFs and Support attachments use authenticated blob downloads, not storage URLs.

Review actions follow only `pending → published/rejected`, `published → hidden` and `hidden → published`; rejection/hiding require a 5–2000 character reason. Certificate revocation requires a reason and reissuance creates a **new** record, leaving the revoked record historical. Staff Support separates public replies from unmistakably private internal notes. Status, priority, category and assignment edits send the latest server `expected_updated_at`; a 422 stale-write error refetches the ticket and asks the staff member to review it, without retrying the mutation. The list exposes only backend filters (no unsupported text search).

The previous Support upload exception is traceable to Laravel `FilesystemAdapter::putFileAs()` calling `fopen('')` after an uploaded file's `getRealPath()` returned false. The browser FormData (`attachments[]`) and Axios request use the accepted multipart contract; the request had reached server storage. `SupportTicketMessageService` now checks that the PHP temporary file remains valid/readable, uses its actual temporary pathname when `realpath` is unavailable, and returns a 422 attachment error with transaction rollback if the file is gone. Regression tests cover both cases. The original browser session was unavailable during this phase, so successful live upload and whether a web-server-specific temp-directory problem remains require a repeat test. If 422 persists, inspect the **web PHP** `upload_tmp_dir`/`sys_temp_dir` and ensure the web-server identity can read/write that directory (for Laragon, a dedicated writable directory such as `C:/laragon/tmp`), then restart the web server. CLI PHP settings alone do not prove the web PHP configuration. No local PHP configuration was changed automatically.

`RolesAndPermissionsSeeder` calls `findOrCreate` for permissions and then `syncPermissions` for every built-in role. Rerunning it adds missing permission definitions but also replaces custom grants on those roles with the coded lists. No new permission is needed for Phase 13C, so the development database was not reseeded. If a later deployment needs this seeder, first compare/export role grants and run it only after deciding how custom grants should be preserved.

Live Admin/Sales Support workflows, including safe test payment approval, public review visibility, certificate revoke/reissue, Support internal-note isolation, stale-write conflict and real attachment upload, remain pending suitable authenticated disposable data. No meaningful development purchase, review, certificate or ticket was mutated solely for smoke testing. No migration or dependency was added. Assignment grading, Instructor Portal, analytics/reporting, refunds, coupons, gateways, live chat, notifications and unrelated modules remain out of scope.

Verification on 2026-10-01: 302 frontend tests passed (32 files); 412 Laravel tests / 1845 assertions passed. Vite production build, Pint, route audit, migration status and `git diff --check` passed. All existing migrations are applied. The existing optional `fontaine` warning remains non-blocking.

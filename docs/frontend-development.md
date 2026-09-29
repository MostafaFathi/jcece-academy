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

Phases 10–12B provide the public homepage, course and package catalogs/details, published course reviews, login, localized responsive navigation, dashboard shells, authenticated commerce pages, My Courses and the course learning player. The public application integrates these implemented endpoints:

- `GET /api/v1/categories`
- `GET /api/v1/courses` and `GET /api/v1/courses/{slug}`
- `GET /api/v1/courses/{slug}/reviews`
- `GET /api/v1/packages` and `GET /api/v1/packages/{slug}`
- the existing Sanctum session authentication endpoints

The public catalog persists supported search, filter, sort, and page values in URL query parameters. Catalog/cart prices expose configured backend currency; missing currency is shown explicitly and blocks new checkout. Money rendering preserves decimal strings without floating-point totals.

Student commerce routes live under `/student/cart`, `/student/checkout`, `/student/orders`, and `/student/orders/:id`, backed by `/api/v1/me/cart`, `/checkout`, `/orders`, order payments, and private proof download. Cart/auth/customer/payment data stays in memory. Only language preference uses localStorage and the non-sensitive checkout UUID uses sessionStorage to recover uncertain requests. See `frontend-api-contract.md` for exact retry, status, ownership, and upload behavior.

Public registration, password reset, and email verification endpoints are not implemented. Gateways, coupons, refunds, quizzes/assignments/certificates UI, review management, support-ticket UI, admin business pages, and unrelated business screens remain outside Phase 12B.

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

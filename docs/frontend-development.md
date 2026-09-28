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

Phases 10–12A provide the public homepage, course and package catalogs/details, published course reviews, login, localized responsive navigation, dashboard shells, and authenticated cart/checkout/orders/manual-payment/proof pages. The public application integrates these implemented endpoints:

- `GET /api/v1/categories`
- `GET /api/v1/courses` and `GET /api/v1/courses/{slug}`
- `GET /api/v1/courses/{slug}/reviews`
- `GET /api/v1/packages` and `GET /api/v1/packages/{slug}`
- the existing Sanctum session authentication endpoints

The public catalog persists supported search, filter, sort, and page values in URL query parameters. Catalog/cart prices expose configured backend currency; missing currency is shown explicitly and blocks new checkout. Money rendering preserves decimal strings without floating-point totals.

Student commerce routes live under `/student/cart`, `/student/checkout`, `/student/orders`, and `/student/orders/:id`, backed by `/api/v1/me/cart`, `/checkout`, `/orders`, order payments, and private proof download. Cart/auth/customer/payment data stays in memory. Only language preference uses localStorage and the non-sensitive checkout UUID uses sessionStorage to recover uncertain requests. See `frontend-api-contract.md` for exact retry, status, ownership, and upload behavior.

Public registration, password reset, and email verification endpoints are not implemented. Gateways, coupons, refunds, the learning player, quizzes/assignments/certificates UI, protected lesson downloads, admin commerce, and unrelated business screens remain outside Phase 12A.

Manual-payment account instructions still need a managed backend/configuration contract. No bank or wallet details are fabricated. A connected browser is required for the end-to-end desktop/mobile RTL smoke test; automated API/component tests do not replace that visual check. In this execution the browser inventory was empty, so the manual smoke test remains outstanding; no live order/payment was created or approved.

Lesson resources still expose `file_path` in the current API without a protected download endpoint. The public frontend never renders that field or creates a download link; this backend contract must be corrected before the student lesson player is implemented.

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

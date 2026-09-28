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

Phase 11 provides the public homepage, course and package catalogs, public detail pages, published course reviews, polished login, localized responsive navigation, and the existing access-aware dashboard shells. The public application integrates these implemented endpoints:

- `GET /api/v1/categories`
- `GET /api/v1/courses` and `GET /api/v1/courses/{slug}`
- `GET /api/v1/courses/{slug}/reviews`
- `GET /api/v1/packages` and `GET /api/v1/packages/{slug}`
- the existing Sanctum session authentication endpoints

The public catalog persists supported search, filter, sort, and page values in URL query parameters. The API does not currently expose a public currency field, so prices are displayed as numeric amounts without inventing a currency.

Public registration, password reset, and email verification endpoints are not implemented, so the frontend does not fabricate those flows. Checkout, orders, the student learning player, protected lesson downloads, and business administration screens remain outside Phase 11.

Lesson resources still expose `file_path` in the current API without a protected download endpoint. The public frontend never renders that field or creates a download link; this backend contract must be corrected before the student lesson player is implemented.

# JCEC Academy frontend development

The Vue 3 application is served by Laravel from the same origin. Keep API requests relative so Sanctum session and XSRF cookies work without cross-origin configuration.

## Local development

1. Configure the Laravel application and database as documented by the project environment.
2. Install dependencies with `composer install` and `npm install`.
3. With Laragon, start the site and run `npm run dev`; the current project URL is `http://jcec-academy.local`.
4. Without Laragon, run Laravel and Vite together with `composer run dev`, or run `php artisan serve` and `npm run dev` separately.
5. Open the Laravel URL, not Vite's asset-server URL.

Keep `APP_URL` aligned with the Laravel URL. The same-origin default works with `SESSION_DOMAIN=null`; `SANCTUM_STATEFUL_DOMAINS` must contain the browser host. In production, also use `SESSION_SECURE_COOKIE=true` over HTTPS.

Useful checks:

- `npm run test:frontend` runs the Vitest suite.
- `npm run build` creates production assets.
- `php artisan test --compact` runs backend tests.

## Production

Run `npm ci` followed by `npm run build` during deployment and serve Laravel's `public` directory through the web server. Use HTTPS and set `SESSION_SECURE_COOKIE=true`. Same-origin deployment is preferred; sibling subdomains additionally require matching `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, and credentialed CORS settings.

Laravel returns the SPA shell for frontend history routes. `/api`, `/sanctum`, `/up`, `/storage`, `/assets`, and `/build` are excluded from that fallback. The frontend uses `/sanctum/csrf-cookie` before session login and never stores tokens or sensitive session data in browser storage. Only the non-sensitive language preference is persisted.

## Current boundary

This phase provides authentication, access-aware layouts, localization, API utilities, and placeholder destinations. Business-module screens are intentionally not implemented. Lesson resources still expose `file_path` in the current API without a protected download endpoint; the frontend must not publish or work around that path until the backend contract is corrected.

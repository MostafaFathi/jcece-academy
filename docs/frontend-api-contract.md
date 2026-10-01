# JCEC Academy frontend API contract

Audited against the Laravel application on 2026-09-30 (Phase 12C assessment and certificate integration). This document describes implemented behavior only. The API prefix is `/api/v1`; the Sanctum CSRF initializer is the framework route `/sanctum/csrf-cookie`.

## 1. Client and authentication contract

The first-party Vue application uses Sanctum SPA session cookies, not personal access tokens. Axios defaults are in `resources/js/api/client.js`: JSON responses are requested and both `withCredentials` and `withXSRFToken` are enabled.

Login sequence:

1. `GET /sanctum/csrf-cookie`.
2. `POST /api/v1/auth/login` with `{ "email": "student@example.com", "password": "...", "remember": false }`.
3. Bootstrap the auth store from `GET /api/v1/auth/user`.
4. `POST /api/v1/auth/logout` to invalidate the session and rotate the CSRF token.

The login response and current-user response use this shape:

```json
{
  "data": {
    "id": 15,
    "name": "Example User",
    "email": "student@example.com",
    "phone": null,
    "avatar": null,
    "country": null,
    "city": null,
    "specialization": null,
    "status": "active",
    "roles": ["student"],
    "permissions": ["categories.view", "courses.view"],
    "instructor_profile": null
  }
}
```

Only active users may log in. Invalid, inactive, and blocked accounts receive the same generic `422` credential error. There is no registration, password-reset, email-verification, or bearer-token issuing endpoint yet.

### Local and production settings

- Recommended local model: load the SPA through `https://jcec-academy.local` and make relative API requests. Vite supplies trusted HTTPS development assets/HMR; the browser still calls the API from the Laravel origin. `SESSION_DOMAIN=null`, `SameSite=Lax`, and the `jcec-academy.local` Sanctum stateful entry are suitable. See `frontend-development.md` for the Laragon certificate configuration.
- The current default CORS configuration has `supports_credentials=false`. That is harmless for the recommended same-origin model. A standalone `http://localhost:5173` SPA is not supported safely until CORS is published/configured with that exact allowed origin and credentials enabled; wildcard origins must not be combined with credentialed requests.
- Production should use HTTPS, `SESSION_SECURE_COOKIE=true`, and relative same-origin requests where possible. If the SPA and API use sibling subdomains, set `SESSION_DOMAIN=.example.com`, include the exact SPA host in `SANCTUM_STATEFUL_DOMAINS`, and allow that exact origin with credentialed CORS.
- All `/api/*` failures are forced to JSON. CSRF protection remains enabled.

## 2. Common response and error rules

Single resources use `{ "data": { ... } }`; resource collections use `{ "data": [...] }`. Laravel paginators additionally return top-level `links` and `meta` objects. Clients must not infer pagination for unpaginated arrays.

Typical errors:

| Status | Meaning | Shape / client action |
|---|---|---|
| `401` | Missing/expired session | `{ "message": "Unauthenticated." }`; clear auth store and route to login. |
| `403` | Policy/permission/access failure | JSON `message`; do not retry as another UI action. |
| `404` | Missing model or deliberately hidden foreign-owned record | JSON `message`; ownership failures frequently use 404. |
| `419` | Missing/expired CSRF token | Refresh `/sanctum/csrf-cookie`, then retry once. |
| `422` | Validation or business-rule failure | `{ "message": "...", "errors": { "field": ["..."] } }`. Business conflicts currently also use 422, not 409. |
| `429` | Rate limited | Login is 5/minute, checkout/payment submission 10/minute, certificate verification 30/minute. |

Dates are Laravel ISO-8601 JSON strings with timezone, or `null`. Decimal database casts (money, scores, percentages) serialize as fixed-precision strings; do not use binary floating point for totals. Enum fields serialize to the lowercase values listed below. Nullable fields are intentionally `null`; conditional fields may be omitted entirely (for example quiz results before submission).

## 3. Access legend

- `Public`: no session.
- `Student/owner`: `auth:sanctum`, ownership plus the relevant enrollment/access rule.
- `CV/CC/CU/CD/CP`: `courses.view/create/update/delete/publish`.
- `PV/PC/PU/PD/PP`: equivalent package permissions.
- `CatV/CatC/CatU/CatD`: category permissions.
- `CurV/CurC/CurU/CurD`: curriculum permissions.
- `EV/EM`: `enrollments.view/manage`.
- `OV/OM`: `orders.view/manage`.
- `PayV/PayM`: `payments.view/manage`.
- `AV/AC/AU/AD/AP/AR`: assessment view/create/update/delete/publish/results permissions.
- `AsV/AsC/AsU/AsD/AsP/SubV/SubG`: assignment and submission permissions.
- `CertV/CertI/CertR`: certificate view/issue/revoke.
- `RevV/RevM`: review view/moderate.
- `SupV/SupM/SupR`: support ticket view/manage/reply.

Every `/me` and `/admin` route requires `auth:sanctum`. Policies remain authoritative; hiding frontend navigation is usability only.

## 4. Public and authentication inventory

| Method and URL | Access | Request / filters | Success |
|---|---|---|---|
| `GET /api/v1/categories` | Public | none | Active category collection, not paginated. |
| `GET /api/v1/categories/{category}` | Public | route ID | `CategoryResource`; 404 if unavailable. |
| `GET /api/v1/courses` | Public | `search, category, instructor, level, language, sort, per_page(1..100)`; only published/current | Paginated `CourseResource`, default 15. |
| `GET /api/v1/courses/{slug}` | Public | slug | `CourseResource` with public curriculum and rating summary. |
| `GET /api/v1/courses/{slug}/reviews` | Public | `per_page(1..100)` | Published reviews only, paginated default 15. |
| `GET /api/v1/packages` | Public | `search, type, sort, per_page(1..100)` | Paginated published packages, default 15. |
| `GET /api/v1/packages/{slug}` | Public | slug | Public package with course memberships. |
| `GET /api/v1/certificates/verify/{token}` | Public, 30/min | opaque verification token; JSON clients send `Accept: application/json` | Public verification resource; unknown token is `{data:{status:"unknown"}}` with 404. HTML browsers redirect to the public SPA. No private PDF path. |
| `GET /sanctum/csrf-cookie` | Public | credentials enabled | `204`, sets `XSRF-TOKEN`. |
| `POST /api/v1/auth/login` | Public, 5/min, CSRF | `email,password`; optional `remember` | `AuthenticatedUserResource`; session regenerated. |
| `GET /api/v1/auth/user` | Authenticated | none | Current profile, roles, effective permissions, optional instructor profile. |
| `POST /api/v1/auth/logout` | Authenticated, CSRF | none | `204`; invalidates session and rotates CSRF token. |

## 5. Student inventory

### Cart, checkout, orders, and payments

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/cart` | current user | `CartResource`; 201 when first creating the user's cart, otherwise 200. |
| `DELETE /api/v1/me/cart` | current user | Cleared `CartResource`. |
| `POST /api/v1/me/cart/items` | `purchasable_type=course|package`, `purchasable_id` | Updated `CartResource`; unavailable products are 422. The same type/ID is idempotently kept once, not rejected. Course/package overlap is not rejected. |
| `DELETE /api/v1/me/cart/items/{cartItem}` | owner-scoped cart item | Updated `CartResource`; foreign item is hidden/denied. |
| `POST /api/v1/me/checkout` | `idempotency_key(UUID), customer_name, customer_email, customer_phone`; optional `notes` | `MeOrderResource`, 201 on first request and 200 on idempotent replay; 10/min. |
| `GET /api/v1/me/orders` | owner | Paginated `MeOrderResource`, 15/page. |
| `GET /api/v1/me/orders/{order}` | owner | Order, items, package-course snapshots, payments. |
| `POST /api/v1/me/orders/{order}/payments` | owner; multipart `method`, optional `transaction_id`, required `payment_proof` PDF/JPEG/PNG; size from `payment_proof_max_kilobytes` on the order (default config 5120 KB) | `MePaymentResource`, 201; 10/min. |
| `GET /api/v1/me/payments/{payment}/proof` | payment/order owner | Authenticated binary download; never a public URL. |

#### Phase 12A commerce resource contract

`CourseResource`, `PublicPackageResource`, nested public package-course prices, and `CartResource` now add `currency`. It is the uppercase configured `JCEC_COMMERCE_CURRENCY`, using the same validation as checkout. Missing/invalid configuration produces `null`, never a guessed default. Checkout still rejects missing/invalid currency with 422 and preserves the cart. Order/payment currency is the historical snapshot, not the current catalog configuration.

Cart shape: `{data:{id,items,item_count,estimated_total,currency}}`. Money remains fixed two-decimal strings. Each item has `id,purchasable_type,purchasable_id,available,product`; a deleted product can be `null`. Product fields are `id,title,slug,price,thumbnail,access_duration_days`. Null access duration means no configured expiry. The estimated total excludes unavailable products; the UI requires removing unavailable items before a new checkout. There is no quantity/update endpoint. Package composition is not exposed by the cart; the UI does not reconstruct it from the public catalog.

Checkout accepts only required `idempotency_key` UUID, name (255), email (255), phone (50), and nullable order notes (2000). Server prices, currency, discount/tax totals, and access duration are snapshotted; cart items are deleted atomically. Direct purchases use Course duration and package purchases use Package duration. Package order snapshots include historical membership titles, including courses no longer in the public catalog. Idempotent lookup is user-scoped and occurs before the empty-cart check, so retrying the same UUID returns the original order after cart clearing. Neither cart addition nor checkout provisions access.

The order index is owner-scoped, 15/page, and does not load items/payments; detail and checkout responses load `items.package_courses` and `payments`. `MeOrderResource` adds the safe configured integer `payment_proof_max_kilobytes` for upload validation. It exposes no entitlement keys, provisioning reconciliation metadata, or idempotency key. Foreign order/detail and proof requests are 404; payment submission for a foreign order is 403. Backend ownership checks remain authoritative regardless of frontend roles.

Actual order statuses: `pending,awaiting_payment,paid,completed,cancelled,refunded`. Actual payment statuses: `pending_review,paid,rejected`. Payment methods: `bank_transfer,wallet,manual`. Payment `paid` means approved payment, but an order still marked `paid` does not confirm successful provisioning. The provisioning service sets the order to `completed` after all grants succeed atomically; completed records historical provisioning, not necessarily current access (duration may expire or access may later be revoked). No separate student reconciliation state is exposed, so the UI never invents one or infers provisioning from payment approval alone.

Payment payload has no notes, amount, or currency field. Amount/currency come from the full order total. Reference is optional, max 255; proof is required and server MIME/content/size validation is authoritative. Only pending/awaiting-payment orders accept submissions, and a second pending-review payment is blocked with 422. Rejection permits resubmission if the order is pending again. Upload receipt means review pending, not approved access. Safe payment fields include `proof_available,transaction_id,method,amount,currency,status,paid_at,approved_at,rejection_reason,created_at`. Proof bytes use the authenticated blob helper; no storage URL is constructed.

There are no bank/wallet account details or managed payment instructions in the current backend. The UI truthfully directs students to contact the academy before transferring money. A managed, authenticated payment-instructions contract remains a content requirement; no account data was fabricated.

The cart store owns server snapshots, loading/mutation/error flags, and count. Mutations are serialized and stale responses from a prior auth session are ignored. Checkout intent UUID alone is persisted in sessionStorage; customer/cart/order/payment/proof data is not persisted. An uncertain request retains its UUID and original in-memory payload, blocks cart changes, and offers same-intent retry. Across reload only UUID survives: required customer fields must be supplied again, but a committed order is still recovered by UUID. Keys clear on success, logout/account switch, or explicit new intent; an explicit new intent warns to inspect orders first. Upload outcomes without a definitive response require an order refresh before retrying.

Authenticated commerce frontend routes are `/student/cart`, `/student/checkout`, `/student/orders`, and `/student/orders/:id`. They use Phase 10 authentication guards without adding a student-role restriction that the commerce API does not impose. Guests purchasing from a catalog card/detail go to login with a router-generated local detail destination; no guest cart is created. Navigation includes Cart and My Orders. Translated enum labels do not change API enum values.

### Learning and progress

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/courses` | current user; includes inactive access | Paginated owned enrollments with surviving courses, 15/page. |
| `GET /api/v1/me/courses/{slug}` | active effective access | Enrollment/course access metadata. |
| `GET /api/v1/me/courses/{slug}/learn` | active effective access | Learning curriculum with lessons, resources, and progress. |
| `GET /api/v1/me/courses/{slug}/progress` | active effective access | Aggregate course progress. |
| `PATCH /api/v1/me/courses/{slug}/lessons/{lesson}/progress` | nested lesson + course access; `watched_seconds` and/or `last_position_seconds`, non-negative integers | `StudentLessonProgressResource`. |
| `POST /api/v1/me/courses/{slug}/lessons/{lesson}/complete` | nested lesson + course access | Completed `StudentLessonProgressResource`. |
| `GET /api/v1/me/courses/{slug}/lessons/{lesson}/resources/{resource}/download` | authenticated effective course access; scoped course/lesson/resource; published lesson in active section | Streamed private attachment; safe server filename. |

The course identifier is its slug; lessons/resources use IDs. Learning/progress/download endpoints use `CourseAccessService`, without a staff-role bypass. Missing/foreign nested IDs are 404; no access, expired/revoked grants and suspended enrollments are 403; guests are 401. Public previews retain preview content/video fields only and never contain resource records or private file links.

Enrollment responses contain `status,enrolled_at,completed_at,course,has_access,access_state,access_expires_at,is_lifetime,completed_lessons,total_lessons,progress_percentage,resume`. Course summaries now optionally include a safe `instructor` summary when loaded (My Courses and learning detail eagerly load it). `status` describes enrollment, not entitlement. `access_state` is authoritative: `active,expired,suspended,scheduled,revoked,unavailable`. Suspension takes precedence; a currently valid unrevoked grant means active; without one, future unrevoked grants mean scheduled, ended unrevoked grants mean expired, revoked-only grants mean revoked, and no grants mean unavailable. Only `has_access=true` enables continuation. `access_expires_at` is the latest currently valid finite grant expiry; it is null for lifetime or inactive access, not a reconstructed historical expiry. Never derive it from current catalog duration.

The learning response contains `course,enrollment_status,has_access,access_state,access_expires_at,is_lifetime,completed_lessons,total_lessons,progress_percentage,curriculum`. Curriculum contains active sections and published, non-deleted lessons only. Each lesson has actual `video,text,file,link` type, descriptive/content/video fields, safe resource metadata and its enrollment-specific progress. `/progress` returns `{data:{enrollment,lesson_progress}}`; the enrollment contains the same authoritative summary and resume contract as My Courses. Resume identifies the most recently interacted applicable lesson with section, progress status and `last_position_seconds`, otherwise the first applicable lesson; it is null for an empty course.

Progress counts/percentage come exclusively from `CourseProgressService`. No Vue completion calculation exists. `POST .../complete` is idempotent; there is **no incomplete/undo-complete endpoint**. PATCH accepts non-negative integer `watched_seconds` and/or `last_position_seconds`; video position must not exceed a configured duration. The player explicitly saves actual video position only, without converting skipped time into watched time. Mutations are serialized, then `/progress` refreshes both lesson records and the aggregate. My Courses fetches fresh data on re-entry. Quiz/assignment progress and certificate eligibility remain separate.

Frontend routes are `/student/courses` (`student.courses.index`) and `/learn/courses/:slug` (`student.courses.learn`), with `/student/learning` redirecting to My Courses. Both require authentication without an invented student-role restriction. Protected content renders only after learn and progress requests confirm access. Navigation rechecks access through `/progress`; subsequent protected 401/403/404 responses clear course content, progress, video and resources. Network failures do not optimistically mark lessons complete. No protected learning data is persisted in browser storage.

Text/content strings have no trusted rich-text/sanitizer contract and render escaped, preserving whitespace. Native video uses only the actual safe HTTP(S) `video_url`, restores server position and offers explicit save; provider/ID-only lessons display an unavailable message rather than fabricating an embed URL. Link lessons use the actual safe `video_url`; file lessons use the protected resource area. No new video provider or HTML integration was added.

### Quizzes

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/quizzes` | accessible published quizzes | Student quiz collection, not paginated. |
| `GET /api/v1/me/quizzes/{quiz}` | course access + availability | Student quiz; correct answers are not exposed. |
| `GET /api/v1/me/quizzes/{quiz}/attempts` | owner/course access | Attempt collection, not paginated. |
| `POST /api/v1/me/quizzes/{quiz}/attempts` | attempt limits/availability enforced | Attempt resource, 201. |
| `GET /api/v1/me/quiz-attempts/{attempt}` | owner | Current attempt state. |
| `GET /api/v1/me/quiz-attempts/{attempt}/result` | owner | Same resource; result fields are conditional on submission and quiz settings. |
| `PATCH /api/v1/me/quiz-attempts/{attempt}/answers` | owner; `answers[]: {question_id, option_ids[]}` | Updated attempt. |
| `POST /api/v1/me/quiz-attempts/{attempt}/submit` | owner; in-progress and not expired | Submitted/scored attempt. |

Phase 12C uses the published, currently available student list and filters exposed `course_id`/`lesson_id` for the learning player. Future/closed quizzes are omitted by this API; the UI does not invent a status for undiscoverable records. Starting an attempt returns an existing active attempt idempotently or creates a snapshot. The attempt resource supplies `started_at`, `expires_at`, status, selected option IDs and question/option snapshots. Saving sends exact option-ID sets; grading is server-side. Score/percentage/passed/earned points are omitted unless submitted and results enabled. Correct option IDs/explanations are omitted unless submitted and answer review enabled. The UI checks field presence and keeps unsent selections only in memory. Its countdown derives from server `expires_at` but does not decide server acceptance.

### Assignments

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/assignments` | course access + published/available | Student assignment collection, not paginated. |
| `GET /api/v1/me/assignments/{assignment}` | course access | Assignment and attachment metadata. |
| `GET /api/v1/me/assignment-attachments/{attachment}/download` | course access | Protected binary download. |
| `GET /api/v1/me/assignments/{assignment}/submissions` | owner | Submission attempts, not paginated. |
| `POST /api/v1/me/assignments/{assignment}/submissions` | access, availability, max attempts | Draft submission, 201. |
| `GET /api/v1/me/assignment-submissions/{submission}` | owner | Submission snapshot, files, and permitted grade/revision fields. |
| `PATCH /api/v1/me/assignment-submissions/{submission}` | owner/draft; optional `text_answer` max 100000 | Updated draft. |
| `POST /api/v1/me/assignment-submissions/{submission}/files` | owner/draft; multipart `files[]`, allowed office/PDF/image/ZIP/TXT, max count/size from config | Updated submission. |
| `DELETE /api/v1/me/assignment-submissions/{submission}/files/{file}` | nested owner/draft | Updated submission. |
| `GET /api/v1/me/assignment-submission-files/{file}/download` | owner | Protected binary download. |
| `POST /api/v1/me/assignment-submissions/{submission}/submit` | owner/draft; required content/type rules | Finalized submission. |

The assignment list is limited to published, available, accessible records; each includes `course_id` and optional `lesson_id`. Draft creation returns an active draft or creates the next attempt. PATCH saves text, multipart POST uploads `files[]`, and finalization is a distinct POST. `text`, `file`, and `text_and_file` requirements remain server-enforced. A revision-requested historical attempt stays read-only; a new draft receives a new number subject to backend limits/deadlines. Attachment and submission-file resources expose safe metadata only; bytes use authenticated downloads, never storage paths. Final grades and feedback are conditional on backend status.

### Certificates, reviews, and support

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/certificates` | owner | Paginated certificates, framework default 15. |
| `GET /api/v1/me/certificates/{certificate}` | owner | Certificate resource. |
| `GET /api/v1/me/certificates/{certificate}/download` | owner, issued PDF exists | Protected PDF download. |
| `GET /api/v1/me/courses/{slug}/certificate-eligibility` | current user | `{data:{eligible,reasons,progress,certificate_status}}`; status is the latest owned certificate's `issued`/`revoked` value or null. |
| `POST /api/v1/me/courses/{slug}/certificates` | eligible owner | Issued/idempotent certificate resource. |
| `GET /api/v1/me/reviews` | owner | Paginated reviews, default 15. |
| `GET /api/v1/me/courses/{slug}/review` | owner + course | Own review or 404. |
| `POST /api/v1/me/courses/{slug}/reviews` | effective access; `rating(1..5)`, optional `title,body` | Pending review. |
| `PATCH /api/v1/me/reviews/{review}` | owner + effective access; optional `rating,title,body` | Resubmitted pending review. |
| `GET /api/v1/me/support-tickets` | owner | Paginated tickets, 25/page. |
| `POST /api/v1/me/support-tickets` | `subject,category,body`; optional owned `related_order_id`, accessible `related_course_id`, multipart `attachments[]` | Ticket + first public message, 201. |
| `GET /api/v1/me/support-tickets/{ticket}` | owner-scoped | Student ticket resource; no internal activity. |
| `GET /api/v1/me/support-tickets/{ticket}/messages` | owner-scoped | Public messages only, paginated 25; internal notes excluded from query and count. |
| `POST /api/v1/me/support-tickets/{ticket}/messages` | owner; `body`, optional multipart `attachments[]` | Public reply, 201; closed tickets require reopen. |
| `POST /api/v1/me/support-tickets/{ticket}/reopen` | owner; workflow must permit `→ open` | Reopened ticket. |
| `GET /api/v1/me/support-ticket-attachments/{attachment}/download` | owner + public message only | Protected binary download; foreign/internal files return 404. |

Certificate owner resources include a safe `course_id` and a human-readable SPA `verification_url`; snapshot names/title/number/issue/revocation fields remain historical. `pdf_path` and `pdf_disk` are never exposed. The student list includes revoked records, and owner PDF download remains backend-authorized. Eligibility reasons are authoritative, not inferred from lesson progress. Issuance is idempotent for an existing issued certificate; revoked certificates require administrative reissue.

New PDFs encode `/certificates/verify/{token}` in the server-generated QR, served by the public SPA and backed by the JSON verification endpoint. Existing PDFs encode `/api/v1/certificates/verify/{token}`: HTML browser requests redirect to that SPA page, while JSON clients retain the original API response and 404 unknown-token behavior. The public page displays only `status`, `certificate_number`, `student_name`, `course_title`, and `issued_at`, and treats unknown tokens as a normal state.

## 6. Staff and administration inventory

All URLs below are under `/api/v1/admin` and require both a session and the stated policy permission. Admin has every permission. Content Manager owns content/assessment/certificate/review permissions; Sales Support owns enrollment/order/payment/support permissions. Instructor has limited course/curriculum visibility and assigned-course submission review/grade permissions.

Role-specific grouping of the implemented routes:

- Instructor: category read; course list/detail/create/update, with course edits and instructor assignment restricted to self-owned courses; curriculum section/lesson/resource read (no curriculum mutation permission); assigned-course submission list/detail/file download/grade/revision/correction. Curriculum read itself is permission-based, not owner-filtered.
- Content management: category/course/package CRUD, memberships and ordering, curriculum CRUD/ordering, quiz and assignment authoring/publication, assessment results, grading, certificates, and review moderation.
- Sales support: enrollment grants/revocation, order inspection/status/provisioning, payment inspection/review/proofs, and support ticket handling.
- Administration: every route in this section through the Admin role's complete permission set.

### Catalog and content management

Phase 13A.1 closes the prior admin contract gaps. User and instructor management have separate policies and safe resources. `users.manage` and `instructors.manage` are granted to Admin only by the role seeder; existing `users.view/update` and `instructors.view/update` retain their explicit role assignments. Content Manager can edit instructor profile fields under `instructors.update` but cannot create instructor accounts or edit name/email/status/password. Sales Support's existing `users.update` permits basic name/email updates only. Password, status, role assignment and user creation require `users.manage`. Instructor selection for Course forms uses `courses.create` or `courses.update`, without granting instructor administration. Backend authorization and validation remain authoritative.

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /dashboard-summary` | Any permitted metric | One small response with only authorized `total_categories`, `total_courses`, `published_courses`, `draft_courses`, `total_packages`, `total_users`, `total_students`, `total_instructors`. |
| `GET /users`, `GET /users/{id}` | `users.view` | Paginated index (default 25, max 100), `search`, `role`, `status`, `page`; safe `id,name,email,status,roles,created_at,updated_at`. |
| `POST /users` | `users.manage` | Required `name,email,password,password_confirmation,roles[]` (real role names except `instructor`); optional status; 201. |
| `PUT/PATCH /users/{id}` | `users.update` or `users.manage` | Basic name/email; password/status/roles require `users.manage`. Empty password is ignored; self-removal of Admin role or self-deactivation is rejected. |
| `GET /instructors`, `GET /instructors/{id}` | `instructors.view` | Role-filtered paginated index (default 25, max 100), `search`, `page`; safe account/profile fields. Email is returned only to `instructors.manage`. |
| `POST /instructors` | `instructors.manage` | Atomically creates User, instructor role and InstructorProfile; required `name,email,password,password_confirmation`; actual optional profile fields listed below; 201. |
| `PUT/PATCH /instructors/{id}` | `instructors.update` or `instructors.manage` | Profile changes allowed with `instructors.update`; name/email/status/password require `instructors.manage`. Empty password leaves hash unchanged. |
| `GET /instructor-options` | `courses.create` or `courses.update` | Minimal paginated/searchable options: `id,name,job_title`, default 25/max 100; only users with instructor role. Instructor-only callers receive only themselves. |

Instructor profile fields: `job_title`, `short_bio`, `bio`, `years_experience`, `specialties[]`, `linkedin_url`, `facebook_url`, `instagram_url`, `website_url`, `is_featured`. Existing users are not silently converted into instructors: only new account creation is supported here. No user/instructor delete endpoint was added. User and instructor resources never expose password hashes, tokens or authentication metadata. Existing `/auth/user` response remains unchanged.

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /categories`, `GET /categories/{id}` | CatV | Paginated index 25; category detail. |
| `POST /categories` | CatC | `parent_id,name,slug,description,image,icon,sort_order,is_active`; 201. |
| `PUT/PATCH /categories/{id}` | CatU | Same fields optional; updated resource. Parent may be changed, but self-parenting and descendant cycles return a normal 422 `parent_id` validation error. |
| `DELETE /categories/{id}` | CatD | 204, or 422 when domain dependencies prevent deletion. |
| `GET /courses`, `GET /courses/{id}` | CV | Index filters match public plus admin `status`; default 25. |
| `POST /courses` | CC | Course payload described below; 201. |
| `PUT/PATCH /courses/{id}` | CU | Partial course payload. |
| `DELETE /courses/{id}` | CD | 204 soft delete. |
| `GET /packages`, `GET /packages/{id}` | PV | Filtered/paginated index default 25; detail. |
| `POST /packages` | PC | Package payload; 201. |
| `PUT/PATCH /packages/{id}` | PU | Partial package payload. |
| `DELETE /packages/{id}` | PD | 204 soft delete. |
| `GET /packages/{package}/courses` | PV | Membership collection. |
| `POST /packages/{package}/courses` | PU | `course_id`, optional `sort_order,is_required`; 201. |
| `PATCH /packages/{package}/courses/{membership}` | PU | required `is_required`. |
| `DELETE /packages/{package}/courses/{membership}` | PU | 204. |
| `POST /packages/{package}/courses/reorder` | PU | ordered distinct `ids[]`; reordered collection. |

Course payload fields: `category_id,instructor_id,title,slug,level` required on create; optional `short_description,description,thumbnail,promo_video_url,language,duration_minutes,access_duration_days,price,compare_price,discount_starts_at,discount_ends_at,certificate_enabled,discussion_enabled,status,is_featured,published_at,learning_outcomes[],requirements[],target_audiences[],required_tools[]`.

Package payload fields: `title,slug,type,price` required on create; optional `description,thumbnail,compare_price,access_duration_days,is_sequential,status,published_at`. Admin package responses now include configured `currency`; list items include `course_count` via `withCount`, avoiding per-package detail requests. Index supports `search,type,sort,per_page,page`, but not a `status` filter. `access_duration_days=null` is lifetime; positive integers override duration. Creation/membership validation accepts any non-deleted course, while public package output filters out draft, hidden and future-unavailable courses. Membership mutations do not rewrite historical order-item snapshots or access grants.

### Curriculum

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /courses/{course}/sections`, `GET /courses/{course}/sections/{section}` | CurV | Section resources, unpaginated. |
| `POST /courses/{course}/sections` | CurC | `title`; optional `description,sort_order,is_active`; 201. |
| `PUT/PATCH /courses/{course}/sections/{section}` | CurU | Partial section payload. |
| `DELETE /courses/{course}/sections/{section}` | CurD | 204. |
| `POST /courses/{course}/sections/reorder` | CurU | ordered distinct `ids[]`. |
| `GET /sections/{section}/lessons`, `GET /sections/{section}/lessons/{lesson}` | CurV | Lesson resources, unpaginated. |
| `POST /sections/{section}/lessons` | CurC | `title,slug,type`; optional content/video/duration/preview/publish/order fields; 201. |
| `PUT/PATCH /sections/{section}/lessons/{lesson}` | CurU | Partial lesson payload. |
| `DELETE /sections/{section}/lessons/{lesson}` | CurD | 204. |
| `POST /sections/{section}/lessons/reorder` | CurU | ordered distinct `ids[]`. |
| `GET /lessons/{lesson}/resources`, `GET /lessons/{lesson}/resources/{resource}` | CurV | Lesson resource records, unpaginated. |
| `POST /lessons/{lesson}/resources` | CurC | `title,type`; optional `file_path,external_url,is_downloadable,sort_order`; 201. |
| `PUT/PATCH /lessons/{lesson}/resources/{resource}` | CurU | Partial resource payload. |
| `DELETE /lessons/{lesson}/resources/{resource}` | CurD | 204. |
| `POST /lessons/{lesson}/resources/reorder` | CurU | ordered distinct `ids[]`. |

Resource input remains metadata/reference management, not file upload: paths must be safe `lesson-resources/{relative-key}` references, at most 255 characters, without traversal, absolute paths, encoded/backslash paths or dot-directory segments. External URLs accept HTTP(S) only. All resource CRUD/reorder responses and nested curriculum resources omit raw file/storage metadata. Clients must not depend on reading the stored path back.

Phase 13B uses the section index with eager-loaded lessons/resources for the builder. Section deletion cascades its lessons/resources in the database; the UI requires confirmation and notes that learning records may be affected. Lesson deletion is soft. Lesson types are `text,video,file,link`; text is plain and escaped in the student player. `is_preview` and `is_published` are separate lesson flags and do not override course publication rules. All section/lesson/resource reorder operations require an exact permutation of current child IDs and return server order; cross-section moves are unsupported. Package reorder uses **membership IDs**, not course IDs. Admin resource creation can reference an already-provisioned private file but has no multipart upload endpoint or admin download endpoint; the Phase 13B browser UI deliberately creates links only and never submits a local path. The student protected download endpoint remains under `/api/v1/me` and requires effective access. A real Lesson Resource upload browser comparison with the Support `Path must not be empty` issue is therefore unavailable until a safe upload contract exists.

### Quizzes and assessments

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /courses/{course}/quizzes`, `GET /courses/{course}/quizzes/{quiz}` | AV | Admin quiz resources, unpaginated. |
| `POST /courses/{course}/quizzes` | AC | `title,passing_score`; optional lesson/description/instructions/time/max-attempts/shuffle/result/availability fields; 201. |
| `PUT/PATCH /courses/{course}/quizzes/{quiz}` | AU | Partial quiz payload. |
| `DELETE /courses/{course}/quizzes/{quiz}` | AD | Returns the archived/refreshed resource rather than 204. |
| `POST /quizzes/{quiz}/publication`, `DELETE /quizzes/{quiz}/publication` | AP | Publish/unpublish resource. |
| `GET /quizzes/{quiz}/questions`, `GET /quizzes/{quiz}/questions/{question}` | AV | Question/options resources. |
| `POST /quizzes/{quiz}/questions`, `PUT/PATCH /quizzes/{quiz}/questions/{question}` | AC/AU | `type,question_text,points,options[]`; optional explanation/order; options need `answer_text,is_correct`. |
| `DELETE /quizzes/{quiz}/questions/{question}` | AD | 204. |
| `POST /quizzes/{quiz}/questions/reorder` | AU | ordered distinct `ids[]`; 204. |
| `GET /quizzes/{quiz}/attempts`, `GET /quizzes/{quiz}/attempts/{attempt}` | AR | Paginated attempts 25; detailed attempt/questions/answers. |

### Assignments and grading

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /courses/{course}/assignments`, `GET /courses/{course}/assignments/{assignment}` | AsV | Assignment resources, unpaginated. |
| `POST /courses/{course}/assignments` | AsC | `title,submission_type,maximum_score`; optional lesson/description/instructions/passing/max-attempts/availability/due/late; 201. |
| `PUT/PATCH /courses/{course}/assignments/{assignment}` | AsU | Partial assignment payload. |
| `DELETE /courses/{course}/assignments/{assignment}` | AsD | Returns archived/refreshed resource. |
| `POST /assignments/{assignment}/publication`, `DELETE /assignments/{assignment}/publication` | AsP | Publish/unpublish resource. |
| `GET /assignments/{assignment}/attachments` | AsV | Attachment collection. |
| `POST /assignments/{assignment}/attachments` | AsU | multipart `file`, allowed office/PDF/image/ZIP/TXT; 201. |
| `DELETE /assignments/{assignment}/attachments/{attachment}` | AsU | 204. |
| `GET /assignment-attachments/{attachment}/download` | AsV | Protected binary. |
| `GET /assignments/{assignment}/submissions`, `GET /assignments/{assignment}/submissions/{submission}` | SubV; instructors scoped to assigned course | Submission resources. |
| `GET /assignment-submission-files/{file}/download` | SubV with submission scope | Protected binary. |
| `POST /assignment-submissions/{submission}/grade` | SubG + scope | `score`; optional `feedback`; graded resource. |
| `POST /assignment-submissions/{submission}/revision` | SubG + scope | required `feedback`; revised resource. |
| `POST /assignment-submissions/{submission}/grade-corrections` | SubG + scope | `score,reason`; optional `feedback`; corrected resource. |

### Sales support: enrollments, orders, and payments

| Endpoints | Permission | Payload / result |
|---|---|---|
| `POST /users/{user}/courses/{course}/access` | EM | optional `access_starts_at,access_expires_at`; grant resource, 201. |
| `DELETE /access-grants/{grant}` | EM | optional `revocation_reason`; revoked grant resource. |
| `GET /orders` | OV | filters `search,status,user_id,per_page(1..100)`; paginated default 25. |
| `GET /orders/{order}` | OV | Detailed admin order. |
| `PATCH /orders/{order}/status` | OM | `status` limited to service transition targets; updated order. |
| `POST /orders/{order}/provision-access` | OM | no body; idempotent access provisioning result/order. |
| `GET /payments` | PayV | filters `status,order_id,per_page`; paginated default 25. |
| `GET /payments/{payment}` | PayV | Detailed payment. |
| `GET /payments/{payment}/proof` | PayV | Protected binary. |
| `POST /payments/{payment}/approve` | PayM | no body; updated payment/access side effects. |
| `POST /payments/{payment}/reject` | PayM | required `rejection_reason` max 2000. |

### Certificates and reviews

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /certificates`, `GET /certificates/{certificate}` | CertV | Paginated index (default 15), detail. |
| `GET /certificates/{certificate}/download` | CertV | Protected PDF. |
| `GET /users/{user}/courses/{course}/certificate-eligibility` | CertI | Eligibility JSON. |
| `POST /users/{user}/courses/{course}/certificates` | CertI | Issued/idempotent certificate. |
| `POST /certificates/{certificate}/revoke` | CertR | required `reason` 5..2000. |
| `POST /certificates/{certificate}/reissue` | CertI | New active certificate where permitted. |
| `GET /course-reviews` | RevV | filters `status,course_id,user_id,per_page`; default status pending, paginated 25. |
| `GET /course-reviews/{review}` | RevV | Review plus moderation history. |
| `POST /course-reviews/{review}/publication` | RevM | no body. |
| `POST /course-reviews/{review}/rejection` | RevM | required `reason` 5..2000. |
| `POST /course-reviews/{review}/hiding` | RevM | required `reason` 5..2000. |

### Support tickets

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /support-tickets` | SupV | filters `status,category,priority,assigned_to,created_from,created_to,per_page`; paginated default 25. |
| `GET /support-tickets/{ticket}` | SupV | Admin ticket plus internal activity history. |
| `PATCH /support-tickets/{ticket}` | SupM | optional `category,priority,status,expected_updated_at`; workflow checked. |
| `PUT /support-tickets/{ticket}/assignment` | SupM | `assigned_to`; optional `expected_updated_at`; assignee must have SupM. |
| `GET /support-tickets/{ticket}/messages` | SupV | Public replies and internal notes, paginated 25. |
| `POST /support-tickets/{ticket}/messages` | SupR | `body`, optional `is_internal`, multipart `attachments[]`; 201. |
| `GET /support-ticket-attachments/{attachment}/download` | SupV | Protected public/internal attachment download. |

## 7. Roles and frontend navigation

| Role | Navigation/capabilities to expose |
|---|---|
| `student` | Public catalog plus owned learning, cart, checkout, orders/payments, quizzes, assignments, certificates, reviews, and support tickets. |
| `instructor` | Course list/create; edit only self-owned courses and assign only self as instructor; curriculum read (no writes); assigned-course assignment submission review/grade. No global quiz, certificate, review, commerce, or support management. |
| `content_manager` | Catalog, categories, packages, curriculum, quizzes, assignments, certificates, and review moderation. No commerce/support permissions by default. |
| `sales_support` | User/instructor lookup permissions, enrollment management, orders, payments, and support tickets. |
| `admin` | All effective permissions. |

Build navigation from the `permissions` returned by `/auth/user`, not role-name assumptions. Phase 13B tightened the course policy: an Instructor without an Admin/Content Manager role can update only their assigned course and set only their own `instructor_id`. Course/curriculum read remains permission-based; direct URL mutations remain subject to backend policies.

## 8. Enum values

- Roles: `student`, `instructor`, `content_manager`, `sales_support`, `admin`.
- User status: `active`, `inactive`, `blocked`.
- Course status: `draft`, `published`, `hidden`, `coming_soon`, `archived`; level: `beginner`, `intermediate`, `advanced`, `all_levels`.
- Package type: `package`, `learning_path`; package status: `draft`, `published`, `hidden`, `archived`.
- Lesson type: `video`, `text`, `file`, `link`; progress: `not_started`, `in_progress`, `completed`.
- Enrollment: `active`, `completed`, `suspended`; effective course access: `active`, `expired`, `suspended`, `scheduled`, `revoked`, `unavailable`.
- Order: `pending`, `awaiting_payment`, `paid`, `completed`, `cancelled`, `refunded`.
- Payment method: `bank_transfer`, `wallet`, `manual`; status: `pending_review`, `paid`, `rejected`.
- Quiz status: `draft`, `published`, `archived`; question type: `single_choice`, `multiple_choice`, `true_false`; attempt: `in_progress`, `submitted`, `expired`.
- Assignment status: `draft`, `published`, `archived`; submission type: `text`, `file`, `text_and_file`; submission status: `draft`, `submitted`, `revision_requested`, `graded`.
- Certificate: `issued`, `revoked`; review: `pending`, `published`, `rejected`, `hidden`.
- Support category: `general`, `technical`, `course_content`, `payment`, `enrollment`, `certificate`, `other`; priority: `low`, `normal`, `high`, `urgent`; status: `open`, `in_progress`, `waiting_for_student`, `resolved`, `closed`.

## 9. Upload and download integration

Use `multipart/form-data` for payment proof, assignment file, and support attachment uploads. Do not manually set the multipart boundary. For downloads, use the configured Axios client with `responseType: 'blob'`, read the filename from `Content-Disposition` where present, create a temporary object URL, trigger the browser download, and revoke the URL. Redirecting `window.location` to a protected download may work same-origin but gives poorer error handling.

Protected implementations exist for lesson resources, payment proofs, assignment attachments/submission files, certificate PDFs, and support attachments. They authorize every request and do not return raw private paths.

Phase 12B resolves the lesson-resource path leak: the shared allowlisted resource (including admin, nested curriculum and reorder responses) exposes only `id,title,type,download_available,external_url,is_downloadable,sort_order`. The model also hides file/storage fields and its unfiltered external URL on direct JSON serialization. `download_available` means an enabled, safely configured file reference, not a guarantee that legacy bytes have been provisioned. Missing bytes return 404. `external_url` is null for any file-backed record and for unsafe schemes, credentials, storage/private paths, repeated encoded equivalents or signed-storage URLs; safe external references are public references, not private file delivery.

Downloads use the dedicated local `lesson_resources` disk at `storage/app/private/lesson-resources`, with private visibility and URL serving disabled, regardless of the default disk. A stored reference `lesson-resources/worksheet.pdf` maps to private key `worksheet.pdf`. The resolved real path must remain inside that root (including symlink containment). Responses use `attachment; filename=lesson-resource-{id}.{allowlisted-extension-or-bin}`, `application/octet-stream`, `nosniff` and `Cache-Control: private, no-store`. The Vue adapter supplies only scoped IDs to the authenticated blob helper and immediately revokes its temporary object URL.

No lesson upload service or automatic legacy relocation was added. A trusted server/operator must provision existing resource bytes inside the dedicated private root; never copy them into `public`, the public disk or a public symlink. The current seeded worksheet reference has no corresponding private file, so live successful-download testing awaits actual course material. No legacy/public data was moved or deleted. Deployment must prevent public/private symlinks or previously published file copies; application authorization cannot revoke an independently hosted public copy.

## 10. Suggested Vue module boundaries (next phase)

Phase 9 originally proposed the following modules; Phases 10–12B use the existing `router`, `pages`, `i18n`, `api`, `stores`, `composables`, `layouts`, and `components` directories rather than introducing a new module hierarchy:

```text
resources/js/
  app/                 # app bootstrap, router, Pinia, locale/direction
  api/                 # Axios client and domain API adapters
  stores/              # auth, cart, notifications/UI state
  layouts/             # Public, Auth, Student, Admin, Instructor
  modules/
    catalog/ learning/ commerce/ assessments/
    assignments/ certificates/ reviews/ support/ admin/
  components/          # reusable presentational/form components
  locales/             # ar, en
```

Use Composition API and lazy route chunks. Set `document.documentElement.dir` to `rtl` for Arabic and `ltr` for English, with logical CSS properties where possible. Keep Burgundy `#6B1D32` and Yellow `#FFD21E` as design tokens. Plan mobile-first breakpoints for phones, tablets, and desktop; use separate layout shells rather than role conditionals scattered across pages.

Bootstrap is not installed. Tailwind CSS 4 and its Vite plugin are used by the implemented frontend; dependencies were not changed for Phase 12A.

## 11. Implemented versus planned

Implemented: all API routes inventoried above, cookie-session login/logout/current user, JSON API errors, role/permission exposure, and protected downloads. Vue foundation/authentication/layouts, public catalog/details/reviews display, Phase 12A commerce UI, Phase 12B learning UI, Phase 12C student quiz/assignment/certificate and public verification UI, and Phase 12D contextual student reviews/support tickets UI are implemented.

Not implemented/planned: external gateways, coupons, refund actions, instructor grading UI, unrelated business-module UI, public registration, password reset, email verification, student profile editing, lesson upload UI/service, email/SMS/push notifications, live chat, and external integrations. Admin users/instructors, category/course metadata, curriculum, packages, operations, and dashboard pages are implemented.

## 12. Phase 13C operational frontend contract

The Vue adapter `resources/js/api/admin-operations.js` integrates existing `/api/v1/admin` endpoints. Orders: `GET /orders`, `GET /orders/{id}`, `POST /orders/{id}/provision-access`. Payments: `GET /payments`, `GET /payments/{id}`, `GET /payments/{id}/proof`, `POST /payments/{id}/approve`, `POST /payments/{id}/reject`. Order listing supports `search,status,user_id,per_page`; payment listing supports `status,order_id,per_page`. Neither API supports a payment-status/date filter or client-selected sort on orders. Payment listing defaults to `pending_review` and server oldest-first. Rejection sends `rejection_reason`. Payment approval/provisioning remains atomic and backend-controlled; Vue does not write enrollments or grants. A paid order is not yet completed, and a completed historical order does not guarantee unexpired access today.

Reviews: `GET /course-reviews`, `GET /course-reviews/{id}`, `POST /course-reviews/{id}/publication`, `/rejection`, `/hiding`. List filters are `status,course_id,user_id,per_page`; default queue is pending. Exact moderation transitions: pending→published/rejected, published→hidden, hidden→published. Rejection/hiding send `reason` (5–2000 chars). Only published reviews enter the public course review collection/aggregates, calculated by the backend. The Admin detail exposes safe reviewer identity and moderation history; the frontend does not fetch private User records or calculate ratings.

Certificates: `GET /certificates`, `GET /certificates/{id}`, `GET /certificates/{id}/download`, `POST /certificates/{id}/revoke`, `/reissue`; list filters are `user_id,course_id,status`. Revocation sends `reason` (5–2000 chars). Reissuance of a revoked record returns a newly issued certificate; no arbitrary Admin issuance endpoint is used. Student/course/instructor names in responses are historical snapshots. The PDF uses authenticated blob download and verification uses the server URL. No private storage path is shown.

Support: `GET /support-tickets`, `GET /support-tickets/{id}`, `PATCH /support-tickets/{id}`, `PUT /support-tickets/{id}/assignment`, `GET/POST /support-tickets/{id}/messages`, `GET /support-ticket-attachments/{id}/download`. List filters are `status,category,priority,assigned_to,created_from,created_to,per_page`; there is no text search. Staff messages use multipart `body`, `is_internal=0|1`, `attachments[]`. Internal-note attachments remain staff-only. Ticket update and assignment send `expected_updated_at` from the latest response; stale 422 is surfaced and refetched, never automatically retried. Ticket status transitions are server-authoritative. Protected downloads use IDs, not private paths.

The Admin role has all permissions. Content Manager has review moderation and certificate view/issue/revoke but not orders/payments/support. Sales Support has orders/payment/support view+manage/reply and no review/certificate actions. Instructor has none of these operations. Vue route guards/navigation reflect those effective permissions, but server Policies remain authoritative for direct URLs. The existing seeder synchronizes configured permissions for each built-in role; review local custom grants before rerunning it. Phase 13C introduced no permissions, migration or dependency.

Support upload diagnosis: prior browser logs show `getRealPath()` produced false at Laravel file storage, which passed an empty path to `fopen`. The backend now falls back to the readable PHP temporary pathname and returns a validation error/rolls back if it disappeared. Browser confirmation remains pending because the former session was unavailable. If it still fails, inspect the web PHP temp-directory configuration and permissions, not only CLI PHP settings.

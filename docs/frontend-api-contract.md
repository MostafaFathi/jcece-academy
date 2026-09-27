# JCEC Academy frontend API contract

Audited against the Laravel application on 2026-09-27. This document describes implemented behavior only. The API prefix is `/api/v1`; the Sanctum CSRF initializer is the framework route `/sanctum/csrf-cookie`.

## 1. Client and authentication contract

The first-party Vue application must use Sanctum SPA session cookies, not personal access tokens. The prepared Axios defaults are in `resources/js/app.js`: JSON responses are requested and both `withCredentials` and `withXSRFToken` are enabled.

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

- Recommended local model: load the SPA through `http://jcec-academy.local` and make relative API requests. Vite supplies development assets/HMR; the browser still calls the API from the Laravel origin. `SESSION_DOMAIN=null`, `SameSite=Lax`, and the `jcec-academy.local` Sanctum stateful entry are suitable.
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
| `GET /api/v1/certificates/verify/{token}` | Public, 30/min | opaque verification token | Public verification resource; no private PDF path. |
| `GET /sanctum/csrf-cookie` | Public | credentials enabled | `204`, sets `XSRF-TOKEN`. |
| `POST /api/v1/auth/login` | Public, 5/min, CSRF | `email,password`; optional `remember` | `AuthenticatedUserResource`; session regenerated. |
| `GET /api/v1/auth/user` | Authenticated | none | Current profile, roles, effective permissions, optional instructor profile. |
| `POST /api/v1/auth/logout` | Authenticated, CSRF | none | `204`; invalidates session and rotates CSRF token. |

## 5. Student inventory

### Cart, checkout, orders, and payments

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/cart` | current user | `CartResource`. |
| `DELETE /api/v1/me/cart` | current user | Cleared `CartResource`. |
| `POST /api/v1/me/cart/items` | `purchasable_type=course|package`, `purchasable_id` | Updated `CartResource`; unavailable/duplicate/conflicting products are 422. |
| `DELETE /api/v1/me/cart/items/{cartItem}` | owner-scoped cart item | Updated `CartResource`; foreign item is hidden/denied. |
| `POST /api/v1/me/checkout` | `idempotency_key(UUID), customer_name, customer_email, customer_phone`; optional `notes` | `MeOrderResource`, 201 on first request and 200 on idempotent replay; 10/min. |
| `GET /api/v1/me/orders` | owner | Paginated `MeOrderResource`, 15/page. |
| `GET /api/v1/me/orders/{order}` | owner | Order, items, package-course snapshots, payments. |
| `POST /api/v1/me/orders/{order}/payments` | owner; multipart `method`, optional `transaction_id`, required `payment_proof` PDF/JPEG/PNG max configured 5 MB | `MePaymentResource`, 201; 10/min. |
| `GET /api/v1/me/payments/{payment}/proof` | payment/order owner | Authenticated binary download; never a public URL. |

### Learning and progress

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/courses` | active effective access | Paginated student enrollments, 15/page. |
| `GET /api/v1/me/courses/{slug}` | active effective access | Enrollment/course access metadata. |
| `GET /api/v1/me/courses/{slug}/learn` | active effective access | Learning curriculum with lessons, resources, and progress. |
| `GET /api/v1/me/courses/{slug}/progress` | active effective access | Aggregate course progress. |
| `PATCH /api/v1/me/courses/{slug}/lessons/{lesson}/progress` | nested lesson + course access; `watched_seconds` and/or `last_position_seconds`, non-negative integers | `StudentLessonProgressResource`. |
| `POST /api/v1/me/courses/{slug}/lessons/{lesson}/complete` | nested lesson + course access | Completed `StudentLessonProgressResource`. |

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

### Certificates, reviews, and support

| Method and URL | Policy / request | Success |
|---|---|---|
| `GET /api/v1/me/certificates` | owner | Paginated certificates, framework default 15. |
| `GET /api/v1/me/certificates/{certificate}` | owner | Certificate resource. |
| `GET /api/v1/me/certificates/{certificate}/download` | owner, issued PDF exists | Protected PDF download. |
| `GET /api/v1/me/courses/{slug}/certificate-eligibility` | course access | `{data:{eligible,reasons,...}}`. |
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

## 6. Staff and administration inventory

All URLs below are under `/api/v1/admin` and require both a session and the stated policy permission. Admin has every permission. Content Manager owns content/assessment/certificate/review permissions; Sales Support owns enrollment/order/payment/support permissions. Instructor has limited course/curriculum visibility and assigned-course submission review/grade permissions.

Role-specific grouping of the implemented routes:

- Instructor: category read; course list/detail/create/update; curriculum section/lesson/resource read; assigned-course submission list/detail/file download/grade/revision/correction. The current course create/update policy is permission-based and not instructor-assignment-scoped.
- Content management: category/course/package CRUD, memberships and ordering, curriculum CRUD/ordering, quiz and assignment authoring/publication, assessment results, grading, certificates, and review moderation.
- Sales support: enrollment grants/revocation, order inspection/status/provisioning, payment inspection/review/proofs, and support ticket handling.
- Administration: every route in this section through the Admin role's complete permission set.

### Catalog and content management

| Endpoints | Permission | Payload / result |
|---|---|---|
| `GET /categories`, `GET /categories/{id}` | CatV | Paginated index 25; category detail. |
| `POST /categories` | CatC | `parent_id,name,slug,description,image,icon,sort_order,is_active`; 201. |
| `PUT/PATCH /categories/{id}` | CatU | Same fields optional; updated resource. |
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

Package payload fields: `title,slug,type,price` required on create; optional `description,thumbnail,compare_price,access_duration_days,is_sequential,status,published_at`.

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
| `instructor` | Course list/create/update currently allowed by permissions; curriculum read; assigned-course assignment submission review/grade. No global quiz, certificate, review, commerce, or support management. |
| `content_manager` | Catalog, categories, packages, curriculum, quizzes, assignments, certificates, and review moderation. No commerce/support permissions by default. |
| `sales_support` | User/instructor lookup permissions, enrollment management, orders, payments, and support tickets. |
| `admin` | All effective permissions. |

Build navigation from the `permissions` returned by `/auth/user`, not role-name assumptions. Important current limitation: instructor course update/create permissions are global at the policy layer; there is no assigned-course restriction for course editing. Do not imply a narrower frontend security boundary.

## 8. Enum values

- Roles: `student`, `instructor`, `content_manager`, `sales_support`, `admin`.
- User status: `active`, `inactive`, `blocked`.
- Course status: `draft`, `published`, `hidden`, `coming_soon`, `archived`; level: `beginner`, `intermediate`, `advanced`, `all_levels`.
- Package type: `package`, `learning_path`; package status: `draft`, `published`, `hidden`, `archived`.
- Lesson type: `video`, `text`, `file`, `link`; progress: `not_started`, `in_progress`, `completed`.
- Order: `pending`, `awaiting_payment`, `paid`, `completed`, `cancelled`, `refunded`.
- Payment method: `bank_transfer`, `wallet`, `manual`; status: `pending_review`, `paid`, `rejected`.
- Quiz status: `draft`, `published`, `archived`; question type: `single_choice`, `multiple_choice`, `true_false`; attempt: `in_progress`, `submitted`, `expired`.
- Assignment status: `draft`, `published`, `archived`; submission type: `text`, `file`, `text_and_file`; submission status: `draft`, `submitted`, `revision_requested`, `graded`.
- Certificate: `issued`, `revoked`; review: `pending`, `published`, `rejected`, `hidden`.
- Support category: `general`, `technical`, `course_content`, `payment`, `enrollment`, `certificate`, `other`; priority: `low`, `normal`, `high`, `urgent`; status: `open`, `in_progress`, `waiting_for_student`, `resolved`, `closed`.

## 9. Upload and download integration

Use `multipart/form-data` for payment proof, assignment file, and support attachment uploads. Do not manually set the multipart boundary. For downloads, use the configured Axios client with `responseType: 'blob'`, read the filename from `Content-Disposition` where present, create a temporary object URL, trigger the browser download, and revoke the URL. Redirecting `window.location` to a protected download may work same-origin but gives poorer error handling.

Protected implementations currently exist for payment proofs, assignment attachments/submission files, certificate PDFs, and support attachments. They authorize every request and do not return raw private paths.

Known blocker: lesson resource records currently expose the stored `file_path` field through `LessonResourceResource`, and there is no dedicated protected lesson-resource download endpoint or upload service. Treat `external_url` as a normal link, but do not ship private lesson-file downloads until that backend contract is redesigned. Phase 9 does not silently convert or relocate those existing paths.

## 10. Suggested Vue module boundaries (next phase)

No pages or layouts were created in Phase 9. Recommended modules:

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

Bootstrap is not installed. Tailwind CSS 4 and its Vite plugin are already installed, so the next phase should use the existing Tailwind stack unless the project explicitly approves a dependency change.

## 11. Implemented versus planned

Implemented: all routes inventoried above, cookie-session login/logout/current user, JSON API errors, role/permission exposure, and protected downloads except lesson resources.

Not implemented/planned: Vue pages/layouts, public registration, password reset, email verification, profile editing, user/instructor administration endpoints, private lesson-file delivery, email/SMS/push notifications, live chat, and external integrations.

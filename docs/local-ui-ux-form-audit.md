# Phase 16.1 — Local UI/UX form-controls audit

This is a local source-and-test audit, **not staging acceptance**. The scan found **50 `<form>` elements in 44 Vue files**. Action panels without `<form>` (package course membership, ticket assignment, quiz/assignment read-only instructor views) were also inspected. The signed-out browser could only exercise public pages; no administrator or instructor credentials were used.

The audit found **26 inappropriate or unnecessarily technical short-text controls** in the changed screens: 11 typed relationship IDs, 12 decimal/percentage inputs, two protected-video infrastructure inputs, and one phone input. Zero boolean inputs and zero finite-enum inputs needed conversion to a dropdown or switch; those inspected were already constrained. Five existing end-date controls gained a start-date minimum.

## Inventory by screen

| Area | Forms / fields inspected | Finding |
| --- | --- | --- |
| Public/auth | Login, register, forgot/reset password, catalog/course/package filters, contact | Email/password/search/long message controls appropriate; select filters use supported values. Contact has field-level errors. |
| Student | Cart coupon, checkout customer details, manual-payment method/reference/proof, course review, support creation/reply, quiz answers, assignment submission, profile/password/avatar/language | Related support course/order are named selects but only page-by-page, not server-searchable. Profile phone was changed to `tel`. Uploads use file inputs. |
| Admin taxonomy/users | Category, user, instructor create/edit and list filters | Parent category, role, user status and instructor status are constrained controls. Names, slugs, biographies and specialties remain authored text. Category image/icon are unresolved string references, not a browser upload. |
| Admin course/curriculum | Course create/edit, sections, lessons, lesson resources, course FAQ, certificate requirements | Course category/instructor and lesson type are selects; paid video setup needed clarification. Course money and promotion inputs corrected. Lesson resource upload already uses a file input. Certificate quiz/assignments are constrained controls. |
| Admin commerce | Package create/edit and course membership, coupon, orders/payments, refund | Package course membership was already searchable. Coupon product IDs were replaced by named multi-selection. Payment order filter and refund amount corrected. |
| Admin operations/content | Certificates, reviews, support list/detail, audit, policy/site content and FAQ, reports/exports | Typed relational filters were replaced by name-based lookup. Status/format filters were already selects/buttons. Site/policy/FAQ body remains long text. |
| Instructor | Course metadata, course/submission filters, grade/revision/correction | Grade and correction score changed to bounded numeric controls. Instructor quiz definitions are read-only in this portal; the backend quiz/question and assignment authoring APIs have no corresponding Admin form to audit. |
| Sales support | Order/payment/support lists and detail actions (shared operational pages) | Assignee now uses an authorized searchable lookup. Other status/category/priority fields were already selects. |

## Changed controls

| Screen | Field | Old control | New control | Reason |
| --- | --- | --- | --- | --- |
| Lesson editor | `video_provider`, `video_id` | Unexplained free-text infrastructure fields | Removed from ordinary editing; existing values preserved on edit | No configured protected-video provider or valid selectable provider set exists. Avoid implying that entering an ID enables playback. |
| Lesson editor | `protected_video_asset_key` | Prominent technical text field | Operator setup disclosure with explicit unconfigured-playback warning; open for a new paid video | Backend still requires an operator-provisioned reference. It is not a working upload. |
| Lesson editor | `video_url` | Shown for every video | Shown for public-preview video only, separately from protected paid video | Public URL must not be mistaken for protected playback. Link lessons still use URL. |
| Coupon editor | `product_ids` | Comma-separated ID text | Searchable, paginated named multi-select; selected labels hydrated on edit | Operators should select real courses/packages, not type database IDs. |
| Coupon editor | `discount_value` | Generic decimal text | Numeric amount or percentage (0.01–100% for percentage), with contextual label | Prevent obviously invalid input and show unit. |
| Coupon editor | `minimum_order_amount` | Generic decimal text | Non-negative money number | Match numeric backend validation. |
| Course editor | `price`, `compare_price`, `promotional_price` | Decimal-pattern text | Non-negative `number` with 0.01 step and currency label | Match monetary semantics while serializing two-decimal strings to the existing API. |
| Package editor | `price`, `compare_price`, `promotional_price` | Decimal-pattern text | Non-negative `number` with 0.01 step and currency label | Same as course pricing. |
| Package editor | `sequential_completion_percentage` | Decimal-pattern text | Number 0.01–100, step 0.01 | The field is a percentage and already conditional on sequential mode. |
| Refund panel | `amount` | Generic decimal text | Number 0.01–refundable balance, step 0.01, order currency shown | Limit invalid refund requests; existing two-decimal API payload retained. |
| Instructor grading | Grade/correction score | Generic decimal text | Number 0–maximum score, step 0.01 | Score bounds visible before submission; API still receives decimal string. |
| Profile | Phone | Generic text | Telephone input | Correct keyboard/semantic input for phone. |
| Audit | Actor ID | Typed numeric ID | Searchable user selector | Display person name, not ID. |
| Certificates list | Course ID, student ID | Typed numeric IDs | Searchable course and student selectors | Select meaningful names. |
| Reviews list | Course ID | Typed numeric ID | Searchable course selector | Select meaningful title. |
| Reports | Course, package, instructor IDs | Typed numeric IDs | Searchable role-scoped selectors | Filter by named entity while retaining IDs in report request. |
| Payments list | Order ID | Typed numeric ID | Searchable order-number selector | Select an order by recognizable number. |
| Support list/detail | Assignee ID | Typed numeric ID | Searchable eligible-person selector | Only users with effective `support_tickets.manage` permission appear. |
| Coupon/course/package/ticket/report dates | End date/time | Native picker without lower bound | Native picker with start date/time minimum | Prevent reversed ranges before server validation. |
| Certificate requirements | Quiz/assignment status | Raw enum key beside title | Localized Arabic/English status | Clarify draft/published/archived options. |
| Refund initiation/completion/rejection | Confirmation | Generic or consequence-free prompt | Amount, currency, access consequence and manual-processing effect stated | Make consequential action explicit. |

`AsyncResourceSelect.vue` is the sole new shared UI control. It uses existing paginated lookup APIs for courses, packages, instructors, users and orders, including single-record hydration for preselected values. Coupon scope changes clear IDs from the previous product type. Backend relationship validation remains authoritative.

The **only new backend endpoint** is `GET /api/v1/admin/support-ticket-assignees`. It requires `support_tickets.manage`, queries effective permission (role or direct grant), limits output to `id` and `name`, and supports bounded search, page, and exact-ID lookup. No schema, enum or business-rule change was made.

## Text inputs intentionally retained

- Names, titles, slugs, short summaries, coupon codes, reference numbers, professional titles, free-text search, and support subject/reason are genuine authored short text. Slugs/codes keep server validation.
- Course `language` remains a short text code because backend validation accepts any string up to 10 characters; restricting the UI to `ar/en` would invent a business rule. This remains a UX decision for a later language-policy phase.
- Report currency remains a three-letter ISO-style text filter because backend accepts any uppercase three-letter code. The UI now constrains length/pattern and uppercases before request.
- Category image/icon and course/package thumbnail values remain URL/path or icon-name strings because no matching upload contract exists. These are still technical and should be redesigned only with an approved media workflow.
- The protected-video asset reference remains operator-supplied text because no provider/upload integration is configured. The UI warns that publication does not make paid video playable.

## Remaining gaps / verification boundary

- The student support form's related course/order lists paginate but cannot search across all owned records. A scoped search contract is needed to make those relationship selectors fully searchable without exposing unrelated records.
- Category parent selection and some course-specific quiz/assignment lists are paginated or bounded but not searchable. They can become unwieldy with large real data.
- Not every legacy form associates every server-side field error with `aria-describedby`; this phase added field feedback to changed coupon/refund fields, not a full accessibility certification.
- Admin quiz/question and assignment authoring forms are absent from the Vue application. This audit did not create a new product feature to fill that gap.
- Protected paid video remains provider-unconfigured; video upload/playback acceptance is impossible in the current local environment.
- The public browser walkthrough verified contact form presence/labels, Arabic RTL and English LTR, and no horizontal overflow at 320, 390, 768 and 1280 px on the contact page. Admin/instructor/sales-support workflow could not be walked in the browser because the current session is redirected to login. Source/tests are not a substitute for an authenticated local walkthrough.
- Browser/device accessibility and touch behavior, plus actual upload flows, remain unverified.

## Verification

Focused Vue tests cover the searchable selector, coupon product selection, lesson-video conditions, money input serialization and grading. Backend tests cover support-assignee permission scoping, minimal response, search and filter validation. Full Vue suite: **357 passing tests in 42 files**. Full Laravel suite: **518 passing tests, 2511 assertions**. Vite build, Pint (`--dirty --format agent`), and `git diff --check` passed. Vite only emitted the existing optional `fontaine` warning.

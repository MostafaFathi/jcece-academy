# Unified notifications and announcements — implementation report

Status: **INCOMPLETE**. The implementation and automated checks are in place; the MySQL/concurrency, real mail/worker and full integration acceptance checks below remain outstanding. No staging or production environment was changed. No claim of real SMTP, browser push or WebSocket validation is made.

Branch: `feature/unified-notifications` (local development branch). The checkout is `/workspace/scratch/8d7b7f51b6f2/jcece-academy`; the Windows Laragon installation at `C:\laragon\www\jcec-academy` was not accessible from this runtime.

## Discovery and preserved work

Reviewed AGENTS.md, relevant Laravel, testing and Tailwind skills, authentication/Sanctum, the five system roles, permission registry, resource policies, effective access grants, commerce/refunds, certificates, assessments/grading, support, email outbox, queue/scheduler/audit, Vue layouts/router/stores/API clients/translations and messaging. Installed runtime used Laravel 13.32.0 and PHP 8.4.12.

Remote `main` was verified at `7e0022676a30835f6f7510bd10980009d61899cf`. Messaging was actually present in this checkout, including the implementation history after the reported `c2653fd` work and the mobile fullscreen changes at `1645b76`. The new branch preserves those changes. Messaging models/services/policies remain the authorities; no administrator messaging bypass was added. No main-branch commit, deployment or production/staging database migration was performed.

## Architecture and boundaries

`NotificationWorkflowObserver` translates existing Eloquent workflow transitions into stable `PlatformNotificationEvent` events. Laravel discovers `PersistPlatformNotification`; `NotificationRecipientResolver` narrows candidate queries by permission, ownership and course/ticket scope and then applies the existing policies and access services. Users are unique candidates even with multiple roles. Registry identifiers and Arabic/English copy live in `config/platform_notifications.php`.

`UnifiedNotificationService` records a unique SHA-256 event receipt, native Laravel database notification and optional email delivery inside the source transaction. A recipient row lock serializes concurrent recipient writes; database unique constraints enforce idempotency. The native DatabaseChannel is used directly so mail fakes do not suppress transactional inbox persistence. Queued external delivery runs after commit. A source rollback removes its inbox/outbox changes.

`SendPlatformNotification` uses a delivery lock, terminal status checks, current recipient status/preferences and target authorization, five attempts and 60/300/900/1800 second backoff. Delivery status and sanitized exception class are recorded. A stale-delivery sweep recovers missed dispatches. SMTP delivery is at-least-once: a transport success followed by a process crash before marking sent can still produce a duplicate email. It is not advertised as exactly-once external delivery.

The existing centralized `TransactionalDeliveryService` continues owning financial/certificate emails and documents. The unified service deliberately avoids sending a second email for those events. The management API now monitors both outboxes and retries the existing outbox through the same shared service used by the order-specific retry endpoint. Essential transactional email settings are shown as mandatory. Existing welcome/financial document mail payloads are preserved.

The bell polls every 30 seconds while visible and online; course messaging retains its existing bounded polling. No new package was installed. Optional browser push and a new notification WebSocket channel are not enabled.

## Implemented event catalog

In-app defaults are enabled except where users disable optional types. Mandatory in-app and mandatory email cannot be disabled by preferences or policy defaults. Daily digest is generated as an email summary, not a second set of transaction alerts. Financial emails use the preserved adapter described above.

| Stable identifier | Category | Priority | In-app | Email | English / Arabic title |
| --- | --- | --- | --- | --- | --- |
| `enrollment.activated` | learning | high | Required | Optional, disabled | Course access activated / تم تفعيل الوصول للدورة |
| `enrollment.suspended` | learning | high | Required | Optional, disabled | Course access suspended / تم تعليق الوصول للدورة |
| `enrollment.expired` | learning | high | Required | Optional, disabled | Course access expired / انتهت صلاحية الدورة |
| `enrollment.expiring` | learning | normal | Optional, enabled | Optional, enabled | Course access expires soon / صلاحية الدورة تنتهي قريبًا |
| `enrollment.student_joined` | learning | normal | Optional, enabled | Optional, disabled | A student joined your course / انضم طالب إلى دورتك |
| `lesson.published` | learning | normal | Optional, enabled | Optional, disabled | New course lesson / درس جديد في الدورة |
| `quiz.available` | assessment | normal | Optional, enabled | Optional, disabled | Quiz is available / الاختبار متاح |
| `quiz.result` | assessment | high | Required | Required | Quiz result available / نتيجة الاختبار متاحة |
| `quiz.reminder` | assessment | normal | Optional, enabled | Optional, enabled | Quiz closes soon / موعد إغلاق الاختبار قريب |
| `assignment.available` | assessment | normal | Optional, enabled | Optional, disabled | Assignment is available / الواجب متاح |
| `assignment.submitted` | assessment | normal | Optional, enabled | Optional, enabled | Assignment needs grading / واجب بانتظار التصحيح |
| `assignment.graded` | assessment | high | Required | Required | Assignment reviewed / تم تصحيح الواجب |
| `assignment.revision` | assessment | high | Required | Required | Assignment revision requested / طلب تعديل الواجب |
| `assignment.reminder` | assessment | normal | Optional, enabled | Optional, enabled | Assignment is due soon / موعد تسليم الواجب قريب |
| `certificate.eligible` | certificate | normal | Optional, enabled | Optional, disabled | Certificate requirements met / استوفيت متطلبات الشهادة |
| `certificate.requested` | certificate | normal | Optional, enabled | Optional, disabled | Certificate approval requested / طلب موافقة على شهادة |
| `certificate.approved` | certificate | high | Required | Required, existing transactional outbox | Certificate approved / تمت الموافقة على الشهادة |
| `certificate.issued` | certificate | high | Required | Required, existing transactional outbox | Certificate issued / تم إصدار الشهادة |
| `certificate.revoked` | certificate | high | Required | Required | Certificate revoked / تم إلغاء الشهادة |
| `payment.pending` | commerce | normal | Optional, enabled | Optional, disabled | Payment needs review / دفعة بانتظار المراجعة |
| `payment.approved` | commerce | high | Required | Required, existing transactional outbox | Payment approved / تمت الموافقة على الدفع |
| `payment.rejected` | commerce | high | Required | Required, existing transactional outbox | Payment rejected / تم رفض الدفع |
| `refund.updated` | commerce | high | Required | Required, existing transactional outbox | Refund updated / تحديث استرداد المبلغ |
| `refund.pending` | commerce | normal | Optional, enabled | Optional, disabled | Refund needs processing / استرداد بانتظار المعالجة |
| `order.updated` | commerce | high | Required | Required, existing transactional outbox | Order updated / تحديث الطلب |
| `message.received` | messaging | normal | Optional, enabled | Optional, disabled | New course messages / رسائل جديدة في الدورة |
| `support.escalated` | support | high | Optional, enabled | Optional, enabled | Important support escalation / تصعيد مهم لتذكرة دعم |
| `support.created` | support | normal | Optional, enabled | Optional, enabled | New support ticket / تذكرة دعم جديدة |
| `support.assigned` | support | normal | Optional, enabled | Optional, enabled | Support ticket assigned / تم إسناد تذكرة دعم |
| `support.updated` | support | normal | Optional, enabled | Optional, enabled | Support ticket updated / تم تحديث تذكرة الدعم |
| `support.reply` | support | normal | Optional, enabled | Optional, enabled | New support reply / رد جديد على تذكرة الدعم |
| `support.overdue` | support | normal | Optional, enabled | Optional, enabled | Support ticket is overdue / تذكرة دعم متأخرة |
| `review.pending` | content | normal | Optional, enabled | Optional, disabled | Course review needs attention / تقييم دورة يحتاج مراجعة |
| `course.updated` | content | normal | Optional, enabled | Optional, disabled | Course content updated / تم تحديث محتوى الدورة |
| `course.assigned` | content | normal | Optional, enabled | Optional, disabled | Course instructor assignment changed / تغيير إسناد مدرب الدورة |
| `video.failed` | content | normal | Optional, enabled | Optional, enabled | Course video processing failed / فشلت معالجة فيديو الدورة |
| `security.permissions_changed` | security | high | Required | Optional, disabled | Role permissions changed / تغيرت صلاحيات أحد الأدوار |
| `security.changed` | security | high | Required | Required | Account security settings changed / تغيرت إعدادات أمان الحساب |
| `delivery.failed` | system | high | Required | Optional, disabled | Notification delivery failed / فشل إرسال إشعار |
| `system.critical` | system | high | Required | Optional, disabled | Critical platform event / حدث حرج في المنصة |
| `announcement.published` | announcement | normal | Optional, enabled | Optional, disabled | New announcement / إعلان جديد |
| `digest.daily` | system | normal | Optional, enabled | Optional, enabled | Daily notification digest / ملخص الإشعارات اليومي |

## Role and scope matrix

Roles below describe the repository defaults; effective permissions and resource policies control actual delivery.

| Recipient | Covered workflows | Required scope |
| --- | --- | --- |
| Student / resource owner | Access activation, suspension, expiry and reminders; published lessons/assessments; quiz results; assignment grading/revision/deadlines; certificate eligibility/approval/issue/revocation; payment/refund/order updates; messages; customer-visible support updates; targeted announcements | Own enrollment/order/payment/submission/ticket/certificate; effective current course access for learning/message targets; current conversation membership and pinned instructor; announcement snapshot plus current eligibility |
| Instructor | Eligible students joining; new assignment submissions; course assignment/content/video problems; reviews needing attention; participant messages/groups; targeted announcements | Assigned course and actual view/grade/curriculum/message permissions; existing submission and conversation policies; unrelated instructors excluded |
| Administrator | Payments/refunds awaiting action, certificate approval requests, urgent support, delivery failures, failed queue jobs, security/account/role permission changes, content/moderation, announcements | Corresponding payments/refunds/certificate/support/content permissions; `notifications.manage` for failures/management; `notifications.security.view` for security recipients; no private-message access inherited from administrator role |
| Content manager | Course creation/publication/content changes, lesson content changes, video failures, review moderation, permitted assessment grading/certificate review and announcements | Existing course/curriculum/review/submission policies and permissions; course audiences require view scope |
| Sales & support | New/assigned tickets, customer replies, status changes, overdue/high-priority tickets, payment follow-up | Ticket assignment where present plus view/reply/manage permissions; actual payment/order permissions |
| Multiple roles | Union of eligible responsibilities | One receipt and one notification per recipient/event; role membership does not multiply delivery |

Existing roles with customized permissions are not reset. Newly seeded content-manager roles receive announcement permissions; existing custom roles need an explicit grant through the existing role-management UI. Existing administrators receive newly introduced notification/announcement permission keys on seeding. Messaging permissions are not automatically granted.

## Database changes

One additive migration: `2026_10_10_192143_create_unified_notification_tables.php`.

| Table | Purpose and guarantees |
| --- | --- |
| `notifications` | Native UUID notification identity, recipient morph, data/read state; category/priority/allowlisted target/version/aggregate count; recipient unread and target indexes |
| `notification_receipts` | Unique recipient/event hash; notification association; deduplication survives inbox retention |
| `notification_preferences` | Unique recipient/type; optional in-app/email/digest choices |
| `notification_type_policies` | Unique type defaults; mandatory rules enforced above configured defaults |
| `notification_deliveries` | Unique recipient/event/channel; sanitized ID-only payload, attempt/status/timing/failure tracking; recovery index |
| `announcements` | Bilingual title/body, audience definition, draft/schedule/publish/expiry/archive lifecycle, author/publisher and timestamps |
| `announcement_recipients` | Unique announcement/user publication snapshot and independent read timestamp |

Existing messaging and transactional-email tables are preserved. No migration was run on the user's MySQL database.

## API endpoints

All paths below are under `/api/v1`, authenticated through Sanctum and rate limited. Inactive/blocked users cannot operate the notification endpoints. Inbox IDs are always looked up within the current user's inbox; unauthorized ownership/resource links return 404.

| Method | Path | Behavior / permission |
| --- | --- | --- |
| GET | `/notifications` | Owner inbox; unread/category filters; 1–50 item pagination; total/unread counts; sanitized bilingual titles |
| POST | `/notifications/read-all` | Mark owner's inbox read |
| POST | `/notifications/{id}/read` | Mark owner's notification read |
| POST | `/notifications/{id}/open` | Reauthorize current target before returning an allowlisted local application path and marking read |
| GET / PUT | `/notification-preferences` | Known type/channel preferences; mandatory rules cannot be bypassed |
| PUT / DELETE | `/notification-presence` | Authorized conversation visibility lease; 25-second expiry |
| GET | `/announcements/{id}` | Published/unexpired snapshot member with current eligibility; records announcement read and matching inbox read |
| GET | `/admin/notification-management` | `notifications.manage`; catalog, policies, both outbox statistics and sanitized failure pagination |
| PUT | `/admin/notification-management/policies` | `notifications.manage`; audited optional defaults; mandatory constraints |
| POST | `/admin/notification-management/deliveries/{id}/retry` | `notifications.manage`; failed unified delivery retry, audited |
| POST | `/admin/notification-management/transactional-deliveries/{id}/retry` | `notifications.manage` plus existing transactional retry permission; shared preserved outbox retry, audited |
| GET / POST | `/admin/announcements` | `announcements.manage`; paginate staff catalog or create draft/scheduled bilingual announcement |
| POST | `/admin/announcements/preview` | Audience-only validation; eligible recipient count, no identities; authorized course scope |
| PUT | `/admin/announcements/{id}` | `announcements.manage`; edit draft/scheduled records |
| POST | `/admin/announcements/{id}/publish` | Manage + publish permission; atomic recipient snapshot and deduplicated notifications |
| POST | `/admin/announcements/{id}/archive` | Manage + publish permission; audited archive |

Browser navigation calls `/open` first. The client additionally rejects external/protocol-relative/backslash paths. Announcement HTML is rendered as escaped plain text.

## Frontend components and routes

`NotificationBell` is shared by `AppHeader` and `PublicHeader`, covering the student, instructor, administration/content, sales/support and authenticated public shells. It displays unread count, recent items, loading/error/empty states, keyboard close/focus restoration and a bounded mobile dropdown. Account switches discard late responses and old counts.

| Component/page | Route / role |
| --- | --- |
| `NotificationCenterPage` | `/notifications`; all authenticated users; unread/category filters, mark-read/all, pagination and authorized links |
| `NotificationPreferencesPage` | `/notification-settings`; optional channels and daily digest; mandatory controls disabled |
| `AnnouncementPage` | `/announcements/:id`; backend-authorized bilingual escaped content |
| `NotificationManagementPage` | `/notification-management`; notification-management permission; catalog/defaults/statuses/failures/retries for both outboxes |
| `AnnouncementManagementPage` | `/announcement-management`; announcement-management permission; bilingual editor, role/course audience selector, count preview, local-time scheduling/expiry and publication/archive controls |

Arabic RTL and English LTR use the existing locale store, translations, Tailwind and brand conventions. New authenticated pages use the shared public shell so their availability is not tied to one role-specific dashboard route.

## Announcements and scheduling

Supported audiences are all active users, selected system roles, effective students of selected courses, and assigned instructors of selected courses. Publication snapshots recipients once. Reading rechecks active status, current role/course eligibility, publication/expiry and snapshot membership. Newly eligible users do not gain a historical announcement automatically; revoked/expired/suspended course access removes access to historical course-targeted content.

Drafts can be edited, scheduled, published once, expired and archived. Bilingual title/body limits and future timing are validated. Scheduling requires publication permission; the scheduler rechecks the author and scope. An expired or unauthorized scheduled record does not block unrelated publications. Create/update/publish/archive and channel-policy/retry changes use the existing audit trail without announcement bodies or private message contents.

`notifications:maintain` runs hourly; the digest/prune variant runs daily at 18:00 in `Asia/Hebron`, with overlap protection. Absolute deadlines are compared to current timestamps. It handles effective access reminders at 7/1 days, expiry, quiz closing and assignment deadlines within 24 hours, scheduled availability, support inactivity over 48 hours, due announcements and stale outboxes. Completed assessments and suspended access are excluded. Reminder/event keys make repeated invocations idempotent.

Optional conversation email is capped to one queued email per conversation/recipient/five-minute activity window. Other optional emails can join a once-per-day digest. Email rate limiting is 30/minute/recipient, plus endpoint throttles. Read notifications and terminal deliveries retain 180 days; event receipts retain 730 days; unread notifications and failed deliveries are preserved for action. These windows are configuration values.

## Messaging integration status

**Present and integrated**, with the pre-existing feature still independently subject to its original incomplete acceptance status. Private, selected-group and dynamic all-student conversations use actual membership/course policies. New-message notifications exclude the sender, require effective access and never give administrators private message contents. No message body is stored in the unified notification payload or exposed by management.

Unread activity aggregates into a conversation notification. Read cursors partially update its count and clear it after the last unread message; deletion recalculates remaining activity. Notification-center read state and message read cursors remain distinct: dismissing an alert does not pretend the actual messages were read. The existing floating message shortcut remains intact.

The visible conversation refreshes a short presence lease and releases it on close, selection change, hidden/offline transitions and unmount. The lease suppresses duplicate alerts while viewing that conversation; real message unread counters remain governed by the messaging service. Deep links carry the authorized conversation ID into the existing messenger. The original mobile fullscreen header/composer/scroll behavior is retained.

## Validation and actual results

- Full Laravel suite: **621 passed, 3259 assertions**, SQLite test database. It required a 512 MB PHP memory limit for existing mPDF tests and `APP_URL=https://jcec-academy.local` to match existing Google OAuth host fixtures.
- New/financial focused run after mandatory mail-policy adjustment: **36 passed, 164 assertions**.
- Notification + financial email + messaging regression run: **58 passed, 377 assertions**.
- Full Vue suite: **468 passed across 51 files** (including 18 new notification UI tests and the preserved messaging suite).
- `npm run build`: passed. Generated build output was restored/cleaned so source commits do not replace tracked compiled artifacts; build locally after applying the branch.
- Laravel Pint: passed after formatting affected PHP files. `git diff --check`: passed.
- Chromium headless on the actual built Vue application with intercepted test API responses: **10 checks passed**, no JavaScript page errors. Both languages, 320/390/768/1280 widths, dropdown bounds, authorized conversation deep link, 390×844 fullscreen chat, stable header/composer during message scrolling, mandatory preference controls, escaped announcements and responsive course targeting were checked. API authorization was tested separately in Laravel; this browser run is not a live browser/API end-to-end test.

New Laravel coverage includes owner/cross-user isolation, multi-role deduplication, optional/mandatory preferences, transaction rollback, current/expired/suspended course scope, grading/content scope, message aggregation/presence/deletion/read cursor, support internal-note privacy, announcement snapshots/revocation/permissions/expiry/scheduling/preview, deadline exclusions, digests/retention, sanitized failures/retries, existing-outbox adapter and queue-failure alerts. Existing suites additionally exercise payment/refund/certificate, assessment, role-management and messaging workflows.

## Outstanding acceptance checks and limits

1. Apply and verify the migration against the user's **local MySQL** installation; verify indexes, morph IDs, concurrent recipient locks, deadlock retries and multi-worker idempotency there. SQLite passing does not validate MySQL contention.
2. Start real local workers for `notifications` and the existing `default` queue, and the scheduler. Verify interrupted processes, timing, recovery and daily schedules against persistent local cache/queue storage. Automated tests used sync/faked dispatch where appropriate.
3. Verify actual SMTP delivery, localized email rendering, bounce/failure behavior and transport retries. Tests use Laravel notification/mail fakes or array transport; no email was sent to a real recipient.
4. Run browser/API integration using real authenticated local sessions and DB-backed audience changes. The browser layout checks used API fixtures; physical Android/iOS keyboard and safe-area behavior remains to be confirmed.
5. Benchmark large announcements and content fan-out. Recipients are streamed in batches of 100 and emails are queued, but inbox/announcement fan-out is synchronous in the source/publication transaction. Very large audiences need measured limits or a durable staged fan-out implementation before claiming large-scale readiness. The hourly availability sweep also revisits historical eligible resources; receipt retention is a bounded deduplication window, not eternal event storage.
6. No generalized HTTP-error/health monitoring integration or separate sales-task workflow was invented. Critical alerts currently cover failed queue jobs; payment and ticket workflows provide the sales/support follow-up actually present in the repository. Connect an explicit monitoring source if broader platform health alerts are required.
7. Browser push is intentionally not enabled because explicit approval was not provided. New notification realtime broadcasting is optional and not enabled; polling works independently. Existing messaging WebSocket deployment was not validated.

These are explicit reasons the delivery is **INCOMPLETE**, not an assertion that all phases and acceptance criteria have been verified.

## Local application steps and commits

On the local development installation only, apply the branch, review `.env`/database destination, then run the existing dependency setup, `php artisan migrate`, `php artisan db:seed --class=RolesAndPermissionsSeeder`, `npm run build`, a worker listening to `notifications,default`, and the scheduler using the project's existing local process conventions. Do not run these against staging or production. Customized staff grants should be reviewed in the existing role-management UI.

| Local commit | Change |
| --- | --- |
| `3bb6706` | Unified native inbox/outbox, scoped workflow events, preferences and announcements |
| `bbdfd1f` | Scope/reminder/retry/digest hardening, sanitized failure monitoring and shared legacy adapter |
| `968e98a` | Bilingual Vue bell, center/preferences/management/announcements and messaging integration |
| `831e7b0` | Course/lesson content workflows and required transactional email policy alignment |

The final documentation commit is reported with the branch in the delivery message. No merge to main or deployment was performed.

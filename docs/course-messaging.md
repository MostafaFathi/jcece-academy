# Course messaging architecture

This change is local development only. No staging or production deployment or main-branch mutation is authorized.

## Design before implementation

Messaging is separate from support tickets. Each conversation pins a course and its instructor at creation. Private conversations have a database-unique identity (course, instructor, student). Groups are either selected membership or dynamic all-students membership. CourseAccessService remains the authority for student access, including sequential packages, suspension, expiry and revocation. Instructor access requires current course ownership, instructor role and effective messaging permission. No administrator bypass or automatic role grants are introduced.

Instructor reassignment seals old conversations for everybody: history is retained but inaccessible through ordinary messaging APIs. A new instructor starts new conversations; private history is never transferred implicitly. Restoring the same instructor restores access. Explicit selected membership is retained historically; removing membership immediately revokes API and attachment access. Rejoining restores history. Dynamic groups evaluate current access on every request and automatically include future eligible enrollments.

Normalized tables: course_conversations, course_conversation_members, course_messages, course_message_attachments, course_message_reactions, course_read_cursors and course_messaging_events. Messages use client UUIDs for idempotent sends and nullable deletion timestamps for tombstones. Read cursors are monotonic message IDs with unique conversation/user keys. A per-conversation lock serializes writes and event versions. Events contain IDs only, never private message content, and act as a durable catch-up outbox. Notification state is computed from live authorization and unread messages rather than duplicated counters or fan-out rows.

## Schema and transaction boundaries

| Table | Identity and references | Important fields/indexes |
| --- | --- | --- |
| course_conversations | Course, pinned instructor, optional private student; restrictive foreign keys | Unique course/instructor/student; NULL student permits multiple groups; kind, title, version; course/instructor/update index |
| course_conversation_members | Conversation/user unique; conversation cascade, user restrict | removed_at membership tombstone |
| course_messages | Conversation/sender/client UUID unique; conversation cascade, sender restrict | Nullable body, reply self-reference, deleted_at; conversation/ID history index |
| course_message_attachments | Message foreign key/cascade | Private storage path, display filename, detected MIME, kind, bytes, probed duration |
| course_message_reactions | Message/user/emoji unique | Message cascade, user restrict |
| course_read_cursors | Conversation/user unique | Monotonic last_read_message_id; zero means unread; conversation FK and scoped validation instead of a zero-valued message FK |
| course_messaging_events | Conversation/version unique | ID-only kind and subject reference; timestamp; durable ordered catch-up |

Private creation and group writes lock the course before conversation changes. Message sends, reads, deletes and reactions also serialize through course/conversation locks and reauthorize the actor within the transaction. Reply/read references must belong to the same conversation. Deletion retains message IDs and references; no regular API physically deletes history. Conversation cascades are for deliberate migration/retention operations, not an exposed deletion endpoint. Send retries reuse a client UUID scoped to the sender and conversation. Only a successful send acknowledgment clears the draft. Catch-up tracks the last processed event version, so batches beyond 100 continue on subsequent ticks.

## Transport decision

Reverb, Echo and pusher dependencies are absent. Hosting capability is unknown. The default implementation uses bounded authenticated polling (5 seconds while visible, exponential failure backoff up to 30 seconds, paused offline/hidden) and database catch-up. This needs no persistent WebSocket process. Laravel private-channel authorization and ID-only broadcast events are prepared; WebSockets remain opt-in, require separately installed/configured Reverb/Echo, TLS, a supervised Reverb process and a queue worker. No WebSocket deployment is claimed. Even with Echo available, polling revalidates access and recovers missed notifications. Sending private bodies over long-lived channels is deliberately avoided because channel admission alone cannot revoke existing subscriptions.

## API and permissions

All endpoints are under /api/v1/messaging, auth:sanctum, active user and effective messaging.view. POST operations additionally require messaging.send or messaging.groups.manage as appropriate. Register permissions through the permission catalog; assign explicitly through existing Admin role management. Running a seeder must not restore permissions removed by an administrator.

GET courses, courses/{course}/students (assigned instructor only), conversations (course/search/unread/page), conversations/{conversation}, conversations/{conversation}/messages (before/search), conversations/{conversation}/events (after), notifications. POST courses/{course}/private, courses/{course}/groups, conversations/{conversation}/messages, conversations/{conversation}/read, messages/{message}/reactions. PUT conversations/{conversation}/members. DELETE messages/{message}. GET attachments/{attachment}. Routes always scope through the conversation access policy. Sends/uploads are limited to 30/minute; reactions 60/minute; group writes 10/minute.

## Lifecycle security and retention

Plain UTF-8 text is escaped by Vue interpolation. Images allow JPEG/PNG/WebP only, verified image content, maximum 5 MB. Voice allows WebM/Ogg/MP4/WAV audio containers, maximum 10 MB and 120 seconds; server probes duration with ffprobe and rejects media when probing is unavailable or invalid. SVG/HTML and generic executables are rejected. Files stay on a private non-served disk. Authenticated reads return private/no-store and nosniff headers. Attachments become unavailable after message deletion. Failed database writes clean up stored files. Delete-own is permitted only while course/conversation access and send permission remain valid; it clears body and disables attachments and reactions, preserves reply references, read state and event identity. No unrestricted moderation access exists; abuse handling is by revoking course/user access through existing administration and preserving evidence under a separately authorized process.

Retention is indefinite until an academy-approved retention policy exists; no automatic destructive purge. Tombstones and historical memberships are preserved. Physical files of deleted messages are retained privately pending that policy and never exposed by the API. Group notifications are one authorized unread-conversation record per viewer, not a message-per-student fan-out. No sender self-notification. Reader counts exclude senders and currently ineligible users.

## Rollback

Disable messaging navigation/routes, stop optional workers, and revert this feature commit. Before rolling back migration tables, export the messaging tables and private disk: migration rollback destroys messaging history. Existing course, learning, support and commerce records are untouched.

## Verification

Verified in the Work local checkout on PHP 8.4.12 / Laravel 13.32.0, SQLite, Node and Chromium headless. Dependencies were installed from existing lockfiles; no package dependencies were added.

| Check | Result |
| --- | --- |
| Full Laravel suite | 592 passed, 3,124 assertions, no failures/errors/skips |
| Messaging feature suite after transaction review | 19 passed, 188 assertions |
| Full Vue suite | 418 passed across 49 files |
| Vite build | Passed |
| Pint `--dirty --format agent` | Passed |
| `git diff --check` | Passed |

The Laravel run used `APP_URL=https://jcec-academy.local` to match the existing Google authentication tests and a 1 GB PHP memory limit for the existing PDF tests. Normal local browsing used localhost with Sanctum stateful cookies. Voice validation used the installed ffprobe binary. The tests exercise current grant eligibility, suspended/revoked/expired/future access, canonical identities and unique constraints, group privacy/future enrollments, reassignment, scoped replies/search/history, attachments, UUID reconciliation, monotonic reads, reactions/tombstones, effective permission changes, rate limits and private-channel admission. Frontend tests cover lost access, hidden/offline polling, history search, safe text/directions, retries, grouping and microphone failure handling.

Authenticated browser acceptance used separate student/instructor contexts with actual application login and a temporary database containing synthetic users only. Verified: student initiation; instructor initiation (authenticated API); two-way delivery through polling; two groups created with the wizard; nonmember denial; a future enrollment joining a dynamic group; image upload/authorized download; MediaRecorder capture, upload and playback using Chromium's synthetic microphone; reply/reaction/delete confirmation; read receipts; the same history in both student/instructor course workspaces and unified inbox; English/Arabic RTL; 320/390/768/1280 widths without page overflow; suspended access clearing the selected history; no JavaScript errors. Physical microphone hardware and actual WebSocket delivery were not tested.

## Local setup and remaining gaps

1. Apply this change to the repository and install dependencies from the existing locks (`composer install`, `npm ci`). Configure the existing local MySQL and Sanctum environment; do not deploy to staging or production.
2. Run `php artisan migrate`. Run the existing role/permission seeder to register the three new permission names, or register them through the existing administrator flow. Explicitly assign `messaging.view` and `messaging.send` to the intended roles and `messaging.groups.manage` to intended instructors. Existing administrator-managed assignments are preserved. No role receives messaging automatically.
3. Install ffprobe locally and set `MESSAGING_FFPROBE` to its executable path if it is outside PATH (including Laragon on Windows). Configure PHP `upload_max_filesize` at least 10M and `post_max_size` above 10M. Voice rejection is secure when probing is unavailable. Microphone recording requires browser permission and a secure context (HTTPS or localhost).
4. Run `npm run build` or the local Vite server. Compiled assets must be regenerated from these sources; generated build output and demo credentials/data are excluded from the patch.
5. Keep `MESSAGING_BROADCAST=false` for the verified polling transport. No messaging queue worker or persistent socket server is needed for this mode. Notifications are aggregated unread state rather than queued fan-out jobs, so notification counts recover from the database after reconnect.

WebSocket activation is a separate unverified integration: install compatible Reverb/Echo packages, add the Reverb connection to `config/broadcasting.php`, configure the server/client host, scheme and ports, create an Echo instance with cookie credentials and `/api/v1/broadcasting/auth`, set `BROADCAST_CONNECTION=reverb` and `MESSAGING_BROADCAST=true`, then supervise `php artisan reverb:start` and `php artisan queue:work`. Configure TLS/proxy forwarding and allowed origins. Validate authorized subscriptions, worker retry/recovery and two-browser delivery before relying on sockets. The existing Vue adapter consumes an optional global Echo instance and still polls for authorization and catch-up. Events contain conversation ID/version/kind only; their payload is not a confidential message body. The durable event table, not successful queue delivery, is the recovery source.

The actual Laragon Windows environment and MySQL parallel transactions were not available. Canonical uniqueness was tested against SQLite and protected by unique constraints/course locks, but concurrent MySQL stress and deadlock behavior require verification on the target local database. MySQL migrations and utf8mb4 configuration need that local validation. Real socket activation, hardware microphone testing, a browser matrix and large-course load testing remain acceptance gaps. No staging was started.

Known practical limits: selected groups accept up to 200 IDs per update; history loads 50 messages and catch-up loads at most 100 events per tick. Notifications and eligibility currently perform per-conversation/user queries; very large enrollments need profiling and query batching. Reader counts on previously loaded older pages refresh when those messages are loaded again; recent history refreshes on read events. There is no administrator private-message browsing, push/email notification delivery, antivirus scanner, retention purge or moderation inbox. These require separately approved policy and scope. Reactions are a curated six-emoji set; text supports arbitrary UTF-8 emoji. Group title/audience are chosen at creation; selected membership can subsequently change. Search mode does not advance read cursors and preserves its filter during polling.

The implementation is usable locally with polling, but the entire requested acceptance matrix is not verified. Overall delivery status: COURSE MESSAGING: INCOMPLETE.


## Changed files

- `app/Events/CourseConversationChanged.php`
- `app/Http/Controllers/Api/V1/CourseMessagingController.php`
- `app/Http/Middleware/AuthorizeCourseMessagingChannel.php`
- `app/Http/Middleware/EnsureMessagingAccess.php`
- `app/Http/Requests/Api/V1/CourseGroupRequest.php`
- `app/Http/Requests/Api/V1/StoreCourseMessageRequest.php`
- `app/Http/Resources/Api/V1/CourseConversationResource.php`
- `app/Http/Resources/Api/V1/CourseMessageResource.php`
- `app/Models/CourseConversation.php`
- `app/Models/CourseConversationMember.php`
- `app/Models/CourseMessage.php`
- `app/Models/CourseMessageAttachment.php`
- `app/Models/CourseMessageReaction.php`
- `app/Models/CourseMessagingEvent.php`
- `app/Models/CourseReadCursor.php`
- `app/PermissionName.php`
- `app/Policies/CourseConversationPolicy.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/CourseMessagingService.php`
- `bootstrap/app.php`
- `config/broadcasting.php`
- `config/filesystems.php`
- `config/messaging.php`
- `database/factories/CourseConversationFactory.php`
- `database/factories/CourseConversationMemberFactory.php`
- `database/factories/CourseMessageAttachmentFactory.php`
- `database/factories/CourseMessageFactory.php`
- `database/factories/CourseMessageReactionFactory.php`
- `database/factories/CourseMessagingEventFactory.php`
- `database/factories/CourseReadCursorFactory.php`
- `database/migrations/2026_10_09_190000_create_course_messaging_tables.php`
- `database/seeders/RolesAndPermissionsSeeder.php`
- `docs/course-messaging.md`
- `resources/js/api/messaging.js`
- `resources/js/components/messaging/CourseMessagingPanel.vue`
- `resources/js/components/messaging/CourseMessenger.vue`
- `resources/js/components/messaging/GroupWizard.vue`
- `resources/js/components/messaging/MessageComposer.vue`
- `resources/js/components/messaging/MessageNotificationBadge.vue`
- `resources/js/components/navigation/AppHeader.vue`
- `resources/js/composables/navigation.js`
- `resources/js/composables/useCourseMessaging.js`
- `resources/js/composables/useVoiceRecorder.js`
- `resources/js/i18n/ar.js`
- `resources/js/i18n/en.js`
- `resources/js/i18n/messaging.js`
- `resources/js/pages/CourseLearningPage.vue`
- `resources/js/pages/InstructorCoursePage.vue`
- `resources/js/pages/MessagingInboxPage.vue`
- `resources/js/router/index.js`
- `resources/js/tests/instructor-portal.test.js`
- `resources/js/tests/messaging.test.js`
- `routes/api.php`
- `routes/channels.php`
- `routes/messaging.php`
- `tests/Feature/CourseMessagingApiTest.php`

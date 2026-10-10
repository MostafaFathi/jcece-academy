# JCEC Academy — تقرير تسليم نظام مراسلات الدورات إلى Codex

**تاريخ التقرير:** 10 أكتوبر 2026.  
**الحالة:** نظام قابل للاستخدام مع polling، مع فجوات قبول وتطوير مذكورة أدناه. لا يُعتبر تنفيذ المهمة الأصلية مكتملًا بالكامل.  
**المستودع:** https://github.com/MostafaFathi/jcece-academy  
**مشروع المستخدم المحلي:** `C:\laragon\www\jcec-academy`  
**النطاق:** التطوير المحلي. لم يتم تشغيل staging أو نشر التطبيق على production.

## 1. النسخة المرجعية وما وصل إلى GitHub

تم تنفيذ النظام أولًا على فرع محلي `feature/course-messaging`، ثم طلب المستخدم لاحقًا رفعه على `main`، وقد نجح الرفع بعد أن أصبح اتصال GitHub يسمح بالكتابة.

| التغيير | Commit على GitHub | الوصف |
|---|---|---|
| نظام المراسلة الأساسي | `e93b58b9786a0ae548b6a3d7ed648652a7a8b1ad` | 56 ملفًا: backend، schema، Vue، tests، docs |
| إصلاح أسماء الصلاحيات | `bf52bce8c576ae4746cacf45005e9dc40196e699` | إضافة ترجمات `messaging` و`messaging_groups` و`send` بالعربية والإنجليزية |

روابط:
- https://github.com/MostafaFathi/jcece-academy/commit/e93b58b9786a0ae548b6a3d7ed648652a7a8b1ad
- https://github.com/MostafaFathi/jcece-academy/commit/bf52bce8c576ae4746cacf45005e9dc40196e699

معرّفا التنفيذ المحليان هما `c2653fd` و`e39ec6c`. يختلفان عن معرّفي GitHub لأن الرفع تم عبر API، لكن شجرة الملفات تطابقت عند الرفع. المرجع المناسب للمواصلة هو نسخة GitHub، وليس مطالبة جهاز المستخدم بالبحث عن الـcommits المحلية.

تم التحقق من أن `main` يشير إلى `bf52bce…` عند آخر رفع. لم نفحص تغييرات المستخدم المحلية أو أي commits لاحقة لهذا التقرير؛ يجب على Codex فحص الوضع الحالي قبل التعديل.

**تنبيه استمرارية:** أول سطر في `docs/course-messaging.md` ما زال يحمل قيدًا قديمًا بأن تعديل `main` غير مأذون. المستخدم أذن لاحقًا بالرفع إلى `main` في هذه المحادثة، وتم الرفع فعليًا. قيد عدم نشر staging/production ما زال قائمًا. ينبغي تحديث صياغة الوثيقة عند استئناف العمل بدل اعتبارها وصفًا حديثًا لحالة الرفع.

## 2. التقنيات والبنية الموجودة

Laravel 13 API، Vue 3، Sanctum، Spatie Permissions، وقاعدة MySQL المستهدفة لدى المستخدم. بيئة التحقق التي استُخدمت كانت PHP 8.4.12، Laravel 13.32.0، SQLite، وChromium headless.

المراسلة منفصلة عن support tickets. لم يُعد استخدام تذاكر الدعم كمحادثات للدورات، ولم تتم إضافة صلاحية قراءة شاملة للمحادثات الخاصة للإدارة.

تم الاعتماد على `CourseAccessService` الموجود، الذي يقيّم enrollment/access grants وسياسات الباقات التسلسلية، بدل إنشاء مفهوم جديد للأهلية أو الاكتفاء بوجود enrollment.

## 3. الوظائف المنفّذة

| الجزء | التنفيذ الحالي |
|---|---|
| محادثات خاصة | طالب ↔ المدرّب المعيّن للدورة؛ يبدأها أي منهما |
| الهوية الموحدة | محادثة خاصة واحدة لكل course/instructor/student، مع unique constraint |
| مجموعات متعددة | يمكن للمدرّب إنشاء أكثر من مجموعة للدورة نفسها |
| مجموعات مختارة | أعضاء صريحون؛ إضافة وإزالة الأعضاء من المدرّب المخوّل |
| جميع الطلاب | أهلية ديناميكية؛ تشمل التسجيلات الجديدة المؤهلة تلقائيًا |
| أماكن الاستخدام | مساحة دورة الطالب، مساحة دورة المدرّب، inbox موحّد لكل منهما |
| الرسائل | نص UTF-8 وemoji، صور، ملفات صوت وتسجيل من المتصفح |
| التفاعل | Reply، ستة أنواع emoji reactions مع toggle/counts، حذف رسالة المرسل |
| القراءة | cursors متزايدة فقط، unread counts، read/group-reader counts |
| البحث والسجل | بحث محادثات ورسائل، unread filter، pagination وolder history |
| الإشعارات | إجمالي غير المقروء في الواجهة والهيدر وAPI مجمّع بحسب المحادثة |
| الاتصال | polling محدود، catch-up events، deduplication، optimistic reconciliation |
| UX | حالات offline/error/retry/loading، العربية RTL والإنجليزية LTR، واجهة responsive |

القنوات المشتركة تعيد استخدام المحادثات نفسها؛ الدخول من صفحة الدورة لا ينشئ سجلًا مستقلًا عن الـinbox.

## 4. قواعد الوصول والخصوصية

1. كل محادثة مرتبطة بدورة واحدة وبمدرّب معيّن واحد.
2. الصلاحيات الفعّالة من Spatie مطلوبة، إضافة إلى نطاق المورد.
3. المدرّب يحتاج دور `instructor` وصلاحية `courses.view` وملكية الدورة الحالية وصلاحيات المراسلة المناسبة.
4. الطالب يحتاج حسابًا نشطًا ووصولًا فعليًا للدورة وفق `CourseAccessService`، إضافة إلى صلاحية المراسلة.
5. انتهاء المنحة، تعليق enrollment، سحب access grant أو منع وصول الباقة يُقيَّم عبر السياسات القائمة.
6. العضو في المجموعة يستطيع الإرسال إذا امتلك صلاحية الإرسال، لكنه لا يدير الأعضاء.
7. إضافة أعضاء لمجموعة مختارة تتحقق من أهلية كل شخص؛ الحد الحالي 200 ID لكل تحديث.
8. removal يحفظ صف العضوية مع `removed_at` ويمنع API والملفات فور إعادة التحقق. إعادة الانضمام تعيد إمكانية الوصول للتاريخ.
9. تغيير المدرّب يغلق المحادثات القديمة أمام الجميع عبر سياسة الوصول، مع حفظ التاريخ. لا تنتقل محادثات الطالب الخاصة للمدرّب الجديد. يبدأ المدرّب الجديد محادثات جديدة.
10. إعادة تعيين المدرّب الأصلي تعيد الوصول إلى محادثاته القديمة بحسب الأهلية الحالية.
11. IDOR مُقيَّد في الرسائل، الملفات، reply/read references، المحادثات والقنوات الخاصة.
12. النص يعرض عبر Vue interpolation دون `v-html` للرسائل.

وجود صلاحية للإدارة لا يمنح bypass على ملكية الدورة أو المحادثة.

## 5. الصلاحيات ولماذا قد تختفي الواجهة

| Permission | العرض العربي | لمن يُفعّل عادةً |
|---|---|---|
| `messaging.view` | عرض رسائل الدورات | الطالب والمدرّب |
| `messaging.send` | إرسال رسائل الدورات | الطالب والمدرّب |
| `messaging.groups.manage` | إدارة مجموعات مراسلة الدورات | المدرّب |

الصلاحيات مسجلة في `app/PermissionName.php`، لكن **لا تُمنح للأدوار تلقائيًا**. يحافظ الـseeder على قرارات الإدارة، ولا يستعيد صلاحيات أزالها المدير. لذلك مجرد `npm run dev` لا يكفي لظهور النظام.

تهيئة المشروع المحلي:

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan permission:cache-reset
```

بعدها تُمنح الصلاحيات من صفحة الأدوار والصلاحيات، ثم يُعاد تسجيل الدخول لتحديث بيانات الحساب في الواجهة. أسماء المراسلة تُعرض حاليًا ضمن قسم «أخرى» في صفحة الصلاحيات؛ إضافة قسم مستقل للمراسلة تحسين UX ممكن، وليس جزءًا من الإصلاح الأخير.

المراسلة موجودة في لوحتي الطالب والمدرّب، لا صندوقًا لقراءة كل المحادثات في لوحة Admin. الروابط:

- `/student/messages`
- `/instructor/messages`
- `/learn/courses/:slug` لمساحة تعلم الطالب.
- `/instructor/courses/:courseId` لمساحة دورة المدرّب.

الخلل الذي ظهر للمستخدم في أسماء الصلاحيات كان نقصًا في ترجمات `resources/js/i18n/role-permissions.js`، وقد أُصلح في `bf52bce…`.

## 6. قاعدة البيانات

Migration: `database/migrations/2026_10_09_190000_create_course_messaging_tables.php`.

| الجدول | الغرض والقيود المهمة |
|---|---|
| `course_conversations` | course/instructor/student، نوع `private` أو `selected` أو `all`، title، version؛ unique لهوية المحادثة الخاصة |
| `course_conversation_members` | conversation/user unique؛ عضوية مختارة مع `removed_at` |
| `course_messages` | sender، body، client UUID، reply reference، deletion tombstone؛ unique conversation/user/client UUID |
| `course_message_attachments` | private path، filename، MIME، kind، size، audio duration |
| `course_message_reactions` | unique message/user/emoji |
| `course_read_cursors` | unique conversation/user؛ `last_read_message_id` monotonic |
| `course_messaging_events` | durable conversation/version unique؛ kind وsubject ID فقط |

تستخدم العمليات transactions وأقفال course ثم conversation لضبط الإنشاء والكتابة. قيود الحذف على المستخدم والدورة تحافظ على التاريخ. لا يوجد endpoint لحذف المحادثات بالكامل.

`last_read_message_id` يبدأ بصفر ولا يستخدم FK مباشرًا للرسالة؛ الـAPI يتحقق أن أي cursor جديد يعود إلى المحادثة نفسها. Reply-to أيضًا يُتحقق من نطاقه.

## 7. الملفات الأساسية لاستئناف العمل

| المجال | الملفات |
|---|---|
| منطق الأعمال | `app/Services/CourseMessagingService.php` |
| سياسة النطاق | `app/Policies/CourseConversationPolicy.php` |
| سياسة الوصول الموجودة | `app/Services/CourseAccessService.php` |
| Controller/API | `app/Http/Controllers/Api/V1/CourseMessagingController.php`، `routes/messaging.php`، `routes/api.php` |
| Requests | `CourseGroupRequest.php` و`StoreCourseMessageRequest.php` تحت `app/Http/Requests/Api/V1/` |
| Resources | `CourseConversationResource.php` و`CourseMessageResource.php` تحت `app/Http/Resources/Api/V1/` |
| Middleware | `EnsureMessagingAccess.php` و`AuthorizeCourseMessagingChannel.php` |
| البث | `app/Events/CourseConversationChanged.php`، `routes/channels.php`، `bootstrap/app.php` |
| الإعدادات | `config/messaging.php`، `config/broadcasting.php`، `config/filesystems.php` |
| shared UI | `resources/js/components/messaging/CourseMessenger.vue` |
| composer | `resources/js/components/messaging/MessageComposer.vue` |
| المجموعات | `resources/js/components/messaging/GroupWizard.vue` |
| gating والتعداد | `CourseMessagingPanel.vue` و`MessageNotificationBadge.vue` |
| state/sync | `resources/js/composables/useCourseMessaging.js` |
| التسجيل | `resources/js/composables/useVoiceRecorder.js` |
| API client | `resources/js/api/messaging.js` |
| inbox/routing/nav | `MessagingInboxPage.vue`، `resources/js/router/index.js`، `resources/js/composables/navigation.js` |
| الترجمات | `resources/js/i18n/messaging.js`، `ar.js`، `en.js`، `role-permissions.js` |
| الاختبارات | `tests/Feature/CourseMessagingApiTest.php`، `resources/js/tests/messaging.test.js` |
| الوثيقة الأصلية | `docs/course-messaging.md` |

أسماء الـmodels: `CourseConversation`، `CourseConversationMember`، `CourseMessage`، `CourseMessageAttachment`، `CourseMessageReaction`، `CourseReadCursor`، `CourseMessagingEvent`، مع factories مقابلة.

## 8. API الحالي

كل المسارات التالية تحت `/api/v1/messaging`، وتحتاج Sanctum وحسابًا نشطًا و`messaging.view`، مع صلاحيات إضافية بحسب العملية.

| Method | المسار النسبي | الاستخدام |
|---|---|---|
| GET | `courses` | الدورات التي يستطيع المستخدم مراسلتها |
| GET | `courses/{course}/students` | الطلاب المؤهلون؛ للمدرّب المعيّن |
| POST | `courses/{course}/private` | فتح/إعادة المحادثة الخاصة الموحدة |
| POST | `courses/{course}/groups` | إنشاء مجموعة |
| GET | `conversations` | قائمة مع course/search/unread/page |
| GET | `conversations/{conversation}` | تفاصيل وصلاحيات وعدّاد |
| PUT | `conversations/{conversation}/members` | تعديل أعضاء selected group |
| GET | `conversations/{conversation}/messages` | السجل: before/search، 50 رسالة |
| POST | `conversations/{conversation}/messages` | إرسال multipart مع client UUID |
| POST | `conversations/{conversation}/read` | رفع cursor إلى message ID |
| GET | `conversations/{conversation}/events` | catch-up بواسطة after version؛ 100 حدث |
| GET | `notifications` | unread state مجمّع |
| GET | `messages/{message}` | تفاصيل رسالة مصرح بها |
| DELETE | `messages/{message}` | حذف رسالة المرسل كتومبستون |
| POST | `messages/{message}/reactions` | toggle emoji reaction |
| GET | `attachments/{attachment}` | عرض/تنزيل مصرح به من التخزين الخاص |

قناة البث: `course-conversation.{id}`، وحدث Echo: `.conversation.changed`. endpoint التفويض: `/api/v1/broadcasting/auth`.

Rate limits لكل مستخدم: sends/uploads/deletes مشتركة 30/دقيقة؛ reactions 60؛ group creation/membership writes 10؛ private initiation 10. توجد طبقة عامة 240 طلبًا/دقيقة لمجموعة مسارات المراسلة.

## 9. الصور والصوت والحذف

- Images: JPEG/PNG/WebP فقط، فحص محتوى الصورة، 5 MB كحد أقصى. SVG وHTML غير مسموحين.
- Voice: حاويات WebM/Ogg/MP4/WAV، 10 MB، و120 ثانية كحد أقصى.
- التحقق من الصوت يتم على الخادم بواسطة `ffprobe`، مع رفض الملفات إذا تعذر التحقق أو كانت تحتوي video stream أو مدة غير مقبولة.
- يحاول MediaRecorder اختيار صيغة مدعومة. الواجهة تتعامل مع رفض الإذن والمتصفح غير المدعوم، وتوفر stop/preview/send/play.
- Disk باسم `course_messaging`، تحت `storage/app/private/course-messaging`، غير منشور عبر public storage symlink.
- الملفات تمر عبر صلاحية المحادثة عند الطلب، مع `private, no-store` و`nosniff`.
- حذف رسالة المرسل يمسح body والتفاعلات ويخفي المرفقات ويحفظ ID وreply references.
- الملفات المحذوفة تبقى مخزنة privately وغير قابلة للوصول، انتظارًا لسياسة retention معتمدة. لا يوجد purge آلي.

لـWindows/Laragon، يجب توفير ffprobe وضبط `MESSAGING_FFPROBE` بمساره إذا لم يكن في PATH، وكذلك PHP upload/post limits. التسجيل يحتاج browser permission وsecure context: HTTPS أو localhost. نجاحه على hostname محلي عبر HTTP يحتاج التحقق الفعلي من سياق المتصفح؛ لا يُفترض أنه مضمون.

## 10. الاتصال اللحظي: الموجود فعلًا وما لم يكتمل

**النمط المفعّل والمتحقق منه هو polling، وليس WebSockets.**

- polling كل 5 ثوانٍ أثناء نشاط الصفحة، مع pause عند hidden/offline وbackoff حتى 30 ثانية عند الفشل.
- table الأحداث وversion هما مصدر catch-up.
- client UUID يمنع duplicate sends، وmerge يوفّق بين الرسائل المؤقتة ورسائل الخادم دون تكرار.
- إعادة الاتصال تسترجع الأحداث المفقودة؛ event batch يتقدم حتى آخر حدث عولج، لا إلى version لم تُعالج أحداثه.
- إلغاء الوصول يمسح المحتوى المحمي في الواجهة عندما يكتشفه polling.
- search mode يحافظ على فلتر الرسائل ولا يرفع read cursor أثناء عرض نتائج البحث.

Reverb/Echo لم يكونا مثبتين ضمن المشروع. لم تُضف هذه dependencies. تم تجهيز Laravel private-channel authorization وbroadcast event وadapter اختياري لـ`globalThis.Echo` فقط. `config/broadcasting.php` يحتوي null/log؛ لا يوجد إعداد Reverb مكتمل.

`CourseConversationChanged` ينفّذ `ShouldBroadcast` و`ShouldDispatchAfterCommit`. الحدث يحمل conversation ID/version/kind دون bodies. الـAPI يظل مسؤولًا عن التحقق من الوصول الحالي. جدول الأحداث durable catch-up log؛ لا يوجد publisher مستقل مكتمل لمعالجة outbox delivery/retry statuses.

الإعداد الحالي: `MESSAGING_BROADCAST=false`. لا يحتاج polling إلى queue worker أو socket process. لتفعيل WebSockets لاحقًا يلزم تثبيت/ضبط Reverb وEcho، connection/client configuration، private auth، TLS/origins حسب البيئة، socket process وqueue worker، واختبارات وصول وإعادة اتصال فعلية. **مجرد تغيير env إلى true لا يكفي.**

## 11. الإشعارات والقراءة

Unread counters مشتقة من الرسائل غير المحذوفة التي كتبها غير المستخدم، بعد cursor القراءة الحالي. لا يوجد sender self-notification.

`notifications` يعيد سجل unread واحدًا لكل محادثة يستطيع المستخدم الوصول إليها بدل fan-out row لكل رسالة ولكل عضو. الواجهة تعرض إجمالي غير المقروء في inbox والهيدر؛ badge لديه polling مستقل. ليست هذه notifications محفوظة بنسخ إضافية في Laravel database notifications، ولا يوجد email/push delivery أو notification center كامل.

Read cursors monotonic/idempotent. Reader counts تستبعد المرسل وغير المؤهلين حاليًا. الرسائل الحديثة تتحدث عند read events، لكن counts على صفحات قديمة سبق تحميلها قد تبقى قديمة إلى أن تُحمّل الرسالة مجددًا؛ هذه فجوة تستحق التحسين.

## 12. نتائج الاختبارات الفعلية

هذه نتائج التشغيل السابق في بيئة Work؛ لا تمثل اختبارات جديدة على جهاز المستخدم أو MySQL.

| التحقق | النتيجة |
|---|---|
| Full Laravel suite | 592 passed، 3,124 assertions، دون failures/errors/skips |
| Messaging suite بعد مراجعة transactions | 19 passed، 188 assertions |
| Full Vue suite بعد تعديل history search/focus | 418 passed في 49 ملفًا |
| Vite build | ناجح |
| Pint | ناجح |
| git diff --check | ناجح |
| بعد إصلاح ترجمة الصلاحيات | اختبارات AdminRolesPage السبعة ناجحة، وVite build ناجح؛ لم يُعد تشغيل full suite لهذا الإصلاح النصي |

من تغطية الاختبارات: اتجاهَا إنشاء private chat، uniqueness/idempotency، مجموعات متعددة، selected privacy، future enrollment، expiry/suspension/revocation/future access، reassignment، IDOR، attachments، malformed/oversized media، reactions/replies/deletion، monotonic read/unread، permission revocation، channel authorization، durable catch-up، rate limits، hiding/offline polling، optimistic dedup، Arabic/English وmic-denied/unsupported states.

Laravel tests احتاجت `APP_URL=https://jcec-academy.local` لتوافق اختبارات Google الموجودة وmemory limit مرتفعًا لاختبارات PDF الموجودة. لا تُغيّر كود الإنتاج فقط للتوافق مع هذا الإعداد الاختباري.

### قبول المتصفح الذي تم فعليًا

استخدمت جلسات دخول حقيقية منفصلة وحسابات وبيانات تجريبية:

- الطالب يفتح private chat ويرسل UTF-8، والمدرّب يرد، وتصل الرسائل بين الجلستين بواسطة polling.
- المدرّب يبدأ محادثة مع طالب آخر عبر authenticated API.
- إنشاء مجموعتين من الـwizard، منع nonmember من selected group، وانضمام future enrollment إلى all-students group.
- image upload وauthorized download.
- MediaRecorder record/preview/upload/play باستخدام synthetic microphone في Chromium؛ لم يُختبر physical microphone.
- reply، emoji reaction، delete confirmation/tombstone، read receipt.
- التاريخ نفسه داخل مساحتي الدورة للطالب والمدرّب والـinbox.
- widths 320/390/768/1280 دون page overflow، وتبديل العربية RTL.
- تعليق enrollment يمسح history بعد polling، وعدم ظهور browser JavaScript errors.

لم يُنفّذ قبول two-browser WebSocket delivery أو concurrent stress على MySQL.

## 13. الفجوات وأولويات المواصلة المقترحة

| الأولوية | المطلوب | معيار قبول واضح |
|---|---|---|
| P1 | تحقق Laragon/MySQL الحقيقي | migrations، utf8mb4، access grants، concurrent private creation/idempotent sends، deadlocks؛ دون duplicate conversations |
| P1 | مراجعة وصول العضوية والقنوات | expired/revoked/suspended/reassigned/removed members ممنوعون من API والملفات وchannel admission؛ لا أجسام خاصة في الأحداث |
| P1 | اختبار ميكروفون حقيقي وbrowser matrix | grant/deny/unsupported، stop/max duration/size، send/play في المتصفحات المطلوبة |
| P1 إذا كانت WebSockets مطلوبة | استكمال Reverb/Echo integration | private channels، worker/reconnect/missed-event recovery، two-browser delivery، polling fallback |
| P2 | قراءة الصفحات القديمة | تحديث reader counts للرسائل القديمة دون polling غير محدود لكل رسالة |
| P2 | UX الإشعارات | تحديد إن كان المطلوب badge فقط أم notifications feed/toasts؛ بناء المطلوب مع نطاق course/unread صحيح |
| P2 | أداء المجموعات الكبيرة | profile/batch eligibility وreader/unread queries؛ منع N+1 على نطاق كبير |
| P2 | تقييم queries/locks | course-wide locks تقلل throughput؛ تحسينها دون فقدان حماية reassignment/uniqueness |
| P2 | section خاص بالصلاحيات | عنوان عربي/إنجليزي للمراسلة بدل «أخرى»، دون auto-grant |
| P3 حسب طلب المنتج | إدارة المجموعة | إعادة تسمية المجموعة/أرشفتها؛ تعديل selected members موجود، تعديل kind/title بعد الإنشاء ليس وظيفة إدارة مكتملة |
| P3 بعد اعتماد سياسة | retention/abuse/moderation | سياسة واضحة قبل purge أو فتح وصول خاص للإدارة؛ لا تمنح unrestricted visibility |

ملاحظات:
- reactions الحالية ستة اختيارات؛ arbitrary emoji في text موجود.
- notifications وreader/eligibility queries تحتاج profiling عند كثرة enrollments.
- pagination limits الحالية: conversations 25، history 50، eligible students 50، events 100.
- fallback timers وبعض الحدود مكتوبة في Vue مباشرة؛ `config/messaging.php` ليس بروتوكول configuration كاملًا يُرسل للعميل.
- لا يوجد antivirus integration أو moderation inbox أو automatic retention purge. content/MIME checks موجودة.
- لا تخلط regression bug مثبتًا مع feature جديدة أو قبول لم يُختبر بعد.

## 14. تعليمات اختبار العمل التالي والرجوع

اقرأ `AGENTS.md` والمهارات والقواعد المحلية قبل التعديل. افحص installed versions وملفات lock. لا تفترض أن جهاز المستخدم يطابق بيئة Work.

```powershell
php artisan test
npm run test:frontend
npm run build
vendor\bin\pint --dirty --format agent
git diff --check
```

للتشغيل الجزئي عند تعديل المراسلة:

```powershell
php artisan test --filter=CourseMessagingApiTest
npm run test:frontend -- resources/js/tests/messaging.test.js
npm run test:frontend -- resources/js/tests/admin-roles-page.test.js
```

استخدم memory limit مناسبًا لاختبارات PDF عند الحاجة. لا تدّعِ نجاح أي test أو browser acceptance دون تشغيل موثّق. راجع security/permissions قبل إضافة endpoint أو channel جديد.

للرجوع: تعطيل واجهة/مسارات المراسلة وإيقاف optional workers وإرجاع commits حسب وضع git الفعلي. لا تتراجع عن migration أو تمسح disk قبل تصدير messaging tables والملفات الخاصة؛ migration rollback يدمر التاريخ. تحديثات المستخدم الأخرى قد تمنع revert مباشرًا، ويجب فحصها.

## 15. Prompt جاهز للمواصلة مع Codex

انسخ النص التالي إلى Codex مع هذا التقرير:

```text
Continue the existing JCEC Academy course messaging implementation.
Project: C:\laragon\www\jcec-academy
Repository: MostafaFathi/jcece-academy
GitHub reference commits:
- e93b58b9786a0ae548b6a3d7ed648652a7a8b1ad: messaging implementation
- bf52bce8c576ae4746cacf45005e9dc40196e699: Arabic/English permission-label fix

Read the attached Arabic handoff report and docs/course-messaging.md.
Inspect the current checkout, git status, AGENTS.md, applicable local rules,
installed dependencies, migrations and effective role permissions before editing.
The repository may contain additional local work; preserve it.

Do not rebuild messaging from scratch or mix it into support tickets.
The working transport is bounded polling; Reverb/Echo is NOT installed/configured.
Do not claim WebSocket deployment or MySQL acceptance based on the earlier
SQLite and synthetic-microphone tests.

Preserve these invariants:
- Exactly one course and pinned assigned instructor per conversation.
- Canonical private identity course/instructor/student.
- Effective Spatie permissions plus resource scope; preserve Admin-managed grants.
- Actual CourseAccessService enrollment/package/grant access, expiry/revocation/suspension.
- Selected membership privacy and dynamic all-students eligibility.
- Safe sealed history on instructor reassignment.
- Private authorized attachments; no broad Admin private-message bypass.
- Client UUID idempotency, monotonic read cursors, tombstones and durable event versions.

First assess the remaining gaps against the current local environment.
Continue the changes I specify, reusing the shared CourseMessenger/API/service.
Prioritize actual MySQL/concurrency/access validation and real microphone UX;
complete WebSockets only when required and dependencies/configuration are authorized.
Review older-page read receipts and large-course query costs.
Keep Arabic/English labels and keyboard/mobile behavior complete.
Do not auto-grant messaging permissions merely to make navigation visible.

Keep work local; do not start staging or deploy to production.
Ask about remote publishing only if my current instructions do not already authorize it.
Run appropriate tests and the required Laravel/Vue/build/Pint/diff checks.
Report files changed, verified tests, actual acceptance gaps and setup requirements.
End with COURSE MESSAGING: COMPLETE or COURSE MESSAGING: INCOMPLETE honestly.
```

**حالة التسليم الحالية: COURSE MESSAGING: INCOMPLETE**


## ملحق: قائمة الملفات المتغيرة

تشمل القائمة التنفيذ الأساسي وإصلاح ترجمات الصلاحيات، ولا تشمل ملفات build المولّدة.

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
- `resources/js/i18n/role-permissions.js`

# Phase 15A P0 implementation checklist

Source of scope: the five P0 work packages in `docs/requirements-gap-audit.md`, § Prioritized gap plan (3 October 2026). Product authority: `JCEC_Academy_Platform_Requirements_AR.docx`. This plan does not promote any P1–P3 package.

Implementation status: account registration/recovery, lesson-file upload/management download, policy-page draft/public architecture and additive permission initialization are coded and under automated verification. Certificate eligibility/approval is **PRODUCT DECISION REQUIRED** by explicit product-owner instruction and unchanged. Legal copy, production mail, staging environment and authenticated browser signoff are external P0 dependencies. An unchecked manual-QA item is not a claim of failure; it has not been verified.

## 1. Public registration and password recovery

- [ ] **Original:** site map § public interface and T2 visitor require account creation; § notifications requires password-reset email. T5 profile mentions changing password. The document does **not** explicitly require email verification. It does not mandate extra signup profile fields.
- [ ] **Current:** Sanctum SPA login/logout and active-user checks exist; `users`/`password_reset_tokens` exist; no public signup, forgot/reset, or self-service change route or page.
- [ ] **Mismatch:** a visitor cannot self-onboard or recover credentials. Do not turn optional verification into a launch gate.
- [ ] **Backend:** create only an active Student with server-assigned role, normalized unique email, hashed validated password, atomic user/role creation and throttling. Use Laravel password broker, neutral forgot response, throttling, one-use token, safe SPA link and session/token invalidation on reset. Add authenticated change-password only if it can be completed without displacing the P0 recovery flow.
- [ ] **Frontend:** localized registration, forgot and reset pages in the existing auth shell; validation, loading, duplicate-submit protection, success/error states and navigation links.
- [ ] **Migration:** none expected; existing reset-token table and user schema suffice.
- [ ] **Tests:** role injection, duplicate/invalid email, throttling, enumeration resistance, valid/invalid/expired reset, password/security state, auth UI and RTL/LTR.
- [ ] **Manual QA:** register, logout/login, request and use real reset email in staging, login with new password on Arabic/English desktop/mobile.
- [ ] **Complete when:** browser and automated account lifecycle pass and production mail delivery is proven. Local log mail is not release evidence.

## 2. Certificate eligibility and approval conflict — PRODUCT DECISION REQUIRED

- [ ] **Original:** § `الشهادات`, paragraphs 85–86: `إصدار شهادة بعد اعتماد الإدارة` and `شروط إصدار قابلة للضبط: إكمال نسبة معينة + اجتياز اختبار نهائي + تسليم واجبات`. T1 certificate says after course/exam pass. No numeric percentage, final-quiz selection, mandatory-assessment scope, assignment submission-versus-grading criterion, or pass-score rule is specified. Whether effective access must be active *at issuance* is also not explicit.
- [ ] **Current:** `CertificateEligibilityService` requires certificate-enabled course, nonempty published active lessons, every such lesson complete, nonsuspended enrollment and effective access; quizzes and assignments are excluded. Student self-issuance exists alongside Admin issuance. Existing issued/revoked certificates are historical.
- [ ] **Mismatch:** original expects configurable progress plus final quiz and assignments and Admin approval; current fixed all-lessons/self-issue rule conflicts. The original is not deterministic enough to implement safely.
- [ ] **Backend/frontend/migration/tests:** **deferred until a written product policy** identifies threshold/configuration, final quiz, assessment scope and pass/submission rule, approval actor/workflow, access-at-issuance condition and treatment of existing certificates. Do not change eligibility, client calculations, issuance, PDFs, revocation or historical records now.
- [ ] **Manual QA:** once approved, cover every gate, concurrency, QR/PDF, revocation/reissue and historical records.
- [ ] **Complete when:** approved rule is implemented centrally and verified. Until then this P0 remains blocked.

## 3. Protected lesson-file authoring

- [ ] **Original:** § learning paragraph 47 and T4 content require controlled downloadable files for lessons/course; T2 Content Manager and T6 Courses/Content require management of lesson files.
- [ ] **Current:** private `lesson_resources` disk and student access-controlled download exist. Admin resource API accepts a stored reference or URL only; browser UI creates links, no byte upload or management download.
- [ ] **Mismatch:** managers cannot author a file resource end to end. Course-level files are in the original but the present resource model is lesson-scoped; retain this visible subgap if not safely covered in this P0 package.
- [ ] **Backend:** multipart create using allowlisted type/size and `UploadedFileStorage`; server-generated private name; validate and authorize; clean up bytes on DB failure; protected management download with fresh policy check. Avoid inventing replacement semantics; keep current metadata edit/delete behavior and do not expose storage paths.
- [ ] **Frontend:** file picker/name, upload/error/busy states and protected blob download in curriculum builder; no local/server path fields.
- [ ] **Migration:** none expected with existing `LessonResource.file_path`.
- [ ] **Tests:** upload, type/size, role/scope, temp-path fallback, unavailable temp file, storage/DB failure cleanup, safe responses, management/student downloads and denial.
- [ ] **Manual QA:** actual manager upload/download and eligible/unauthorized student download, plus separate Support and Assignment multipart regression checks.
- [ ] **Complete when:** byte workflow and security tests pass and real browser checks are signed off.

## 4. Approved public privacy, terms and refund policies

- [ ] **Original:** § public site map paragraph 28 names `سياسة الخصوصية، شروط الاستخدام، سياسة الاسترجاع`; T6 Content explicitly includes admin-managed `الصفحات الثابتة`. No approved legal copy is supplied.
- [ ] **Current:** no public policy API/page or static-page management workflow.
- [ ] **Mismatch:** buyers cannot review required policies. **LEGAL CONTENT REQUIRED**: architecture cannot substitute for owner-approved Arabic/English text.
- [ ] **Backend:** implement narrowly scoped admin-managed static policy pages, publication state and safe plain-text rendering; public endpoint returns only published localized content. No invented legal clauses.
- [ ] **Frontend:** localized route labels and page states, links from public footer/auth/checkout where appropriate; clearly distinguish unavailable copy from approved content.
- [ ] **Migration:** likely a policy-page table only if the existing schema has no suitable content entity.
- [ ] **Tests:** public/guest access, unpublished isolation, role-based edit permission and XSS-safe rendering; routing and RTL/LTR.
- [ ] **Manual QA:** approve and enter actual legal copy, inspect all three pages/links on Arabic/English desktop/mobile before public checkout.
- [ ] **Complete when:** approved, versioned content is published and verified. Until copy is supplied this P0 remains open.

## 5. Production environment and manual purchase/access/security signoff

- [ ] **Original:** integrated selling, payment approval, course access, private files, certificates and role boundaries across §§ goal, commerce, learning and T2/T6 require a working secure deployment.
- [ ] **Current:** automated flows pass locally; production mail, storage/video infrastructure, backup/restore and authenticated browser evidence are absent. Seeder `syncPermissions()` replaces custom grants on five built-in roles.
- [ ] **Mismatch:** code tests are not release acceptance. Environment settings, permission initialization, mail, backups and Arabic/English desktop/mobile purchase/access/security QA are unsigned.
- [ ] **Backend/frontend:** fix only defects actually found during this P0 QA. Choose and test a deterministic, documented safe permission-seeding strategy; no speculative broader feature development.
- [ ] **Migration:** none expected for signoff.
- [ ] **Tests:** seeder behavior and any discovered security regressions; rerun full Laravel/Vue suites, build, Pint, route and migration audits.
- [ ] **Manual QA:** staging config/HTTPS/cookies/CORS/mail/PHP limits/private storage/backup restore, purchase/approval/access, role isolation, support/assignment/resource uploads, QR/PDF and both locales/devices; record owners and evidence in `docs/production-readiness.md`.
- [ ] **Complete when:** product, legal, engineering, security and operations owners have signed real staging evidence. This cannot be closed by local automation alone.

## Phase 15A outcome and release disposition — 3 October 2026

| Exact P0 package | Code outcome | Remaining release gate |
| --- | --- | --- |
| Public registration + password recovery | Student-only registration, neutral broker recovery, SPA forms and tests implemented | Real mail provider/delivered link, non-database session invalidation plan if used, Arabic/English desktop/mobile browser QA |
| Certificate eligibility/approval conflict | **PRODUCT DECISION REQUIRED**; existing eligibility, student self-issue and historical certificates untouched | Written threshold/final quiz/assignment/approval policy, then separate implementation and QA |
| Protected Lesson Resource authoring | Private multipart upload, authorized manager download, file cleanup, UI and tests implemented | Authenticated real browser upload/download and role tests; course-level file placement remains a separate gap |
| Public privacy/terms/refund policies | Bilingual draft/publish architecture, public routes/pages/links and tests implemented; empty unpublished keys seeded | **LEGAL CONTENT REQUIRED**: approved Arabic/English text, publication and browser QA |
| Production environment + purchase/access/security signoff | Additive permission seeding tested; local test/build/route/migration audits completed | Production grant diff/approval, HTTPS/mail/storage/video/backup checks, full purchase/access/security and file-upload staging matrix, cross-functional signoff |

All five P0 packages remain release-open; eight P1 packages remain in the Phase 15 audit and are not part of this implementation. JCEC Academy is **not approved for public launch or formal Release Candidate signoff**. It may proceed to a controlled staging QA pass for the code-complete flows, with certificate QA deferred and no legal publication until approved copy exists.

Local verification: `php artisan test --compact` passed 443 tests/2,033 assertions; `npm run test:frontend -- --maxWorkers=1` passed 326 tests/35 files; `npm run build`, Pint and `git diff --check` passed; 195 non-vendor routes; 48 migrations Ran, none Pending. The parallel two-worker frontend run crashed a Windows worker without an assertion failure; the serial rerun passed. One `policy_pages` migration was added and applied locally; no dependency was added. The local targeted role and empty policy seeders were run only after inspecting the existing grants. These checks are not staging acceptance.

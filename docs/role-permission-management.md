# Admin roles and permissions — local implementation

This page extends Spatie Permission; it does not replace policies or ownership checks. It is local implementation evidence, **not staging or launch acceptance**.

## Registry and access

Five system roles are editable: Admin, Content Manager, Instructor, Sales Support, and Student. The API exposes the **64 keys** in `App\PermissionName` (including the new `roles.manage`) and groups them into Users & Access, Courses & Enrollment, Curriculum, Assessments, Packages, Commerce, Certificates, Reviews, Support, Public Content & Policies, and Reports. There are no Dashboard or Audit Log permission keys in the registry; the audit viewer is still Admin-role gated. UI labels are translated, while keys are secondary identifiers.

`roles.manage` is granted to the Admin role at first introduction. The management API requires **both** Admin membership and this permission; a direct grant to a non-Admin account does not open the API. The UI refuses to grant `roles.manage` to non-Admin roles, and the server rejects such payloads. Sensitive capabilities are marked in the UI, and adding any capability to the Student role requires explicit acknowledgment because non-catalog grants may open back-office routes. The role matrix shows role-assigned permissions only: it does not imply access to every resource. Existing Course, curriculum, assessment, enrollment and other policies still apply; Instructor ownership remains scoped to assigned Courses.

## API and concurrency

- `GET /api/v1/admin/roles`: the five role snapshots and grouped registry metadata.
- `GET /api/v1/admin/roles/{role}`: latest permissions and a version hash for one system role.
- `PUT /api/v1/admin/roles/{role}/permissions`: validated registry keys and the loaded version. Unknown roles return 404, unknown keys return 422, unauthorized callers return 403, and stale versions return 409.

The update locks the role row within a transaction, compares a SHA-256 version of sorted assigned keys, and only then syncs managed grants. Permission keys outside the registry are preserved, not silently deleted; their count is shown as a warning. A stale editor must reload and review before retrying. The server requires at least one **active Admin** with effective `roles.manage` after a role update or a sensitive user-role/status change, including changes through the Instructor account endpoint. This includes direct grants for recovery; the check and change share a transaction and an Admin-role lock. Self-demotion/deactivation protections in the user and instructor requests remain in force.

Spatie's permission cache is reset after an update, including after a failed transaction/rollback. Tests check both current-session and fresh-request effects. Role-permission additions/removals and bulk before/after key summaries are recorded in the append-only Phase 15C audit trail. User role assignment changes get separate added/removed events. No private user data is placed in permission-change metadata.

## User assignment and direct grants

The Admin User form uses a controlled existing-role dropdown with chips; instructor membership continues through the dedicated instructor workflow. Changing a user's roles requires an Admin who has **both** `users.manage` and `roles.manage`. A non-Admin with `users.manage` may create a Student account but cannot assign elevated roles or alter an Admin account; a non-Admin instructor manager cannot change the account credentials/status of an Admin who also holds the Instructor role. Role assignment and role-definition editing are distinct operations. On authorized Admin user detail, inherited role permissions and direct user grants are displayed separately, read-only. Existing direct grants are not deleted or converted to a second allow/deny system; this phase adds no user-specific permission editor.

## Seeder and deployment

`RolesAndPermissionsSeeder` creates missing permission definitions and system roles. It grants the coded baseline **when a role is first created**, not every time the seeder runs. When `roles.manage` is first introduced to an existing installation, it grants that new capability to the existing Admin role once. Later seeder runs preserve manual additions **and removals**. Future permission keys require an explicit product/security decision about initial role grants; the default is definition-only for existing roles. Do not use destructive `syncPermissions` in ordinary deployment seeding. Back up/export the role and permission tables before deployment and review the resulting matrix.

Role editing in the Admin UI is an intentional, audited `syncPermissions` operation for one selected role; it is distinct from deployment seeding. Keep a known active Admin recovery path, review any direct grants, and verify authorization after changing high-risk roles.

## Outstanding local QA

The authenticated Admin → Instructor edit/save → Instructor reload/ownership refusal → Admin restore → Audit Log walkthrough requires a suitable local Admin and Instructor session. No valuable account or role matrix should be modified merely to satisfy this check. Staging has not begun.

Final local automated verification: Laravel **530 tests / 2588 assertions**, Vue **365 tests / 43 files**, Vite build and Pint passed. The build retains only the optional `fontaine` warning. The local roles/permissions seeder was run once to register `roles.manage`, and a read-only check confirmed its Admin grant. The browser's current session reached the forbidden page for `/admin/roles`; no authenticated role edit was attempted.

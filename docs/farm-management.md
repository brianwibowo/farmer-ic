# Farm Management

Farm Management uses Laravel MVC + Service and Inertia Vue. Open
`/admin/farm-management` after signing in as an application Admin or SuperAdmin.
`/admin` redirects to `/admin/dashboard`. Admin needs valid current-team
membership. The sidebar shows the farm link when both portal entry and the Farm
policy allow it.

## Flow and files

- `FarmManagementController` renders `farms/Index`, `farms/Form`, and
  `farms/Show`. Form submissions redirect with an Inertia success toast.
- `FarmService` handles pagination, scoped statistics, owner options, detail
  queries, creation, updates, and deletion. Existing JSON farm routes continue
  to use this service and `FarmController`.
- `ListFarmsRequest` and `SaveFarmRequest` validate data. Controllers call the
  existing Farm policy before invoking services; requests do not repeat it.
- `FarmManagementLayout`, `FarmField`, and `DeleteFarmDialog` reuse the existing
  shell and UI primitives. The layout uses the desktop admin profile in
  `design.md`, scoped semantic tokens, and Plus Jakarta Sans. Small screens use
  cards instead of the desktop table and the existing navigation drawer.
- `/kebun` and its existing prototype pages remain separate. This slice does not
  replace their bundled data or change the daily-log UI/service.

| Method | URL | Behavior |
| --- | --- | --- |
| GET | `/admin/farm-management` | Scoped list, filters, sorting, pagination, summary |
| GET | `/admin/farm-management/create` | Create form |
| POST | `/admin/farm-management` | Persist and redirect to detail |
| GET | `/admin/farm-management/{farm}` | Detail and scoped planting statistics |
| GET | `/admin/farm-management/{farm}/edit` | Edit form |
| PUT/PATCH | `/admin/farm-management/{farm}` | Validate, update, and redirect |
| DELETE | `/admin/farm-management/{farm}` | Delete an empty farm, otherwise return a form error |

The summary describes all visible farms; list filters narrow the table/cards.
City and province filters use exact matches, while search accepts partial text
and treats SQL wildcard characters literally. Pagination retains filters.

## Data and boundaries

The form stores name, total area in hectares, optional owner, address,
province/city/district, land tenure, paired coordinates, and an optional HTTP/HTTPS photo
reference. The photo reference is a link; this feature does not upload files or
load remote images.

Land tenure (`farms.land_tenure`) is required for create and full update: `owned`
(Milik sendiri) or `rented` (Sewa). It describes land use/possession and is separate
from the application Owner role. The additive migration leaves older records
null (Belum diisi); partial updates may preserve that unknown state. PostgreSQL
also rejects other non-null values. Fresh demo seed data explicitly uses owned;
this migration never guesses the status of existing farms. Rental periods and
costs are outside this change.

`FarmLocationMap` replaces the numeric inputs. Users click the map or drag the
pin; keyboard users can pan with arrow keys and select the map center. Removing
the pin clears both coordinates. Only a confirmed selection is submitted; the
initial Indonesia view is not saved as a location. Coordinates remain optional
and validated as a pair, at seven decimal places. The same component displays
the saved pin and a Google Maps link in farm details. A pin does not define land
boundaries or calculate area. Map loading/retry states handle tile failures;
the existing Leaflet dependency and OpenStreetMap tiles are used, with visible
attribution. Maps require an internet connection; there is no geocoding/search
service or new package in this change.

Farm pages explicitly disable attribute fallthrough for shared Inertia props.
The shared SidebarProvider defers its viewport-dependent drawer until after
hydration so the server and first browser render have the same structure,
including component-generated accessibility IDs on smaller screens.

Creator and team come from the authenticated session on creation. The browser
cannot change `created_by`, `team_id`, or assignments. Owner selection is limited
to active application Owners belonging to the farm's team. Assignment management
is outside this feature.

The resource policy matrix remains unchanged: Owner reads, Admin manages within
its team boundary, and Petugas reads the assigned farm. Entering the admin UI
is a separate non-resource decision: `admin.access` permits application Admin
with valid team membership; Superadmin retains the existing developer bypass.
Owner and Petugas get HTTP 403 on every admin route, even when their Farm read
policy permits the underlying record. Team ownership is not application Admin.
Inertia booleans only control visibility of actions; controllers enforce
resource permissions. All list/statistics/owner/detail read queries
use `visibleTo`. Detail derives the team from the persisted farm relationship.
The existing API's local/testing restriction is unchanged; the new web routes
use `auth`, `verified`, and `can:admin.access` middleware with CSRF protection.

The former `/farm-management` and `/{current_team}/dashboard` web routes are no
longer registered. The named `dashboard` route now points to `/admin/dashboard`.
Login and authenticated `/login` visits land Admin/Superadmin on the admin
dashboard and Owner/Petugas on `/`. Inaccessible admin intended URLs are cleared
for non-admin logins. Team invitation responses use the same portal decision.
The Farmer/Owner profile icon opens an inline profile menu with account identity
and a session logout action, with no link to settings, the admin dashboard, or
farm management. Logout uses the existing CSRF-protected POST `/logout`, clears
Inertia's client cache, and returns to the public home page. Settings navigation outside
the admin portal also suppresses admin links for Owner/Petugas.

Browser permission denials render `errors/Forbidden` with HTTP status 403, both
for a direct visit and Inertia navigation. The standalone page offers a safe
home link and logout for signed-in users; guests can open login. It does not
resolve team navigation or display internal exception details. JSON requests
and `/api/*` retain their original error bodies and status codes. Authentication
redirects, validation responses, and other error statuses are unchanged.

Public demo pages still omit tenant data from `auth.user`, `currentTeam`, and
`teams`. The optional shared `account` prop only contains the current session's
name and email for the profile/logout menu. Anonymous and legacy integer
sessions do not query the UUID user table just to render a public demo page.

Admin and Petugas retain the existing one-nonpersonal-team limit. Membership in
two nonpersonal teams makes the current policy deny farm access, even when the
active team is valid. Switching teams alone does not resolve this condition.
Review the account's memberships through the existing team flow; do not loosen
farm policies to accommodate inconsistent demo memberships. Superadmin's existing
developer bypass may show farms outside the active team.

An area reduction below active planted area is rejected. Deletion requires an
explicit dialog confirmation and rejects farms with cycles, daily logs,
activities, weather history, finance records, or operational targets. Related
records are not silently removed.

The same deletion guard also rejects any persisted `users.assigned_farm_id`
reference, including inactive and soft-deleted accounts. Move or explicitly
clear those assignments first; soft-deleted accounts may need restoration
through User Management before reassignment. A completed planting cycle still
counts as history. Superadmin does not bypass these integrity rules. The guard
reports the blocking categories without exposing user identities or record
counts. These checks inspect physical references inside the farm's existing
delete transaction, after controller authorization; they are not listing or
serialization queries. They include records hidden by soft deletion so account
restoration cannot silently lose an assignment. The parent farm lock and FK
checks coordinate concurrent assignment/operational writes. The controller
returns the existing Inertia `delete` form error; development JSON deletes
return HTTP 409. No new route, archive feature, or migration is introduced.

## Force delete khusus SuperAdmin

`GET /admin/farm-management/{farm}/force-delete` renders `farms/ForceDelete` with
an authoritative summary; `DELETE` on the same path confirms it. Both are normal
session/CSRF-protected Laravel routes. `FarmPolicy::forceDelete` always denies
normal actors; only the existing Superadmin `Gate::before` grants it. No grant
or wildcard is added to the static matrix. The controller supplies
`can.forceDelete`; Vue only renders the link. The Form Request delegates the
same Policy for early 403 rejection before validation, and the controller
checks the ability again at its HTTP boundary. There is no new JSON API route.

`FarmDeletionService` selects the persisted farm through `visibleTo`, locks the
farm and selected parent/child rows, and computes counts from scoped IDs. It
includes inactive/soft-deleted user assignments. `ai_insights` has no Eloquent
model, so its internal rows are selected only through already-scoped, locked
cycle IDs. Physical cross-boundary references are checked in both directions;
any ambiguous outside-farm finance/activity/observation link blocks the action,
without absorbing another farm's rows into the deletion set.

The confirmation requires the exact farm name, a 5–2,000-character reason, and
a server-signed summary token. The token covers farm identity/name/team and the
ordered IDs of records in the preview. Added, removed, or reassigned records
invalidate the preview, even if counts happen to match. It is not a full content
snapshot: edits inside an existing row do not invalidate an unchanged record
set. Names and relationships are checked again under locks; the UI can reload
the summary and requires name confirmation again when the token changes.

Deletion removes operational data and details, photos' database references,
cycle observations/risks/insights, cycles, weather, finance, targets, and finally
the farm. Assigned users are detached, never deleted or reactivated. The service
keeps the database's existing FK constraints and deferred integrity triggers
enabled; it does not enable schema-wide cascades. Legacy local `farm_admin`
pivot rows, if still present from earlier experiments, follow their existing
farm FK cascade and confer no runtime authority. Shared tenants, memberships,
users, roles, commodities, varieties, activity types, financial categories,
notifications, AI chat/attachments/knowledge, and previous audit logs remain.
File URLs are removed from affected records; physical files, including external
files and unrelated AI attachments, are not deleted without proven ownership.

The same transaction writes `audit_logs.action = force_delete`, the actor,
original farm identity, reason, time, and deletion counts. Audit/database failure
rolls back deletion and detachment. Existing hard-delete guards remain on the
ordinary action, including for SuperAdmin. No restore action is provided for
force-deleted farms. No migration, production reset, new dependency, permission
editor, or automatic execution against local demo farms is part of this feature.
SuperAdmin may use a known farm URL without membership/current team; this does
not create membership or switch teams, and a missing current team returns to the
admin dashboard after success.

Database-backed queries and summaries naturally reflect deletion. Public/mobile
demo fixtures and the legacy JSON-based AI image demo are unchanged; they are
not live views of deleted PostgreSQL farms. Old AI chats remain historical; this
feature does not rewrite their messages or generic reference knowledge.
Before implementing the phase-1 block/SOP/batch proposal or any new farm-owned
table, extend the deletion graph, shared-reference checks, preview, and tests.
Those draft-only entities do not exist in the current runtime schema.

## Verification

```sh
php artisan test tests/Feature/Farms
npm run types:check
npm run build
```

Tests use the repository's guarded PostgreSQL test database, not
`farmer_ic_dev`. They cover web redirects and validation, CRUD persistence,
Owner/Petugas/Superadmin permissions, foreign-team denial, forged metadata,
owner options, scoped pagination/statistics, and deletion/area restrictions.
Farm policies and resource grants are unchanged. Admin portal routes are
protected by the portal Gate as well as their existing resource checks. Public
Farmer prototype routes remain public and show bundled demo data; existing
daily-log and local/testing JSON API routes retain their own checks. This change
does not claim that all other application routes have been audited.

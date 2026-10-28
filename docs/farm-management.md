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
province/city/district, paired coordinates, and an optional HTTP/HTTPS photo
reference. The photo reference is a link; this feature does not upload files or
load remote images. Coordinates are numeric references, not a polygon editor.

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
The Farmer/Owner profile icon opens an inline profile menu, with no link to
settings, the admin dashboard, or farm management. Settings navigation outside
the admin portal also suppresses admin links for Owner/Petugas.

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

## Verification

```sh
php artisan test tests/Feature/Farms/FarmManagementInertiaTest.php tests/Feature/Farms/FarmManagementTest.php
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

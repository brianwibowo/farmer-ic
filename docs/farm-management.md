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

The list, paginator totals, all three summary values, and city/province choices
are explicitly restricted to the authenticated user's persisted current team,
including for Superadmin. Switching teams changes all these values together.
A tenant without farms returns an empty list, zero totals, and no location
choices. Search and location filters cannot retrieve another tenant's farms;
a location filter retained from the previous tenant may return no matches.
The summary continues to describe the selected tenant regardless of list filters.

This is a selection filter in `FarmService`, applied after `visibleTo`; it does
not alter the Superadmin `Gate::before`, `access.global`, policies, or global
query scopes. Superadmin retains direct access to farm records in other tenants,
including tenants without membership. Switching the active team still requires
persisted membership. No new route or authorization bypass is introduced; the
existing authorization coverage outside this module is unchanged.

## Data and boundaries

The form stores name, total area in hectares, optional owner, address,
province/city/district, land tenure, paired coordinates, and an optional HTTP/HTTPS photo
reference. The photo reference is a link; this feature does not upload files or
load remote images.

Land tenure (`farms.land_tenure`) is required for create and full update: `owned`
(Milik sendiri), `rented` (Sewa), or `partnership` (Kerja sama). It describes land use/possession and is separate
from the application Owner role. The additive migration leaves older records
null (Belum diisi); partial updates may preserve that unknown state. PostgreSQL
also rejects other non-null values. Fresh demo seed data explicitly uses owned;
this migration never guesses the status of existing farms. Rental periods and
costs are outside this change.

`farms.land_tenure_party` stores the name of the person, company, or institution
renting out the land or partnering with the farm (up to 255 characters). Create
and full update require it for Sewa/Kerja sama. PATCH preserves an existing name
when omitted; editing legality requires a name, while unrelated legacy edits may
leave it unknown. Switching to Milik sendiri clears the name. The additive
`2026_11_09_000001` migration preserves existing records with a null name. Admin
forms, lists/details, the Owner farm detail, and the Owner dashboard Legalitas
column use this persisted value. Legacy non-owned land displays “Pihak terkait
belum diisi”. This name is separate from the Owner account and PIC assignment.

The follow-up `2026_10_29_140000` migration expands the existing check constraint
without changing stored values. Rolling it back is rejected by PostgreSQL while
any farm uses `partnership`; it never silently converts or clears that status.
Contract terms and profit sharing for cooperative land are outside this field.

### Owner legality details

`GET /kebun/{farm}/legalitas` (`owner.farms.legality`) opens the read-only
`owner/Legality` page from the dashboard's Legalitas column or the farm detail.
The persisted one-to-one `farm_legalities` record holds the PKS start/end dates,
related institution, and a summary. This does not replace `land_tenure` or
`land_tenure_party`. The database rejects an end date before the start date. Owned land can
explicitly have no PKS period without inventing a rental agreement.

The Controller authorizes Farm `view` and FarmLegality `viewAny` even when no
legality record exists. `OwnerReadService::legality` scopes both records with
`visibleTo($actor, $team)` and restricts the Farm to the persisted active team.
Owner has only `farm_legalities.view = any`; Admin may create/update/delete
the record and view/create/delete its draft PDFs. Petugas has no grant.
Superadmin retains the existing developer bypass. This Owner route additionally
requires `auth`, `verified`, and `owner.access` and has no mutation controls.
Status is derived using the Asia/Jakarta calendar: upcoming, active (inclusive
end date), expired, incomplete, or not_applicable for owned land with no dates.
An absent record stays null; no frontend fixture fills missing legal data.

Admin farm detail labels its existing status field **Legalitas**. Its text link
opens `GET /admin/farm-management/{farm}/legalitas` (`farm-management.legality.show`),
the dedicated detail page with native Farm `view`, Legality `viewAny`, and active
tenant checks. The farm overview contains only that link, without a second
legalities card or editor. On the dedicated page, the pencil opens the shared
create/edit dialog; deletion is confirmed inside that dialog. Save/delete return
to this same detail page; deletion leaves an empty state with the create pencil.
The backend uses:

- `POST /admin/farm-management/{farm}/legality` to create;
- `PUT /admin/farm-management/{farm}/legality/{legality}` to update (multipart
  submissions use `POST` with `_method=put`);
- `DELETE` on the same nested URL to delete the record and its PDFs.

These actions require `auth`, `verified`, `admin.access`, the native policy
ability, and a farm in the persisted active tenant. Requests reject forbidden
farms before date/PDF validation, while Controllers remain the explicit action
authorization boundary. A wrong nested parent cannot reassign a record. PKS
dates are required for sewa/kerja sama and may both be empty for milik sendiri.
Institution and summary are manually entered, required text; no AI generates or
rewrites these values. Owner reads the same service payload on navigation or
refresh, with no cached fixture fallback.

Optional drafts are real PDF uploads stored on the private `legality` disk under
`storage/app/private/legality`, with random server-generated paths and persisted
`farm_legality_documents` metadata. Paths/disk names never appear in page props.
Each legality allows at most five PDFs in total. The configured ceiling is
10 MB per file and 50 MB per submission, reduced by the PHP upload/post limits
(one MB multipart allowance); actual limits are supplied to the form. The
default local PHP setup therefore allows 2 MB per file and 7 MB per submission.
Both extension and content-derived MIME type must be PDF. Removal accepts only
UUIDs belonging to the current legality. New files are removed if persistence
rolls back, and superseded/deleted files are removed after the database commits.
Storage cleanup failures are reported server-side without exposing a file URL.

`GET /farm-legality-documents/{document}/download` requires an authenticated,
verified session, Document `view`, and active-tenant scoped Document/Legality
reads. Responses are private PDF attachments with `nosniff`; Owners can download
only visible files. Normal farm deletion blocks existing legalities. Explicit
force deletion includes legalities/PDFs in the confirmation graph and removes
their private files after commit. A scoped graph that omits physical child rows
blocks cascading deletion instead of silently deleting unseen records.

`FarmLegalitySeeder` adds missing examples for the recognized seven prototype
farms, including four owned records without PKS and three rental/partnership
agreements. It is included after portfolio/name seeders for fresh local/testing
installs and can also be run alone with
`php artisan db:seed --class=FarmLegalitySeeder`. It requires the fixed active
Superadmin seed account and its non-personal seed tenant, uses native Gates and
scoped reads, and refuses other environments. It neither resets identities nor
changes farm fields or existing legality records. Farms whose tenure was edited
away from the fixture are skipped. Summaries describe the land arrangement
without claiming a certificate number or requiring a particular crop.
Apply the additive `2026_11_10_000001` and `2026_11_10_000002` migrations first. Never use this seeder or
an overall database reset against non-prototype data.

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

## Revisi alur kebun dan blok (3 November 2026)

Detail kebun memakai satu section: luas kebun, lokasi, target volume panen
dalam ton, dan ringkasan tahap sub blok ditampilkan sebelum peta. Tabel blok
berada di bawah section tersebut. Header dan padding atas dipadatkan, dengan
angka utama menggunakan gaya metrik Admin agar langsung terbaca. Tim dan
Pemilik tidak ditampilkan; Logbook memakai tautan teks. Tambah/Ubah blok memakai
komponen dialog bersama dan kembali ke detail kebun setelah penyimpanan. Hapus
blok tetap memakai konfirmasi dan mempertahankan penolakan untuk blok berhistori.

Form kebun memilih komoditas sebelum SOP. `farms.commodity_id` disimpan secara
terpisah sehingga komoditas dapat dipilih walaupun belum tersedia SOP. SOP harus
berasal dari tenant yang sama dan cocok dengan komoditas; backend memvalidasi
kombinasi tersebut termasuk pada PATCH. Mengubah komoditas mengosongkan SOP yang
tidak cocok. Pemilik existing tidak diubah oleh form baru. Migration tambahan
`2026_11_03_000001_add_commodity_to_farms.php` mengisi komoditas dari SOP existing
hanya ketika tenant keduanya sesuai; tidak mereset data atau mengubah baseline.

Target panen `farms.target_volume_ton` berasal dari input Create/Edit kebun,
bukan perhitungan AI atau perkiraan dari luas lahan. Presisi maksimal 3 desimal;
1 ton = 1.000 kg. Kebun lama tanpa target menampilkan Belum diisi. Migration
`2026_11_03_000002_add_target_volume_to_farms.php` tidak mengarang target untuk
kebun existing. Seeder instalasi demo baru menggunakan target 80 ton dan komoditas
Jagung; target existing tidak ditimpa.

Progres menghitung batch/siklus yang terhubung langsung ke blok dan tidak
cancelled, dikelompokkan menurut tahap berstatus in_progress yang tersimpan.
Batch harvested masuk kelompok Panen; yang tidak memiliki tahap berjalan
ditandai Belum dimulai. Semua query model, termasuk tahap dan siklus, memakai
visibleTo sebelum agregasi.

Dialog blok/sub blok memakai lebar desktop maksimal 768px, header berkonteks,
ringkasan kapasitas, body yang dapat digulir, dan footer tindakan yang tetap
terlihat. Tampilan tetap mengikuti profil Admin/Farmer dan design.md.

Daftar kebun langsung diawali tiga kartu ringkasan; heading Kelola kebun dan
kalimat pembuka lama dihapus. Tambah kebun berada di header Daftar kebun,
sebelum pencarian/filter. Detail menggunakan label Status lahan, Komoditas yang
ditanam, dan Panduan budidaya (SOP). Progres menjelaskan unit dan pekerjaan,
misalnya 2 sub blok sedang dalam pengolahan tanah, bukan angka tanpa konteks.

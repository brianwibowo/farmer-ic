# Farmer IC Design System — Mobile and Desktop

This is the single design reference for Farmer IC screens and components across
mobile, tablet, and desktop. It combines the Farmer IC design system from the
[UI Design Kebon Figma file](https://www.figma.com/design/WEyyR9lHsrkUD1x4WEi3H6/UI-Design-Kebon?node-id=3-4)
with the desktop admin dashboard guidance for land management and warehousing.
Read this document before implementing or changing any UI.

## Scope and profile selection

- **Shared components:** use the button, input, dialog, tab, pagination,
  accessibility, and state requirements below across all screen sizes.
- **Farmer/mobile profile:** use the Farmer IC palette and mobile typography for
  field-worker screens and the existing farmer interface. Keep this profile when
  those screens are opened on desktop; viewport width alone does not change the
  product's colors or font.
- **Desktop admin profile:** use the desktop layout, extended palette, and
  desktop typography for admin dashboards with dense operational data, including
  land management and warehousing. Adapt these pages to smaller viewports using
  the responsive rules below while retaining their profile.
- An explicit screen specification or supplied wireframe determines its layout
  and content. For unspecified details, apply the selected profile and shared
  rules. Do not mix the two primary palettes within a single screen.
- Implement colors through the existing semantic theme utilities. The desktop
  ramps below are design values, not a claim that matching utilities already
  exist. Define missing tokens centrally, scoped to the desktop layout when
  necessary, before using them in components. Do not globally replace the farmer
  palette to implement a desktop screen.
- Application architecture, persistence, authentication, and authorization follow
  `AGENTS.md`. This design guide does not override those requirements.

## Foundations

### Corner radius

- Default rounded corner: **12px**
- Use the same radius for cards, buttons, fields, popups, and other contained surfaces unless a component specification says otherwise.

### Farmer/mobile color palette

| Token      | Hex       | Intended use                                          |
| ---------- | --------- | ----------------------------------------------------- |
| Primary    | `#68BB6C` | Main actions, selected controls, active navigation    |
| Secondary  | `#1C5E21` | Strong emphasis, pressed or high-contrast actions     |
| Accent     | `#93A99F` | Supporting accents and secondary visual cues          |
| Background | `#F9FAF9` | App and page background                               |
| Surface    | `#ABE7B2` | Filled cards, highlighted surfaces, soft emphasis     |
| Foreground | `#070807` | Primary text and high-contrast content                |
| Text Muted | `#2A1A0E` | Secondary text, descriptions, metadata                |
| Border     | `#D9D9D9` | Dividers, outlines, field borders                     |
| Success    | `#4A943E` | Successful actions and positive status                |
| Warning    | `#EDC92C` | Caution and attention states                          |
| Error      | `#BC0202` | Validation errors and destructive feedback            |
| Info       | `#D9D9D9` | Informational status when no stronger color is needed |
| Disable    | `#E5E7EA` | Disabled backgrounds and controls                     |

In Vue components, use the project semantic utilities such as `text-primary`, `text-foreground`, `text-muted-foreground`, `bg-card`, `bg-muted`, `border-border`, `text-success`, `text-warning`, and `text-error`. Add or adjust theme tokens centrally when a token is missing.

## Farmer/mobile typography

The Figma typography scale uses the following size, line-height, and weight combinations. The design system records spacing as zero for these styles.

### Headings

| Style       | Weight    | Size | Line height |
| ----------- | --------- | ---: | ----------: |
| H1 Headline | Semi Bold | 32px |        40px |
| H2 Headline | Semi Bold | 28px |        36px |
| H3 Headline | Semi Bold | 24px |        32px |
| H4 Headline | Semi Bold | 20px |        28px |
| H5 Headline | Semi Bold | 18px |        26px |

### Supporting text

| Style       | Weight    | Size | Line height |
| ----------- | --------- | ---: | ----------: |
| S1 Subtitle | Semi Bold | 18px |        28px |
| S2 Subtitle | Semi Bold | 16px |        24px |
| B1 Body     | Regular   | 16px |        24px |
| B2 Body     | Medium    | 16px |        24px |
| B3 Body     | Regular   | 14px |        20px |
| B4 Body     | Medium    | 14px |        20px |
| C1 Caption  | Regular   | 12px |        16px |
| C2 Caption  | Medium    | 12px |        16px |
| C3 Caption  | Medium    | 10px |        14px |
| Label       | Medium    | 12px |        16px |

### Button text sizes

| Size   | Weight    | Font size | Line height |
| ------ | --------- | --------: | ----------: |
| Giant  | Semi Bold |      18px |        24px |
| Large  | Semi Bold |      16px |        20px |
| Medium | Semi Bold |      14px |        16px |
| Small  | Semi Bold |      12px |        16px |
| Tiny   | Semi Bold |      10px |        12px |

Use real heading elements and labels in the UI. Keep text wrapping responsive and avoid fixed widths that force truncation on small phones.

## Button

The button system supports five sizes, three visual styles, four interaction states, and text/icon content combinations.

### Sizes

| Size   | Target height |
| ------ | ------------: |
| Giant  |          56px |
| Large  |          48px |
| Medium |          40px |
| Small  |          32px |
| Tiny   |          24px |

Width should hug the content with consistent horizontal padding. Use the 12px radius and keep icon and label alignment centered.

### Styles and states

- **Filled:** primary green surface for the main action.
- **Outline:** transparent or surface background with a visible border.
- **Clear:** no container emphasis; use for low-priority actions.
- **Default:** primary action color.
- **Hover:** darker green emphasis.
- **Focus:** visible focus ring around the control.
- **Press:** strongest green/secondary emphasis while pressed.
- **Disabled:** `Disable` background and muted foreground; no hover or press treatment.

Buttons may contain text, an icon, or an icon with text. When text and icons are combined, support leading and trailing icons and preserve a clear gap between them. Use a native `<button>` and expose a visible focus state.

### Destructive actions and shared button states

- Use the shared `Button` variants: `destructive` for the final confirmation,
  `destructive-outline` for a secondary destructive action, and
  `destructive-ghost` for compact list/table actions. Do not simulate them with
  a normal primary/outline/ghost button plus `text-error` or `bg-error`.
- Red remains the semantic color in default, hover, pressed, and keyboard focus
  states. Use `--destructive`, `--destructive-hover`,
  `--destructive-pressed`, and `--destructive-foreground` from the active theme;
  never reuse primary green interaction states for destructive actions.
- Hover and pressed states must retain readable foreground contrast in both
  themes. Disabled/loading controls keep disabled semantics and cannot submit
  twice. A destructive action still uses its existing confirmation flow.
- Transitions affect colors, borders, and focus shadows without changing the
  control's layout. Respect reduced motion. Use 44px minimum hit targets for
  coarse pointers; compact desktop table actions may use 32px controls.
- All pages, including future mobile pages, should consume these shared
  variants with their own profile's tokens. Do not copy admin colors into the
  Farmer/mobile theme.

### Active navigation across subpages

- A section stays selected on its index, create, detail, edit, and other nested
  actions. Match complete path segments: `/admin/user-management-extra` is not
  part of `/admin/user-management`. Ignore query strings, hashes, and trailing
  slashes when matching navigation.
- Dashboard/root links use exact matching so they do not stay selected while
  visiting another section. Point the Dashboard item to `/admin/dashboard`.
- Use `useCurrentUrl` and `NavItem.exact` instead of adding one-off route checks
  in each page. Expose `aria-current="page"` on exact matches and
  `aria-current="location"` on the selected parent section. Keep the same
  selection for expanded, collapsed, and mobile navigation.
- Close the mobile menu after successful navigation, while preserving normal
  keyboard focus and visible labels/tooltips. Selected navigation uses both
  background emphasis and weight, with a separate keyboard focus indicator.

### Refinement scope

Existing operational screens keep their information, permissions, and task
flows when polishing their UI. Centralize reusable state colors and input
behavior in the relevant profile stylesheet, and keep `design.md` as the single
design reference. Impeccable and UI UX Pro Max provide review guidance; their
suggestions do not replace project tokens, factual content, or this guide.

Admin layouts reuse `AdminHeader` for section navigation, tenant context, and
theme switching. Keep its navigation and theme action on one row on small
phones; long section titles may wrap without pushing controls off screen.
The dashboard uses two metric columns on phones. Field activities use a labeled
officer select next to search at compact widths, and an officer panel only when
there is room for both it and the table. Below the tablet breakpoint, present
activities as a list with the same information as the desktop table. Show the
active filters, result count, and a reset action; distinguish an empty dataset
from a search with no matches. Refresh existing data through Inertia, retaining
the current filters and showing pending, success, and failure feedback.

## Input

Inputs are defined in **Large** and **Medium** sizes with **Filled** and **Outline** styles. Each style supports these states:

- Default
- Filled
- Hover
- Focus
- Disabled
- Success
- Info
- Warning
- Error

Every input needs a visible `<label>`, a helpful value or placeholder, and inline validation feedback. Use the semantic status colors for validation and keep the focus ring visible in both light and dark themes.

## Pop-up

### Shared selects and searchable choices

- Use `components/selects/AppSelect.vue` with `AppSelectOption.vue` for ordinary
  single choices. Use `AppCombobox.vue` for choices that need text search; keep
  region loading, retries, and parent/child resets in `FarmRegionFields` and
  `RegionCombobox`, rather than the generic control.
- Preserve option value types (including empty strings, numbers, booleans and
  null), required validation, names for native form submission, and disabled
  options. Supply a visible associated label or an explicit accessible name.
- Menus float above the document and may flip to remain in the viewport; they
  must not move the content below. Portaled menus inherit the originating
  admin/Farmer profile. Keep keyboard navigation, Escape dismissal, visible
  focus, selected state, and loading/empty/error feedback in both themes.
- Block and batch area fields offer hectares or whole square metres. Changing
  units preserves valid entered area (1 ha = 10,000 m²). Explain the accepted
  precision and show the equivalent area; Laravel validates and converts the
  submitted unit/value to canonical hectares before persistence.

Pop-ups support both horizontal and vertical button layouts. Content patterns include:

- Title and message
- Title, message, and input
- Text with horizontal buttons
- Text with vertical buttons

Use a real dialog or modal pattern, keep the action order predictable, and make the close and primary actions keyboard accessible.

## Pagination

Pagination includes text and icon items with these states:

- Default
- Hover
- Focus
- Selected
- Disabled

Use buttons or links for page items, expose the current page with `aria-current="page"`, and provide accessible labels for previous and next icon controls.

## Tabs

Tabs include individual tab items and five-tab examples. Supported states are:

- Default
- Hover
- Focus
- Selected
- Disabled

Use semantic tab markup when switching content in place. Keep the selected indicator visible and allow the tab row to scroll horizontally on narrow screens.

## Farmer/mobile bottom navigation

The existing Owner/farmer bottom bar has five destinations: Beranda, Kebun,
AI, Keuangan, and Aktivitas. Retain its emphasized AI action.

The Petugas portal uses four equally sized destinations: Beranda, Kebun,
Aktivitas, and Laporan. These open the authenticated `/petugas` pages through
Inertia links. Do not include AI or Finance in the Petugas portal navigation.
The Petugas screens reuse the Farmer mobile shell at every viewport size:
full width on phones and a centered phone-width frame on tablets/desktops,
with the same header, independently scrolling content, and bottom safe area.
Empty pages show their title and an honest placeholder without fabricated
operational records, metrics, or inactive action buttons. Keep the active
navigation indicator, request loading feedback, keyboard focus, and both themes.

The Figma page includes both full-color and LoFi variants. Use the active state for the current route, provide an accessible label for every icon, and keep the bar usable on standard, Plus, and Pro Max phone widths.

## Login and password recovery pages

- Use the Farmer IC palette for the shared login entry point, regardless of the
  role that will sign in. Retain the same profile on desktop and mobile.
- On desktop, use two columns: brand, login form, and footer on the left; a
  rounded illustration panel on the right using `/images/login-page.jpg`.
- Keep the form width readable, align the brand and footer with its left edge,
  and use 48px-high fields and the primary form action with 12px corners.
- On mobile and tablet, show a single-column form and hide the decorative image.
  Allow vertical scrolling on short viewports; do not fix the form height.
- Use a contrasting green from the shared palette for the submit button, visible
  labels, leading field icons, a keyboard-accessible password visibility toggle,
  remember-me control, and the existing password-reset route when enabled.
- Preserve Laravel/Inertia authentication, invitation context, validation errors,
  status feedback, and the submitting state. Only show alternate sign-in methods
  or legal navigation when those capabilities and destinations exist.
- Verify both explicit light and dark themes. The illustration retains its own
  colors while form surfaces and controls follow semantic theme tokens.
- The forgot-password page reuses the same split layout and illustration, with
  one email field, a submit/loading state, inline validation, a reset-link sent
  confirmation, and an Inertia link back to login. Keep delivery and throttling
  on the existing Laravel password broker; do not simulate successful delivery.

## Desktop admin profile

The following desktop guidance complements the shared components above.
Palette and typography differences are intentional profile differences.

### Desktop architecture and layout

Dashboard menggunakan arsitektur **2 Kolom Utama** dengan pendekatan *Clean, Modern, dan Simplified* untuk menangani data kompleks tanpa mengorbankan fungsionalitas UI/UX.

* **Column 1 (Sidebar Navigation):** Panel navigasi vertikal statis di sisi kiri.
  * **Top Section:** Logo Aplikasi.
  * **Middle Section:** Daftar Menu Utama.
  * **Menu Item States:**
    * *Default/Inactive:* Teks, ikon, dan label kelompok memakai
      `--sidebar-foreground`: hitam pada tema terang dan terang pada tema gelap.
      Background transparan.
    * *Hover:* Teks dan Ikon berubah menjadi `brand-300`, Background `brand-100`.
    * *Active:* Emerald solid melalui `--admin-sidebar-active` yang memakai
      `--primary`, dengan teks dan ikon `--primary-foreground`. Pasangan token
      terang/gelap menjaga kontras.
* **Column 2 (Main Content Area):** Area kerja dinamis di sisi kanan.
  * **Top Header (Navbar):** Sticky di bagian paling atas Column 2 saat halaman
    digulir (memuat Global Search, Profile, Notifikasi). Gunakan `top: 0`, latar
    surface yang opak, dan lapisan di bawah overlay drawer/dialog.
  * **Dynamic Content:** Konten yang berubah-ubah (Tabel, Metric Card, Form) dirender tepat di bawah Navbar.
* **Corner Radius:** `20px` untuk card konten/statistik melalui `--admin-card-radius`; `12px` untuk tombol, input, dan modal melalui token radius dasar.

### Desktop sidebar navigation

The shared admin sidebar follows a full-height, left-aligned layout with a thin
border, a 260px expanded width, and a 68px icon rail when collapsed. Use
the login page's `logo.png` mark with the live text **Farm IC** in its header:
a 42px mark, 10px gap and 24px semibold text, with IC using the primary token.
On the collapsed icon rail, show only the 40px mark. Keep the
collapse/expand action in the sidebar header; the mobile drawer has its own
visible close action. The desktop navbar has no sidebar toggle or adjacent
divider. On mobile/tablet, a menu button in the navbar only opens the drawer;
it does not collapse the desktop sidebar. Team context appears below the brand
when the existing backend navigation grants permit it.

Group operational links under **Menu utama** and the existing settings link
under **Lainnya**. Use Plus Jakarta Sans, 14px menu labels, 12px uppercase group
labels, 18px line icons, 6px menu-item corners, and 40px desktop rows. Menu
corners use `--admin-sidebar-menu-radius`; other containers keep the shared
12px radius. Touch targets are at least 44px. Use an emerald solid active row from `--primary` and
`--primary-foreground`; hover and keyboard focus have their own semantic states.
Group labels, inactive menu text/icons, team labels, and the account subtitle
use `--sidebar-foreground` (black in light mode, a readable light color in dark
mode). Active items retain their contrasting foreground. The shared admin navbar
stays sticky at the viewport top while page content scrolls underneath; the
mobile drawer and dropdown overlays remain above it.
Keep parent sections active on their create/detail/edit pages and all settings
subpages, with exact matching for Dashboard.

The navigation scrolls independently while the account card remains at the
bottom. Its avatar, identity, and team come from shared authenticated props;
its existing account/settings/logout dropdown remains functional in both
expanded and collapsed modes. Admin settings pages retain the same admin shell.
Below 1024px, use the accessible drawer, close it after successful navigation,
and support Escape, overlay dismissal, light/dark themes, and reduced motion.

Do not add reference-only store setup progress, integrations, or help links
without corresponding application features. Admin navigation does not include
the starter's Repository/Documentation links. Preserve backend permission props
and the mobile Owner/Petugas navigation profiles.

### Desktop extended color palette

Sistem warna menggunakan skala 50–900 untuk mendukung status dinamis, validasi, dan hierarki visual yang kontras.

#### Brand Primary (Emerald Green)

Digunakan untuk aksi utama, tombol primer, dan status aktif.

* `brand-50`  : `#F0FDF4` | `brand-100` : `#D1FAE5` | `brand-200` : `#A7F3D0`
* `brand-300` : `#6EE7B7` *(Hover Text/Icon di Sidebar)*
* `brand-400` : `#34D399` | `brand-500` : `#10B981` *(Base)*
* `brand-600` : `#059669` | `brand-700` : `#047857`
* `brand-800` : `#065F46` | `brand-900` : `#064E3B`

#### Neutral Slate (Surface & Typography)

Digunakan untuk latar belakang, teks pembacaan, dan batas antarmuka.

* `neutral-0`   : `#FFFFFF` | `neutral-50`  : `#F8FAFC` | `neutral-100` : `#F1F5F9`
* `neutral-200` : `#E2E8F0` | `neutral-300` : `#CBD5E1` | `neutral-400` : `#94A3B8`
* `neutral-500` : `#64748B` *(Inactive Sidebar)*
* `neutral-600` : `#475569` | `neutral-700` : `#334155`
* `neutral-800` : `#1E293B` | `neutral-900` : `#0F172A`

#### Semantic: Warning (Amber)

Untuk status menipis, peringatan, atau aksi tertunda.

* `warning-50`  : `#FEFCE8` | `warning-100` : `#FEF9C3` | `warning-200` : `#FEF08A`
* `warning-300` : `#FDE047` | `warning-400` : `#FACC15` | `warning-500` : `#EAB308` *(Base)*
* `warning-600` : `#CA8A04` | `warning-700` : `#A16207`
* `warning-800` : `#854D0E` | `warning-900` : `#713F12`

#### Semantic: Danger (Rose Red)

Untuk validasi error, stok habis, atau aksi destruktif (hapus).

* `danger-50`  : `#FEF2F2` | `danger-100` : `#FEE2E2` | `danger-200` : `#FECACA`
* `danger-300` : `#FCA5A5` | `danger-400` : `#F87171` | `danger-500` : `#EF4444` *(Base)*
* `danger-600` : `#DC2626` | `danger-700` : `#B91C1C`
* `danger-800` : `#991B1B` | `danger-900` : `#7F1D1D`

#### Semantic: Info (Sky Blue)

Untuk instruksi, data transit, dan informasi netral.

* `info-50`  : `#F0F9FF` | `info-100` : `#E0F2FE` | `info-200` : `#BAE6FD`
* `info-300` : `#7DD3FC` | `info-400` : `#38BDF8` | `info-500` : `#0EA5E9` *(Base)*
* `info-600` : `#0284C7` | `info-700` : `#0369A1`
* `info-800` : `#075985` | `info-900` : `#0C4A6E`

### Desktop typography: Plus Jakarta Sans

Tipografi didasarkan pada skala *Golden Ratio* (~1.618) menggunakan **Plus Jakarta Sans** untuk menjaga harmoni proporsi antara *Heading* dan *Body*. Base size (*Body Default*) adalah `14px` untuk kepadatan data desktop.

#### Font weights

* `Regular` (400): Untuk paragraf panjang, deskripsi, dan catatan.
* `Medium` (500): Untuk label input, badge, dan teks tombol standar.
* `SemiBold` (600): Untuk sub-judul, *table headers*, dan *highlight* penting.
* `Bold` (700): Untuk angka utama (KPI Metric), *Headline* H1/H2, dan *primary actions*.

#### Size and line-height scale

| Style | Size (px) | Line Height (px) | Weight | Penggunaan UI |
| :--- | :---: | :---: | :--- | :--- |
| **H1 Display** | `48px` | `60px` | Bold | Angka KPI Ekstra Besar, Judul Halaman Utama |
| **H2 Title** | `32px` | `40px` | Bold | Angka Metric Card, Judul Modal Utama |
| **H3 Subtitle** | `24px` | `32px` | SemiBold | Judul Section, Card Header |
| **H4 Heading** | `20px` | `28px` | SemiBold | Sub-section, Judul Pop-up ringan |
| **Body 1 (Large)**| `16px` | `24px` | Medium/Reg | Teks informasi utama, Alert Banner |
| **Body 2 (Base)** | `14px` | `20px` | Medium/Reg | **Standar Dashboard** (Teks tabel, Label input) |
| **Caption 1** | `12px` | `16px` | Medium/Reg | Metadata, Helper text, Tooltips, Status trend |
| **Caption 2** | `10px` | `14px` | Medium | Label super kecil (jarang digunakan) |

The application loads Plus Jakarta Sans through vite.config.ts. The scoped
admin font token uses this family while the Farmer/mobile profile keeps its
existing font configuration.

### Shared admin theme — all pages

Use the redesigned dashboard as the reference for all admin listing, detail and
create/edit pages. Share resources/css/admin-dashboard-styling.css and the
admin-dashboard-theme class, including portal content. The light canvas is
light neutral gray #F3F4F6 and the dark canvas is muted forest #111C17, both
through --background; --admin-overview-background aliases that token.
Keep white/dark-card surfaces and emerald accents through semantic utilities.

Use Plus Jakarta Sans, 28px/700 page titles (26px on phones), 16px/600 section
headings, 14px body/labels, 12px supporting text and 36px/700 metric values
(32px on phones). Use admin-page-title, admin-section-title, admin-metric-value,
and admin-panel. Content and metric cards share 20px corners through
`--admin-card-radius`; use `admin-card` for card containers that do not already
use `admin-panel` or `dashboard-panel`. Keep button/input corners at 12px and
sidebar menu corners at 6px. Panels use 18px desktop padding and 16px phone
padding; section gaps are 18px. Page padding is 24px desktop and 16px phone.
Keep admin-field controls, loading/empty/error states and existing permissions.

On admin section index pages, show the section title only in the sticky navbar.
`AdminHeader` supplies the page's semantic h1 there. Do not repeat that title
above the content, and omit the introductory sentence/subtitle beneath it.
Start listing content with metrics followed by the content card. Place permitted
Tambah actions at the right of the same toolbar as search, filters and sorting
inside that card, rather than in a separate row above metrics. Keep controls
bottom-aligned and allow the toolbar to wrap on small screens without overflow.
Dashboard filters retain their overview toolbar. Remove empty header wrappers.
Detail and create/edit pages retain their
record-specific names and action titles so the current context stays clear.
Farmer/mobile pages retain their existing headings.

### Admin dashboard overview

Show one personalized welcome sentence, `Selamat datang, {nama pengguna}!`,
at the left of the overview toolbar using the authenticated Inertia user name.
Use `Selamat datang!` when a name is unavailable. Keep the sentence in one inline
text flow without an icon or forced line break. Use 20px/24px medium text
(phone/desktop), with the user's name in bold emerald. Let long names wrap
naturally only when needed to avoid overflow on small screens.
Keep filters on the right; wrap the controls below it on small screens. This
dashboard greeting does not repeat the navbar title or restore listing subtitles.

The overview uses a light neutral gray canvas
(`--admin-overview-background`, `#F3F4F6`) with white cards in light mode,
and a muted forest canvas (`#111C17`)
with the existing dark card surfaces in dark mode. Keep card corners at 20px and sidebar
menu corners at 6px. Non-interactive cards use minimal borders and no hover lift.
Keep the compact overview layout with readable, stronger typography: 28px bold
page title, 16px semibold panel headings, 36px bold metric values, 14px semibold
labels, 12px supporting notes, and 18px content gaps. On phones use 32px bold
metric values and 13px labels. Numeric totals, stage counts, percentages and
financial values use bold weight (700) so they stand out from supporting text.
Metric cards include small area/stage graphics derived from the displayed data;
keep their notes brief and put detailed explanations below the overview. Use a
160px activity plot beside compact 48px attention rows. Keep filter labels
accessible while visually hiding them in the compact toolbar.
Officer selection rows contain multiple text lines: let their height grow with
content instead of inheriting the default 40px button height. Use 14px padding,
12px between rows and avatar/text, 40px avatars, and separate activity counts
from the last-activity date so the panel remains readable at its narrow width.

Order content as four metrics, the activity chart and attention panel, three
planting/commodity panels, the financial summary, the farm map, and the field
activity table. Keep the map and activity table visible. On phones, stack panels
and show activity cards. Farm selection applies to the entire overview; the
7/30 day selector applies only to the activity chart.

Use persisted stage statuses for the planting breakdown. Show batches without
a recorded running stage explicitly, and keep land clearing at block level.
The map represents saved coordinate points, not invented parcel boundaries.
Activity bars use stable type codes for planting/harvest and a labeled group
for maintenance and other activities. Do not display fabricated trend deltas.

### Farm Management pages

Start the Farm Management index directly with three summary cards for total farms, registered area and city coverage, backed by the existing database props. Do not show the former Kelola kebun heading or its introductory sentence. Place Tambah kebun beside the Daftar kebun table heading, above search and filters. Keep one screen-reader page heading. Summaries describe all visible farms;
the list count reflects applied filters. On phones show two cards and a full-width
coverage card, then a responsive list of farm cards instead of the desktop table.

Keep search, its submit action, Filter, sorting and the permitted Tambah kebun
action on one desktop toolbar row, with Tambah at the right edge. SOP uses the
same arrangement for Tambah SOP. The Filter button opens a
360px-wide dropdown directly below it, aligned to its right edge and constrained
to the viewport width. Do not flip the dropdown above the trigger. Stack city,
province, sort and direction fields inside. Constrain height to the available
space below, with scrollable fields and a visible header/action footer. Include
Reset and Apply, outside-click/Escape dismissal, focus return and closing after
a successful Inertia request. Show validation errors and applied filter chips.
Place page size and pagination in the table footer. Preserve detail/edit/delete
permissions and workflows. Detail and create/edit forms use the same admin-panel
surfaces, emerald titles and 18px gaps; keep the cascading address selectors.

### User Management pages

Keep a standalone stat-card grid followed by the content card containing
directory navigation, search, filters, the permitted Tambah pengguna action and
the user list. Do not wrap stat cards inside the content card. Use the
existing admin canvas, emerald, Plus Jakarta Sans and 20px card corners.
Keep Pengguna and permission-gated Terhapus links with an active underline and
real counts when provided by the backend. Do not repeat role summary chips in
the toolbar; keep role selection inside the Filter dropdown. Place search, its
submit action, Filter and Tambah pengguna together on one toolbar row inside the
content card. Match Farm Management: a 40px outlined Filter button with its icon,
visible label and inline count (secondary when filtered). The 40px emerald
Tambah pengguna button has a Plus icon and full label, and sits at the right
edge of that toolbar, after Filter. Both use the shared 12px
radius and 44px touch targets. Use a square search-submit button with an accessible
name. Keep search flexible on phones; let the toolbar wrap with Add aligned right
when space is limited. Keep Filter outside the search form to avoid nested forms.
Place stat cards above the content card: Total pengguna
plus each role already provided in the permitted summary (Admin/Petugas,
Owner for multi-tenant views and Superadmin for the global view). Use real
unfiltered scoped totals, bold 36px numbers, white/dark card surfaces and line
icons. Show only the label, icon and number; omit supporting sentences below
the number. Cards adapt to one desktop row and two phone columns, with an odd final
card spanning both columns. Do not derive whole-directory totals from a page of
results or fabricate active/deleted counts that the backend does not provide.

Keep role, tenant (where permitted) and farm filters in an anchored dropdown,
using AppSelect and the existing parent/child reset behavior. Show removable
applied filter chips and a reset action. Preserve Inertia search, validation,
pending feedback and pagination, with page size in the footer. The filter panel
opens below its trigger, with a viewport-constrained scrollable body.

Desktop rows have rounded soft surfaces, initials avatars, name, email, role,
team/farm, joined date, persisted active status and an accessible three-dot action
menu. Menus use backend permission booleans and the existing delete confirmation.
On phones show equivalent user cards; wrap toolbars and keep tabs scrollable.
Deleted users use the same directory surface and preserve restore confirmation.
Create/edit/detail pages reuse admin-panel surfaces and the shared typography.
Do not add invitation, favorites, bulk actions, sorting or fabricated counts
unless those capabilities are implemented and authorized. Empty, loading, error,
keyboard focus and both themes must remain usable.

### Desktop admin logbook

Logbook has its own permission-gated admin sidebar item and LogbookLayout.
Use the shared neutral canvas, white stat cards and content card in that order;
the section title appears only in the navbar, without an introductory sentence.
The directory lists accessible farms with persisted
report counts and their latest work date, including farms without reports.
Search uses farm name/location; summary cards describe the full visible scope.

The farm page keeps the admin sidebar, a back link, farm identity, summary cards,
date/recorder filters and paginated history. Admin logbook screens are read-only:
do not show Catat logbook, a report form or other write actions. Petugas records
reports through the existing mobile flow for its assigned farm. Keep WIB times
and the same persisted activity details in desktop history and report views.

Report details use a wide main column for report data, daily notes, measurement
grids, obstacles, proposed solutions and actions; a side panel shows farm context
and recording dates. Missing measurements remain an em dash. Show reported
obstacles as recorded facts, without inventing a resolution or approval status.
Tables become equivalent cards on phones; filters and detail columns stack.
Keep loading, no-results/empty states, pagination, light/dark themes and keyboard
focus. Admin farm links open this desktop flow; the existing mobile flow remains
available to field workers and its routes are not repurposed.

### Desktop task-list tables

Dirancang dengan konsep *Clean & Simplified* khusus untuk pengelolaan *task* / to-do harian.

* **Visual:** Tanpa garis batas vertikal (*borderless columns*). Gunakan *bottom border* horizontal (`1px solid neutral-100`) untuk memisahkan baris.
* **Header:** Teks `Caption 1` (12px), `SemiBold`, warna `neutral-500`, latar belakang `neutral-50`.
* **Data Row:** Tinggi minimal `48px`. *Hover state* merubah background menjadi `neutral-50`.
* **Status Badges:** Gunakan *pill-shaped badges* (radius-full) dengan warna token Semantik 50 (BG) dan 700 (Teks) untuk status *To-Do*, *In Progress*, *Done*.

### Desktop component sizing and navigation

- Buttons share the sizes, styles, and interaction states defined above. Medium
  (40px) is the standard form action, Small (32px) is the standard table action,
  and Giant (56px) is reserved for prominent calls to action.
- Inputs support Large and Medium sizes, Filled and Outline variants, visible
  labels, helper text, and semantic validation states.
- Tabs may use a strong underline or a filled pill to indicate selection. Keep
  Default, Hover, Focus, Selected, and Disabled states.
- Pagination uses text and icon controls and marks the current page with
  `aria-current="page"`.
- Dialogs support vertical or horizontal action layouts, a title and message,
  keyboard-operable close/primary actions, Escape to dismiss where appropriate,
  and Enter to submit the relevant form.

### Desktop token mapping and layout example

Use semantic aliases rather than unregistered ramp classes in page templates:

| Design role | Desktop design value | Semantic implementation |
| --- | --- | --- |
| Inactive sidebar label/icon | Black in light mode; light in dark mode | `text-sidebar-foreground` |
| Sidebar hover background | `brand-100` | `hover:bg-sidebar-accent` |
| Sidebar hover label/icon | `brand-300` | Scoped sidebar hover foreground token |
| Sidebar active background | Emerald solid | `--admin-sidebar-active` / `--primary` |
| Sidebar active label/icon | Contrasting foreground | `--admin-sidebar-active-foreground` / `--primary-foreground` |
| Dividers and table separators | `neutral-200` / `neutral-100` | `border-border` / scoped subtle border token |
| Page/card surfaces | `neutral-50` / `neutral-0` | `bg-background` / `bg-card` |
| Error and destructive states | `danger-*` | `text-error`, `bg-error`, `text-error-foreground` |
| Warning and information states | `warning-*` / `info-*` | Existing semantic warning/info utilities |

Scope aliases to the desktop shell, supply light/dark values centrally, and check
foreground/background contrast. Preserve the intended sidebar hover treatment
for both text and icons; icons should inherit the link color. If a prescribed
ramp pair lacks sufficient contrast, use an accessible shade from the same ramp.

Desktop layout baseline:

```text
Shell:         flex min-h-dvh w-full
Sidebar:       hidden lg:flex lg:w-[260px] lg:shrink-0 border-r border-border
Main column:   flex min-w-0 flex-1 flex-col
Navbar:        min-h-16 shrink-0 border-b border-border
Content:       min-w-0 flex-1
```

The 260px sidebar and 64px navbar are desktop baselines. Allow navbar height to
expand when controls wrap. Use `min-w-0` to prevent tables and forms from forcing
page overflow. If using a viewport-height shell (`h-dvh overflow-hidden`), give
its main content `min-h-0 overflow-y-auto` so long pages remain reachable.

## Responsive layouts and themes

- Desktop admin pages use the two-column sidebar/content layout on wide screens.
  On tablet and mobile, collapse the sidebar into an accessible drawer and stack
  grids, filters, and form fields as space requires. Do not force a 260px sidebar
  beside content on a phone.
- Below 640px, omit the compact tenant badge in the admin header to keep the
  section title and theme control on one row. Tenant context remains available
  in the sidebar/team switcher. Use 16px admin input text on these widths to avoid
  automatic form zoom on phones; retain the desktop input scale on wider screens.
- Farmer screens retain their mobile navigation pattern. Desktop space may be
  used for wider content or additional columns when the screen specification
  calls for it; preserve reading order and access to actions.
- Avoid fixed content widths that overflow small devices. Use flexible grid and
  flex layouts. Wide tables may scroll inside an explicitly labeled container;
  keep the overall page within the viewport.
- Verify small, medium, and large phones (including standard, Pro/Plus, and Pro
  Max iPhones), tablet, and wide desktop layouts.
- Light mode is the default regardless of the system preference. Also verify
  explicitly selected dark mode, including focus rings, contrast, disabled
  states, surfaces, badges, and validation feedback.
- Use the shared 12px corner-radius design target unless a component spec says
  otherwise. Check the actual theme radius before assuming a utility matches
  that target; keep any required token adjustment central and intentional.

## Implementation rules for Farmer IC

Farm list toolbars keep search, the region-filter disclosure, and sorting visible.
Place province/city choices in a collapsible panel at every viewport size, show
applied filters as removable controls with an active count, and keep page size
beside pagination. Use a direction icon beside the sort field; its accessible
name and tooltip explain the current order and the next action for names, areas,
or dates. Sort and page-size changes apply through the existing scoped backend.

Farm Management forms display capacity limits before submission and keep a
focusable, linked error summary alongside inline errors. Address selection starts
with a searchable provinsi, then kota/kabupaten, then kecamatan using the existing
combobox pattern. Later controls stay disabled until their parent is chosen;
changing a parent clears all descendants. Result lists float above page content
without taking layout space, stay above the map, and flip upward when space below
is limited. Load choices from the database by parent code and filter names within
that list. Existing unmatched addresses are explicitly marked as legacy and
preserved until changed.

Block pages reuse `FarmManagementLayout` when the backend shared navigation
permits Farm Management, including the Admin sidebar, header, typography,
`admin-field` controls, and themed dialog portals. Keep Manajemen Kebun active
while visiting block routes. Farm detail uses one overview panel: registered area, location, user-entered harvest target in tonnes, and the number of sub blocks at each persisted stage come first; the location map follows within the same panel. The header combines the back action and farm name on one row, with compact top padding so the overview is visible immediately. Use the shared 36px/700 metric style (32px on phones) for area, target, and sub block counts, with a bold location name. Use complete, beginner-friendly labels: Status lahan before Milik sendiri/Sewa/Kerja sama, Komoditas yang ditanam, and Panduan budidaya (SOP). Each progress item names its unit and action, for example 2 sub blok sedang dalam pengolahan tanah. Never show unexplained fragments such as 1 Pengolahan tanah. Team and owner information do not appear in the detail. Logbook is a text link. The block table follows the overview; create/edit actions open a shared dialog and return to that detail. Farm forms choose a commodity before its matching SOP. Sub block create/edit uses a dialog, while each sub block always shows its stages and start/completion dates without a progress disclosure.

Block and sub block editor dialogs use a roomy 768px maximum desktop width, constrained to the viewport on phones. Use card surfaces, semantic tokens, a context header with the existing icon system, a visible capacity summary, two form columns where space permits, and an independently scrolling body. Keep the separated Batal/Simpan footer visible while scrolling; hide duplicate submit/close actions while processing. Retain focus trapping, keyboard dismissal, unsaved-change confirmation, inline errors, and both profile themes. Block forms follow the farm
form width, and compact list metrics retain their units.
Capacity metrics provide an information button with a 44px touch target using
`MetricInfoTooltip`; explain block boundaries, batch reservations, and a labeled
example. Keep totals visible, support keyboard/tap dismissal, and theme portals.

For the Farmer profile, the block shell uses `resources/css/farm-block-styling.css`
and `farm-block-theme` for contrast corrections, including dialog
portals. Primary button labels use a dark foreground on the existing light green;
error/success text has separate dark values. Keep Admin tokens in the existing
admin stylesheet. On mobile, compact statistics retain units, optional list
filters can collapse, and batch timelines show all stages and their dates without an expansion step. A visible return link preserves the permitted Admin
context when moving between farm details, blocks, and logbook.

Admin dashboard screens share `resources/css/admin-dashboard-styling.css` for
their light/dark tokens, typography, and field states. Apply the
`admin-dashboard-theme` class to admin layouts and portal content. Use
`admin-page-title` for the main page title, `admin-navbar-title` for the navbar,
`admin-section-title` for section headings, `admin-metric-value` for metric
values, and `admin-field` for admin inputs. `admin-field--large` provides the
large input size; `data-status="success"`, `"warning"`, or `"info"` adds a
semantic border, while `aria-invalid="true"` marks an error. This stylesheet
implements the design rules in this document; farmer/mobile screens keep their
separate theme.

- Use Laravel + Inertia + Vue 3 with `<script setup>` and the Composition API.
  Follow the MVC + Service architecture and authorization rules in `AGENTS.md`.
- Persist operational records in the database and load them through controllers
  and services. Seed persistent demo records through database seeders. Browser
  storage is only for appropriate UI preferences or explicitly frontend-only
  examples, never the authoritative source of operational records.
- Read this document's shared rules and the relevant mobile/desktop profile
  before implementing or changing a page, component, or layout.
- Use semantic theme utilities instead of hard-coded colors in page templates.
  Define missing color, shadow, and related tokens centrally when possible;
  follow `AGENTS.md` for necessary exceptions.
- Build reusable controls in `resources/js/components/`, layouts in
  `resources/js/layouts/`, and page composition in `resources/js/pages/`.
  Page names must match `Inertia::render()`; use Inertia `<Link>` navigation.
- Interactive screens need request loading indicators (skeletons/spinners),
  empty states, success/error feedback, and client-side plus server-side form
  validation. Do not artificially delay real backend requests.
- Use semantic headings, labels, buttons, links, tables, and dialogs. Ensure
  keyboard operation and visible focus in both themes. Use a button for an
  action and a link for navigation.
- Prefer local assets and existing project primitives. Do not add a new UI
  package without asking.
- Verify responsive behavior and both themes; report changed pages/components,
  assumptions, and intentionally omitted wireframe content. Run the frontend
  build after implementation changes as required by `AGENTS.md`.

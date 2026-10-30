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

The bottom bar has four menu destinations:

- Beranda
- Kebun
- Keuangan
- Aktivitas

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
    * *Default/Inactive:* Teks dan Ikon menggunakan `neutral-500`. Background transparan.
    * *Hover:* Teks dan Ikon berubah menjadi `brand-300`, Background `brand-100`.
    * *Active:* Teks dan Ikon `brand-600`, Background `brand-50`.
* **Column 2 (Main Content Area):** Area kerja dinamis di sisi kanan.
  * **Top Header (Navbar):** Berada di bagian paling atas Column 2 (memuat Global Search, Profile, Notifikasi).
  * **Dynamic Content:** Konten yang berubah-ubah (Tabel, Metric Card, Form) dirender tepat di bawah Navbar.
* **Corner Radius Default:** `12px` (map to the central radius token) untuk card, modal, dan container utama.

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

The existing application loads Instrument Sans through `vite.config.ts` and
`resources/css/app.css`. Plus Jakarta Sans is the desktop design target; configure
its font loading and scoped font token when implementing that profile. Do not
assume the font is installed or change the farmer font globally as a side effect.
No font or theme runtime changes are made by this documentation merge.

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
| Inactive sidebar label/icon | `neutral-500` | `text-muted-foreground` |
| Sidebar hover background | `brand-100` | `hover:bg-sidebar-accent` |
| Sidebar hover label/icon | `brand-300` | Scoped sidebar hover foreground token |
| Sidebar active background | `brand-50` | Scoped sidebar active background token |
| Sidebar active label/icon | `brand-600` | `text-sidebar-primary` |
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

# AGENTS.md

## PROJECT MODE: FULLSTACK STAKEHOLDER PROTOTYPE

This repository is a Laravel 13 + Inertia.js + Vue 3 application used for a
clickable stakeholder demo. New product work may involve both frontend and
backend development. Extend the existing Laravel authentication, team,
controller, migration, and test scaffolding only as needed for prototype features.

The goal is a polished, believable interface backed by working Laravel
business logic and persistent database data. This remains a stakeholder
prototype, not an assumption that the application is production-ready.

## BACKEND AND DATABASE BOUNDARY

Implement new features using Laravel's Model–View–Controller architecture with
a Service layer (MVC + Service). Do not introduce a Repository layer or
Repository interfaces; use Eloquent models and query builders directly from
services where appropriate.

- Models in `app/Models/` represent persisted domain data and relationships.
- Controllers in `app/Http/Controllers/` handle requests, invoke authorization,
  call services, and return Inertia pages, redirects, or responses. Keep business
  rules out of controllers.
- Form Requests in `app/Http/Requests/` handle validation and may enforce
  request-level authorization; do not rely on client-side validation alone.
- Services in `app/Services/` contain use-case/business logic, coordinate
  Eloquent persistence, and manage transactions when necessary.
- Policies and Gates enforce authorization; authorization rules must not be
  buried in Vue components or scattered through service implementations.
- Migrations, seeders, models, controllers, routes, and backend tests may be
  added or changed as needed for the prototype. Keep changes focused.
- Use Laravel routes and Inertia `<Link>` navigation. Server actions should
  follow Laravel's normal request/response and CSRF protection conventions.
- Persist domain data in the database. Do not use `localStorage`, bundled JSON,
  or frontend-only state as the source of truth for operational records.
- Frontend-only UI state (such as theme, open panels, temporary filters, or
  unsaved drafts) may remain in Vue state or browser storage.
- Seed data belongs in database seeders when it represents persistent domain
  records. Existing fixtures in `resources/js/data/` may be retained for
  explicitly frontend-only visual examples, not as live business data.
- Browser storage helpers belong in `resources/js/composables/`.
- Frontend service wrappers belong in `resources/js/services/` when useful;
  they call backend routes or encapsulate presentation-only behavior, rather
  than implementing authoritative business rules or reading operational data
  directly from `localStorage`.
- Do not install new Composer or npm packages without asking.

Keep the existing AI and RAG integration working. The current
`farmDemoData.json` dataset may be used as a migration/seed fixture, but must
not remain the authoritative source for farm records that have been migrated
to the database. Where AI/RAG requires indexed knowledge, derive or refresh
that knowledge from the same authoritative backend data (or clearly identified
reference documents). Do not silently return stale JSON-based answers when
users expect current database records. Preserve the existing AI workflow
unless a change is necessary for this transition.

The user can leave prototype mode only by explicitly writing **"exit prototype
mode"**. Until then, develop functional fullstack prototype features while
keeping data and integrations within the agreed demo scope.

## STATIC RBAC AND AUTHORIZATION

Use Laravel's native Gates and Policies with a small, static role-permission
matrix defined in `config/access.php`. Role names, permission keys, and scopes
are defined in code, not through dynamic role/permission management tables or
runtime permission editors.

- The fixed roles are `owner`, `superadmin`, `admin`, and `petugas`.
- Define the permitted actions and their scopes in `config/access.php` under
  `roles`, for example `'harvests.view' => 'any'` or
  `'harvests.update' => 'own'`. Absence of a permission means deny.
- Interpret `any` as access to all records permitted by the application's
  tenant/team boundary, and `own` as access only to the user's assigned
  kebun/lahan or another explicitly defined ownership scope. Do not equate
  `own` with `created_by` without checking the domain relationship.
- Centralize reading and resolving `config/access.php` permissions for use by
  Gates and Policies. Use Policies for resource-level decisions (including
  record ownership and kebun/team scoping), and Gates for non-resource actions.
- Authorize actions on the backend before service execution and scope read
  queries as well as writes. Frontend visibility rules are only for UX; hidden
  buttons and routes are not security controls.
- Do not implement implicit role inheritance or superadmin bypasses. A role
  gets only the permissions explicitly listed in the matrix.
- The `owner` role can view all aspects of Kebun, Aktivitas, Finance, and User
  data (and other read-only features defined in the matrix), but must not
  create, update, or delete operational data. Only `owner` can access AI/RAG
  features. Other roles must not gain `ai.access` through a wildcard override.
- Keep authentication based on the existing Laravel authentication flow.
  Associate users with their assigned role and relevant kebun/team scope in
  backend data. Do not trust role, user ID, or scope values supplied by the
  browser as proof of authority.
- Return proper `403 Forbidden` behavior for denied actions. Add tests covering
  each role's allowed/denied actions and cross-kebun/team access; default deny
  for undefined actions.
- To change role permissions, edit `config/access.php` and the corresponding
  authorization tests rather than adding dynamic admin UI to manage roles.

## DUMMY DATA RULES

- Seed realistic data in the database for fresh prototype installations; do not
  use placeholder text such as `test 123` or `lorem ipsum`.
- Prefix browser storage keys with `demo_` and include a version, for example
  `demo_v1_products`. Use these keys only for UI/demo preferences, not as the
  authoritative storage for operational records.
- Wrap browser storage reads and writes in `try/catch` and fall back to safe
  default UI state; load persistent domain data from the backend instead.
- Add a visible **Reset demo data** action that clears demo browser keys and
  restores the demo database seed state through an explicitly authorized,
  prototype-only backend action. Require confirmation and never reset
  non-demo/production data.
- Show loading states for backend requests. For any remaining mock-only
  frontend service, simulate 300 to 600 ms service delays and show loading
  states; do not artificially delay real backend operations.
- Use the existing Laravel authentication for user login and RBAC. Do not
  replace it with a browser-only fake login.

## REPOSITORY CONVENTIONS

- Vue 3 with `<script setup>` and the Composition API. Do not use the Options
  API.
- Pages go in `resources/js/pages/` and must match the page name passed to
  `Inertia::render()`.
- Reusable UI belongs in `resources/js/components/`; layouts belong in
  `resources/js/layouts/`.
- Keep components small and readable.
- Use the existing Tailwind, Vue, Inertia, and UI primitives. Do not install a
  new npm package without asking.
- Before any frontend UI work, read and follow the project design guidance in
  [`design.md`](design.md), including its tokens, typography, component
  patterns, and mobile layout conventions.
- Use the existing semantic theme utilities for all UI colors, such as
  `text-primary`, `text-foreground`, `text-muted-foreground`, `bg-card`,
  `bg-muted`, `border-border`, `text-success`, `text-warning`, and
  `text-error`. Do not hardcode hex, RGB, or RGBA colors in page or component
  classes. If a needed color, shadow, and related does not exist, then a
  hardcoding of style is allowed/necessary.
- Ensure light mode is default mode, ignoring system dark mode
- Check every new screen in both light and dark themes. Avoid fixed widths;
  support small, medium, and large phone viewports, including standard,
  Pro/Plus, and Pro Max iPhone sizes.
- Do not edit `.env` or commit changes.

## WIREFRAME-TO-VUE WORKFLOW

Treat an attached wireframe or screenshot as the source of truth. Inspect the
whole image before coding and identify every section, label, control, field,
state, and annotation.

1. Map each screen to a page in `resources/js/pages/`.
2. Extract repeated or distinct UI into components in
   `resources/js/components/`.
3. Match the wireframe's order, hierarchy, grouping, and visible content.
4. Use the existing project design language; do not invent a new UI system.
5. Implement annotated navigation, tabs, filters, dropdowns, modals, sorting,
   and forms with frontend state; persist submitted domain data through the
   appropriate Laravel controller and service.
6. Fill lists, tables, cards, charts, and profiles with realistic
   database-seeded prototype data returned by the backend.
7. Use local placeholder assets instead of hot-linked images.
8. Make the layout responsive for desktop and mobile.
9. Use semantic HTML: real buttons, links, labels, inputs, headings, and
   tables.

When a wireframe is unreadable or ambiguous, ask before building. For small
visual details such as spacing or icon choice, make a reasonable assumption
and report it afterward. If only an inaccessible Figma link is provided, ask
for a screenshot or export.

## UI QUALITY REQUIREMENTS

Every screen should provide:

- a loading state;
- an empty state;
- success or error feedback after actions;
- client-side validation for forms;
- working desktop and mobile layouts; and
- no dead buttons, links, menus, console errors, or warnings.

After each screen, report the pages and components changed, assumptions made,
and any wireframe content intentionally skipped.

## VERIFICATION

Install dependencies with `composer install` and `npm install` when needed.
Run the frontend build after changes:

```text
npm run build
```

On Windows PowerShell, use `npm.cmd run build` if the PowerShell execution
policy blocks `npm.ps1`. Report whether the build passed and mention any
environment limitation. For backend changes, run relevant Laravel tests
(including authorization and service behavior) and report their results.
Verify frontend UI, database persistence, validation, and the frontend/backend
integration as appropriate. Do not run destructive migrations or demo-data
resets against a non-demo database.

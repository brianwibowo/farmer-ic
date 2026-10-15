# AGENTS.md

## PROJECT MODE: FRONTEND-ONLY STAKEHOLDER PROTOTYPE

This repository is a Laravel 13 + Inertia.js + Vue 3 application used for a
clickable stakeholder demo. New product work is frontend work. The existing
Laravel authentication, team, controller, migration, and test files are
starter scaffolding and must not be extended for demo features.

The goal is a polished, believable interface that works entirely with dummy
data in the browser.

## BACKEND AND DATABASE BOUNDARY

Do not add or modify database or backend business logic for a frontend feature.
Do not create or change migrations, seeders, factories, models, database
queries, controllers with business logic, Form Requests, policies, jobs,
events, listeners, observers, API endpoints, authentication/authorization
flows, external integrations, or Composer packages.

Use frontend state, static data, and browser storage instead:

- Seed data belongs in `resources/js/data/` as `.js` or `.json` files.
- Browser storage helpers belong in `resources/js/composables/`.
- Dummy data services belong in `resources/js/services/`; components should
  call these services rather than accessing `localStorage` directly.
- Use Inertia `<Link>` for navigation.
- A Laravel route may be added only when it is needed to serve an Inertia page,
  and it must contain no data access or business logic.

If a feature genuinely cannot work without touching a backend-related file,
stop before making that change and explain which file is required, why it is
required, and why the frontend-only alternative is insufficient. A narrowly
scoped change is acceptable only after that explanation; it must not introduce
database persistence, backend business rules, or a real API.

The user can leave prototype mode only by explicitly writing **"exit prototype
mode"**. Until then, keep all feature data local to the browser or bundled
frontend assets.

## DUMMY DATA RULES

- Seed realistic data on first load; do not use placeholder text such as
  `test 123` or `lorem ipsum`.
- Prefix storage keys with `demo_` and include a version, for example
  `demo_v1_products`.
- Wrap storage reads and writes in `try/catch` and fall back to seed data.
- Add a visible **Reset demo data** action that clears demo keys and reloads
  seed data.
- Simulate 300 to 600 ms service delays and show loading states.
- Use a fake login only when the UI requires one; store its demo state in the
  browser and do not add real authentication.

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
   and forms with frontend state.
6. Fill lists, tables, cards, charts, and profiles with realistic dummy data.
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
environment limitation. There is no requirement to add backend tests for this
prototype; verify the UI and frontend behavior instead.


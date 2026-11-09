# Historical mobile reference (read-only)

## Provenance and scope

The nine pages in `resources/js/pages/reference/` are isolated adaptations of
`resources/js/pages/farmer/` from exact Git commit
`73f2555d91d0fb2d6acb7bc70813921edd479ea4`, committed **29 October 2026,
17:26:26 +07:00**. This is a visual reference, not restoration of historical
privileges or operational services. No current role/policy route is replaced.

The transitive historical dependency tree lives only in
`resources/js/reference/`. Its 74 source modules are enumerated below and in
`provenance.json`. The fixture JSON is byte-for-byte historical, SHA-256:
`5d4197fe39e0bf465e61994ec7cf17632a89b60a7a2128bba2c7748d4f647c34`.
All 39 referenced historical image assets are copied byte-for-byte into
`reference/assets/images/`, with source-path SHA-256 values in the manifest.
Growth-photography attribution is preserved alongside those assets. The map
stylesheet originates in historical `resources/css/farm-map.css` and its
selectors are restricted to `[data-reference-shell]`.

No existing operational frontend, app CSS, npm/composer dependencies, or
production environment files are changed. `routes/web.php` loads the dedicated
`routes/reference.php` namespace and its static-page controller. Existing route
names, actions, middleware, and policies are preserved; no migration is needed.

## Route/page contract

| GET URL | Inertia component |
| --- | --- |
| `/referensi` | `reference/Dashboard` |
| `/referensi/kebun` | `reference/Kebun` |
| `/referensi/kebun/detail?id=kebun-001` | `reference/KebunDetail` |
| `/referensi/kebun/aktivitas?kebun=kebun-001` | `reference/KebunAktivitas` |
| `/referensi/aktivitas` | `reference/Aktivitas` |
| `/referensi/rincian-aktivitas` | `reference/RincianAktivitas` |
| `/referensi/laporan` | `reference/Laporan` |
| `/referensi/finance` | `reference/Finance` |
| `/referensi/ai` | `reference/Index` |

These routes require the existing `auth` + `verified` flow. The backend supplies
`reference: { sourceCommit, readOnly: true, basePath: '/referensi' }`, with the
exact source commit above. No fixture authenticates anybody, provisions a team,
or bypasses an authorization check.

Every page explicitly uses `defineOptions({ layout: [] })` to opt out of
Inertia's live `AppLayout` fallback. This is a non-null, empty layout list;
no `app.ts` modification is necessary. Tests inspect all nine layout options
and render each component with its historical shell. Browser integration must
also confirm no admin sidebar.

## Isolation and intentional deviations

- All historical `@/` imports are remapped into `@/reference/`; runtime UI
  dependencies come from the existing installed packages. No shared Vue
  component is imported by the snapshot.
- `inertia.ts` is a local adapter, not a router interceptor or global patch.
  Its Link always performs GET through `referenceUrl()`, which only accepts
  the nine reference paths, canonicalizes historical home/bypass to reference
  home/AI, and confines nested `back` links. Unknown, external, malformed,
  traversal, and CRUD/export URLs fall back to `/referensi`.
- Literal historical hrefs and history filter URLs are prefixed too; the only
  intentional navigation out is **Kembali ke aplikasi**, a normal `/` link.
  A reference Laporan link is added to the banner because historical bottom
  navigation has five entries, not six.
- Local `usePage()` preserves the current reference URL for filters but gives
  historical components only a static display identity and reference metadata.
  It never reads operational data/current account details from shared props.
  This display identity is not an authentication mechanism.
- `farmDemoService.loadFarmDemoState()` retains historical presentation logic
  and uses fresh clones of the exact bundled seed after a 300 ms display delay.
  It never reads or writes browser storage. `useDemoStorage` is a reference-only
  inert compatibility module; reset controls explain they cannot reset app data.
- `fixtureData.ts` resolves historical JSON image paths to versioned Vite asset
  URLs. No image depends on today's mutable `public/images` directory.
- Dashboard invitations, real profile/logout actions, notifications, and toasts
  are excluded. Header icons keep the historical layout but notification is
  explicitly disabled. No account mutation/request is wired.
- Historical Finance at this commit depended on database props. `financeFixture.ts`
  adapts the exact historical finance dataset to that historical display contract
  (decimal strings, dates, farm labels, monthly points). It does not claim a
  database source. Chart and transaction tabs still render. Server-side finance
  filtering shows an explicit disabled explanation; it does not request live
  records. Export is disabled. CRUD resource tabs are not exposed.
- AI retains the exact historical `AiChatHeader`, `AiMessageList` idle view,
  quick-prompt labels, and `AiChatComposer` visual components. A disabled HTML
  fieldset covers the full interaction area, with an explanation outside it.
  Sending, microphone, upload, quick-prompt activation, conversation/history
  mutation, playback, and feedback cannot activate. No chat/voice/telemetry/API
  composable is imported by the AI entrypoint. There are no fabricated replies.
  The idle message list has no assistant messages. Pure historical presentation
  helpers used by its child cards remain in the import closure.
- Leaflet retains the historical bundled farm-coordinate markers, fit/zoom,
  status legend and preview navigation. Remote basemap tiles and satellite
  providers are intentionally disconnected and visibly explained; no imaginary
  replacement map data is generated. Satellite preview is a coordinate notice.
- Laporan continues to compute its display from the historical fixture. Export
  and reset actions show read-only explanations, not official-report downloads.
  Its generated label is fixed to the snapshot date. No browser print/export
  or storage reset is performed. Legacy print styles remain component-scoped;
  the page's scrollbar selectors are additionally reference-shell scoped.
- The historical semantic mobile theme and Composition API remain. The shared
  shell has a dated, visible read-only banner. Original mobile sizing, cards,
  filters, chart styling, photos, and navigation are preserved except for the
  safety deviations above.

## Verification

No new package was installed for this snapshot. Node 18 on the host is too old
for the repository's frontend toolchain, so check/build/Vitest ran with the
parent-provided Node 22 Docker helper. The standalone URL tests also run on
host Node 18 and need no installed npm dependencies.

### URL-helper test-first evidence

Command: `node --test resources/js/reference/url.node-test.mjs`.

1. RED before implementation: **1 failed, 0 passed**. Expected `/referensi`,
   received `undefined` (missing helper behavior).
2. Minimal GREEN: **1 passed, 0 failed**.
3. Extended RED for path/query/nested-back behavior: **2 failed, 2 passed**.
4. Full URL boundary GREEN: **4 passed, 0 failed**. Includes all nine paths,
   idempotency, query/hash retention, bypass-to-reference-AI, external links,
   unsafe schemes, unknown operations, traversal, malformed inputs, and nested
   back-link confinement.

### Integration evidence

- `npm run types:check`: passed.
- `npm run check -- --no-fmt`: passed, no warnings or lint errors.
- Targeted reference Vitest: **14 passed**; includes nine server renders,
  explicit empty-layout options, dated banner, no operational account props,
  disabled AI fieldset and exact-fixture clone/asset resolution.
- The asset-resolution regression test initially failed because historical
  `data/kebun.ts` used a relative raw JSON import; it was redirected to the
  isolated asset resolver and the same test then passed.
- Independent review caught a double-resolved header logo that silently selected
  a valid fallback image. A rendered-logo regression test failed first, the header
  was corrected to resolve once, and both the test and real mobile browser then
  confirmed the versioned historical logo.
- Full `LARAVEL_BYPASS_ENV_CHECK=1 npx vitest run`: **9 files, 72 tests passed**.
- `npm run build`: passed. Existing optional `fontaine` and large-chunk build
  notices remain; no package was added merely to suppress these notices.
- Fixture and all 39 copied image bytes were compared against exact historical
  source and matched.

### Browser and backend integration

- All nine pages were opened in a real browser against an isolated authenticated
  test app at mobile widths 360, 390, and 440 px. No console errors, broken
  historical images, horizontal page overflow, admin sidebar, or operational API
  requests were observed. Adding the global dark class at 440 px preserves the
  historical shell's intentionally light palette without rendering errors.
- Clicking Dashboard -> Kebun -> Detail -> filtered Aktivitas -> Rincian kept
  every URL, query and back parameter under `/referensi`.
- All 12 AI controls match the browser's `:disabled` state; clicking the disabled
  buttons produced no requests. This checks isolation, not live AI correctness.
- Five Laravel integration tests passed with 667 assertions against a guarded,
  disposable PostgreSQL instance. They cover existing-login admission for all
  four roles, trusted page selection, no domain-record provisioning, rejection
  of mutation URLs even with a tenant named `referensi`, and unchanged existing
  route/policy behavior. Existing email-verification behavior is not changed.
- A runtime comparison verified all 152 existing named route URIs, methods,
  actions, and middleware are identical to the deployed baseline.
- A full local PHPunit run on the host's standalone PHP binary exceeded its
  240-second limit without a result. The focused tests passed; full GitHub CI
  remains the required merge/deployment gate.

The loopback browser fixture/router and its disposable database are test tooling
outside the deployed application. No historical auto-login is shipped.

## Exact historical source modules in the import closure

Paths below are relative to historical `resources/js/`. Page modules are
adapted into `pages/reference/`; all other modules are copied under `reference/`.
Local-only adapters/tests listed afterwards did not exist in the old commit.

- `components/CommodityCardDashboard.vue`
- `components/MetricInfoTooltip.vue`
- `components/StatCardDashboard.vue`
- `components/farmer/ActivityMetadataCard.vue`
- `components/farmer/ActivityPeriodFilter.vue`
- `components/farmer/ActivityPhotoGallery.vue`
- `components/farmer/ActivityReportSection.vue`
- `components/farmer/ActivityTypeFilterDropdown.vue`
- `components/farmer/AiChatComposer.vue`
- `components/farmer/AiChatHeader.vue`
- `components/farmer/AiFinanceCard.vue`
- `components/farmer/AiInfographicCard.vue`
- `components/farmer/AiMessageList.vue`
- `components/farmer/CommodityIncome.vue`
- `components/farmer/DashboardHeader.vue`
- `components/farmer/FarmerBottomNav.vue`
- `components/farmer/FarmerMobileShell.vue`
- `components/farmer/FarmerNavIcon.vue`
- `components/farmer/FinanceNavigation.vue`
- `components/farmer/FinancialOverviewDashboard.vue`
- `components/farmer/KebunFilterDropdown.vue`
- `components/farmer/KebunMap.vue`
- `components/farmer/KebunMapPreview.vue`
- `components/farmer/KebunSatelliteMap.vue`
- `components/farmer/RecapFarmCondition.vue`
- `components/farmer/ReportPrintDocument.vue`
- `components/farmer/RevenueChart.vue`
- `components/ui/button/Button.vue`
- `components/ui/button/index.ts`
- `components/ui/dialog/Dialog.vue`
- `components/ui/dialog/DialogClose.vue`
- `components/ui/dialog/DialogContent.vue`
- `components/ui/dialog/DialogDescription.vue`
- `components/ui/dialog/DialogFooter.vue`
- `components/ui/dialog/DialogHeader.vue`
- `components/ui/dialog/DialogOverlay.vue`
- `components/ui/dialog/DialogScrollContent.vue`
- `components/ui/dialog/DialogTitle.vue`
- `components/ui/dialog/DialogTrigger.vue`
- `components/ui/dialog/index.ts`
- `components/ui/tooltip/Tooltip.vue`
- `components/ui/tooltip/TooltipContent.vue`
- `components/ui/tooltip/TooltipProvider.vue`
- `components/ui/tooltip/TooltipTrigger.vue`
- `components/ui/tooltip/index.ts`
- `composables/useAppearance.ts`
- `composables/useCarouselDrag.ts`
- `composables/useDemoStorage.ts`
- `data/activityLogs.ts`
- `data/farmDemo.ts`
- `data/farmDemoData.json`
- `data/farmerFinance.ts`
- `data/growthStages.ts`
- `data/kebun.ts`
- `lib/farmMap.ts`
- `lib/finance.ts`
- `lib/utils.ts`
- `pages/farmer/Aktivitas.vue`
- `pages/farmer/Dashboard.vue`
- `pages/farmer/Finance.vue`
- `pages/farmer/Index.vue`
- `pages/farmer/Kebun.vue`
- `pages/farmer/KebunAktivitas.vue`
- `pages/farmer/KebunDetail.vue`
- `pages/farmer/Laporan.vue`
- `pages/farmer/RincianAktivitas.vue`
- `services/activityLogService.ts`
- `services/aiChatService.ts`
- `services/aiIntentRouter.ts`
- `services/commoditySummaryService.ts`
- `services/farmDemoService.ts`
- `services/kebunService.ts`
- `types/aiChat.ts`
- `types/finance.ts`

## New isolation helpers and tests

- `resources/js/reference/assets.ts`
- `resources/js/reference/fixtureData.ts`
- `resources/js/reference/financeFixture.ts`
- `resources/js/reference/inertia.ts`
- `resources/js/reference/url.mjs`
- `resources/js/reference/url.d.mts`
- `resources/js/reference/url.node-test.mjs`
- `resources/js/reference/reference.test.ts`
- `resources/js/reference/provenance.json`

The complete new-file inventory is the two scoped frontend directories plus
this document; no existing operational frontend file is modified.

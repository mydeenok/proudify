# Proudify — Site Analysis & TALL Stack (Livewire) Adoption Plan

_Generated 2026-08-03. Updated same day with execution progress. Documentation only for this pass — no new code in the refresh._

---

## Status at a glance (what’s still pending)

| Phase / step | Status | Notes |
|---|---|---|
| **Phase 0** — Livewire setup + Alpine coexistence + `wire:navigate` | ✅ Done | Livewire `^3.8.3`, `config/livewire.php`, Vite ESM bundle |
| **Phase 1** — Notification bell | ✅ Done | `App\Livewire\NotificationBell` + `wire:poll.10s` + `Livewire::test` |
| **Phase 2** — Admin tables (Users → Certificates → Templates → UserSubscriptions) | ✅ Done | Four Livewire components + tests |
| **Step 1** — Tailwind CSS cleanup | ✅ Done | `tailwind-scrollbar@3` + `scrollbar-none` / thin track utilities |
| **Step 2** — Alpine conversion of `ui.js` / preview | ✅ Done | `ui.js` keeps only global form-loading helper |
| **Phase 3 / Step 6** — Tenant certificate list + bulk-upload history | ✅ Done | `CertificatesIndex` + `BulkUploadHistory` |
| **Step 6b** — Bulk-upload wizard as multi-step Livewire | ✅ Done | `BulkUploadWizard` + shared `BulkUploadWizardService` |
| **Plans / analytics filters** | ✅ Done | `SubscriptionPlansTable` + `AnalyticsDashboard` period/search filters |

**Bottom line:** Phases 0–3, Step 6b, and polish leftovers (scrollbar plugin, bell Livewire tests, plans/analytics filters) are shipped.

---

## Section 1 — Site Analysis

### Domain model

| Model | Role | Key relationships |
|---|---|---|
| `Certificate` | Issued credential | BelongsTo `User`, `Template`, optional `CertificateBatch` / `UserSubscription`; HasMany `CertificateVerification`; soft deletes; route key `uuid` |
| `CertificateBatch` / `CertificateBatchItem` | Bulk-upload job + per-row results | Batch BelongsTo `user`, `template`, `issuedBy`; HasMany `items`, `certificates`. Item BelongsTo `batch`, `certificate` |
| `Template` | Design (Fabric `canvas_json` + HTML) | BelongsTo `createdBy`; HasMany `certificates`; `scopeActive` |
| `Subscription` | Plan catalog | HasMany `UserSubscription`; pricing/limits helpers |
| `UserSubscription` | Tenant quota / billing | BelongsTo `user`, `subscription`; Razorpay fields; `isUsable()` |
| `User` | Tenant or admin | `isAdmin()` (`role === 'admin'`), `isActive()` (`status === 'active'`); relations to subscriptions, certificates |
| `EmailLog` | Transactional email audit | No Eloquent relations; scopes `sent` / `failed` |
| `CertificateVerification` | Public verify audit | BelongsTo `certificate` |

**Tenant vs admin split** (`bootstrap/app.php` aliases):

| Middleware | Class | Behavior |
|---|---|---|
| `approved` | `EnsureUserIsApproved` | Inactive users logged out → login |
| `admin` | `EnsureUserIsAdmin` | Non-admin → 403; wraps `routes/admin.php` |
| `tenant-only` | `RedirectAdminFromTenantRoutes` | Admin hitting tenant routes → `admin.dashboard` |

Certificates / templates / bulk-upload / profile are under `auth` + `approved` (admins may enter). Dashboard / subscriptions / purchase are `tenant-only`. Notifications are `auth` only.

### Current stack

| Piece | Version / notes |
|---|---|
| Laravel | 12.x |
| Auth | Breeze (session, OTP, SSO) |
| **Livewire** | `^3.8.3` (installed; Alpine served from Livewire ESM) |
| Alpine | App uses Livewire’s bundled Alpine (npm `alpinejs` no longer separately started) |
| Tailwind | 3.x + `@tailwindcss/forms` |
| Vite | Bundles app + Livewire ESM |
| Fabric.js | Template canvas builder |
| Spatie Browsershot | PDF / image render |
| Maatwebsite Excel | Bulk CSV/XLSX ingest |
| Razorpay | Billing + webhook |
| Endroid QR | Verification QR codes |

Originally T-A-L only; Livewire completes TALL for the surfaces converted below.

### Interaction patterns inventory

#### Pattern A — Alpine + fetch polling (async job status) — leave as-is

| Surface | Mechanism |
|---|---|
| `resources/views/certificates/show.blade.php` | Alpine `x-data` polls `certificates.status` JSON until ready; `regenerate()` restarts |
| `resources/views/bulk-upload/partials/status-content-*.blade.php` | Same shape; polls `bulk-upload.status-data` |

Small JSON patches; converting to `wire:poll` would re-render/diff full Blade every tick — performance regression for no UX win.

#### Pattern B — Full-page-reload GET filter forms — original pain point

Admin indexes in this bucket are **already converted to Livewire** (Phase 2). Remaining GET-filter surfaces:

| Surface | Params | Pattern today |
|---|---|---|
| Tenant `CertificateController::index` | `search`, `status` | ✅ Livewire (`CertificatesIndex`) |
| `BulkUploadController::history` | `status`, `user_id` (admin) | ✅ Livewire (`BulkUploadHistory`) |
| Tenant `templates/index` | `category` | Chip links (not a form) — optional later |

#### Pattern C — Notification bell — converted

Was `@php` unread count on every shell render (stale until next navigation). Now `App\Livewire\NotificationBell` with `wire:poll.10s`, mounted from admin/user shells.

#### Pattern D — Canvas-owned state — not a Livewire target

`admin/templates/builder.blade.php` + `Admin/CertificateBuilderController` save/publish JSON endpoints. Fabric.js owns the DOM; Livewire morphing would conflict.

### Filter / search / pagination survey

| Area | Controller / component | Params | View | Pattern | Tests |
|---|---|---|---|---|---|
| Admin users | `UsersTable` (was `UserController::index`) | `search`, `status`, `role`; paginate(20) | `admin/users/index` → `<livewire:admin.users-table />` | ✅ Livewire | `AdminUsersTableTest`, `AdminUserManagementTest` |
| Admin certificates | `CertificatesTable` | `search`, `status`, `template_id`, `period`; paginate(20) | `admin/certificates/index` | ✅ Livewire | `AdminCertificatesTableTest`, management tests |
| Admin templates | `TemplatesTable` | `search`; paginate(12) | `admin/templates/index` | ✅ Livewire | `AdminTemplateManagementTest` |
| Admin user-subscriptions | `UserSubscriptionsTable` | `search`; paginate(15) | `admin/user-subscriptions/index` | ✅ Livewire | `AdminUserSubscriptionsTableTest`, subscription tests |
| Admin subscription plans | `SubscriptionPlansTable` | `search`, `status`, `type`; no paginate | `admin/subscriptions/index` | ✅ Livewire | `AdminSubscriptionPlansTableTest`, `AdminSubscriptionPlanTest` |
| Admin analytics | `AnalyticsDashboard` | `period` (7/30/90) | `admin/analytics/index` | ✅ Livewire | `AdminAnalyticsTest` |
| Tenant certificates | `CertificatesIndex` | `search`, `status`; paginate(10) | `certificates/index` → `<livewire:certificates-index />` | ✅ Livewire | `CertificatesIndexTest` |
| Bulk-upload history | `BulkUploadHistory` | `status`, `user_id`; paginate(20) | `bulk-upload/history` → `<livewire:bulk-upload-history />` | ✅ Livewire | `BulkUploadHistoryTest` |
| Notification bell | `NotificationBell` + `NotificationController` | — | Shells → `<livewire:notification-bell />` | ✅ Livewire poll | `NotificationTest`, `NotificationBellTest` |

### Gaps found along the way (report, don’t fix here)

| Gap | Status |
|---|---|
| No tests for `Admin/TemplateController` / notification bell | ✅ Filled (`AdminTemplateManagementTest`, `NotificationTest`) |
| No dedicated `Livewire::test` for `NotificationBell` | ✅ Filled (`NotificationBellTest`) |
| `SubscriptionPlanController` / `AdminAnalyticsController` have no filter/date-range UI | ✅ Filled (`SubscriptionPlansTable`, `AnalyticsDashboard`) |
| `tailwind-scrollbar` plugin | ✅ Installed (`tailwind-scrollbar@3`) |
| Root `tmp_*.php` / `tmp_*.html` scratch files | Unrelated cleanup |
| `composer audit` advisories on guzzle / phpspreadsheet | Pre-existing; separate `composer update` |

---

## Section 2 — TALL Completion Plan (phased)

Each phase is independently shippable. Livewire components coexist with untouched Blade indefinitely — no big-bang cutover.

### Phase 0 — Setup ✅ Done

| Item | Detail |
|---|---|
| Files | `composer.json` (`livewire/livewire ^3.8.3`), `config/livewire.php` (`inject_assets => false`), `resources/js/app.js` (Livewire ESM + `Livewire.start()`), `components/head.blade.php` (`@livewireStyles` / `@livewireScriptConfig`) |
| Why safe | Additive; pages without Livewire components unchanged |
| Alpine note | Do **not** double-register Alpine or `@alpinejs/persist` — Livewire’s bundle already includes them; double-register threw `$persist` redefine and aborted all Alpine on the page |
| Rollback | Remove package + layout tags; revert `app.js` import |

### Phase 1 — Notification bell ✅ Done

| Item | Detail |
|---|---|
| Files | `app/Livewire/NotificationBell.php`, `resources/views/livewire/notification-bell.blade.php`, shells; deleted `components/notification-bell.blade.php` |
| Why | Highest value / lowest surface; unread count was stale until next full navigation |
| Tests | `tests/Feature/NotificationTest.php` (6 HTTP cases) |
| Rollback | Restore Blade component; drop Livewire include |

### Phase 2 — Admin tables ✅ Done

Suggested order was Users → Certificates → Templates (tests first) → UserSubscriptions — **all shipped**.

| Component | Files | Params / behavior |
|---|---|---|
| `Admin\UsersTable` | class + `livewire/admin/users-table.blade.php` | `search`, `status`, `role`; `#[Url]`; mutations stay on controller POSTs |
| `Admin\CertificatesTable` | same pattern | `search`, `status`, `template_id`, `period`; Alpine checkbox select-all for bulk download |
| `Admin\TemplatesTable` | same pattern | `search`; tests written before conversion |
| `Admin\UserSubscriptionsTable` | same pattern | `search`; cancel form fixed to POST + `@method('PATCH')` |

Controllers’ `index()` methods are thin view returns. Rollback = put query logic back in controller + restore GET form Blade.

### Phase 3 — Tenant-facing lists ✅ Done

#### 3a — `CertificateController::index` → `App\Livewire\CertificatesIndex`

| | |
|---|---|
| **Files** | `app/Livewire/CertificatesIndex.php`, `resources/views/livewire/certificates-index.blade.php`, thin `certificates/index.blade.php` |
| **Params** | `search`, `status` (`active`/`expired`/`revoked`); `#[Url]`; Alpine list/grid `$persist` kept on root |
| **Tests** | `tests/Feature/Certificates/CertificatesIndexTest.php` (tenant isolation + search + status + query-string) |

#### 3b — `BulkUploadController::history` → `App\Livewire\BulkUploadHistory`

| | |
|---|---|
| **Files** | `app/Livewire/BulkUploadHistory.php`, `resources/views/livewire/bulk-upload-history.blade.php`; deleted `partials/history-content.blade.php` |
| **Params** | `status`, admin-only `user_id`; tenant scoped to own batches |
| **Tests** | `tests/Feature/BulkUpload/BulkUploadHistoryTest.php` |

### Explicitly out of scope

| Surface | Reason |
|---|---|
| Fabric.js builder / `CertificateBuilderController` | DOM ownership conflict with Livewire morph |
| Pattern A status polling | JSON fetch already optimal |

### Step 6b — Bulk-upload wizard ✅ Done

`App\Livewire\BulkUploadWizard` + `App\Services\BulkUploadWizardService`:

- Tenant: template → upload → map → review (no reload between steps)
- Admin: setup (org + template + file) → map → review
- Confirm redirects to Alpine+fetch status page (unchanged)
- HTTP POST routes kept as thin wrappers for BC; GET pages host Livewire
- Re-map clears prior items before re-ingest
- Confirm rejects zero-pending batches server-side
- Tests: HTTP + Livewire paths in `BulkUploadWizardTest`

### Rollback philosophy

Any Livewire component can be removed while sibling pages stay Livewire or Blade. No shared “migration flag” required. Query-string `#[Url]` keeps bookmarks/HTTP tests compatible during conversion.

---

## Appendix — Execution log (Steps 1–5 already landed)

Detailed CSS/Alpine/Livewire install notes from the implementation pass:

- **Step 1** — Tailwind cleanup: custom shadow/glass/carousel/body/fade-up classes removed or inlined; scrollbar plugin deferred.
- **Step 2** — Alpine: intersect/persist/collapse via Livewire bundle; FAQ, billing, view-mode, copy, tabs, file drop, certificate preview converted; global form-loading left in `ui.js`.
- **Step 3** — Livewire 3.8.3 pinned (v4 tried same day, rolled back); Alpine double-init fixed; `wire:navigate` on shared nav components.
- **Step 4** — NotificationBell + poll.
- **Step 5** — Four admin Livewire tables + tests.
- **Step 6 / Phase 3** — ✅ Tenant `CertificatesIndex` + `BulkUploadHistory`.
- **Step 6b** — ✅ `BulkUploadWizard` multi-step Livewire (status page stays Alpine + fetch).

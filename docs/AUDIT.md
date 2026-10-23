# Codebase Audit — OpenBengkel vs. SPEC

Date: 2026-10-21 · Scope: SPEC section 10 checklist · Branch `main` @ `b4ffcbc` plus uncommitted changes in the working tree.
No code was changed for this audit.

**Verdict in one paragraph:** The app is a working single-shop Laravel + Inertia/Vue system with the right overall flow (customer → vehicle → work order → invoice). The UI pages are a usable starting point. The backend, however, does not meet any of the SPEC's non-negotiable rules: there is no tenant or branch concept, authorization is almost entirely missing on the server, money is stored as `decimal` and computed with floats, stock is changed directly in about ten places (and parts on invoices are deducted twice), there are no document numbers, no translations, and no real tests. Plan: keep the Inertia/Vue shell and most pages, refactor the master-data modules (customers, vehicles, parts, suppliers, expenses), and rewrite the transactional core (work orders, invoices, payments, stock) behind Services. Do this through additive migrations that preserve the existing data.

---

## 1. Current state summary

### Versions (from `composer.lock`, `node_modules`, and local CLI)
| Component | Installed | SPEC / CLAUDE.md says |
|---|---|---|
| PHP (local, Herd) | 8.3.33 | 8.3+ ✅ |
| Laravel | **13.5.0** | 12 ❗ (CLAUDE.md is out of date) |
| inertiajs/inertia-laravel / @inertiajs/vue3 | 2.0.24 / 2.3.18 | — |
| Vue | 3.5.30 | 3 ✅ |
| Vite | 8.0.1 | — |
| Tailwind | 3.4.19 (plus `@tailwindcss/vite` 4 in devDeps, unused) | ✅ |
| tightenco/ziggy | 2.6.2 | — |
| laravel/socialite (Google login) | 5.26.1 | not in SPEC |
| UI kit | shadcn-vue style (`reka-ui`, `class-variance-authority`, `lucide-vue-next`, `vue-sonner`), plus the original Breeze components | — |
| Charts | chart.js 4 + vue-chartjs | — |
| MySQL | 8.0.41 (from the dump header), port 3308 locally | 8 ✅ |
| spatie/laravel-permission | **not installed** | required |
| laravel-vue-i18n | **not installed** | required |
| Pest / Larastan | **not installed** (PHPUnit 12 in `require-dev`) | required |
| TypeScript / vue-tsc | **not installed**, no `tsconfig.json` | required |

### Architecture
- **Inertia.js v2 monolith**, not an SPA + API. Controllers return `Inertia::render()`. Routes are named and used in Vue through Ziggy (`route()`). A few JSON endpoints serve autocomplete (`/parts/search`, `/api/customers/search`, `/api/vehicles/search`, `/parts/quick-add`) under the same session auth.
- Started from **Laravel Breeze (Vue)**. The auth controllers and pages are Breeze defaults, plus Google login via Socialite.
- Every Vue page uses `<script setup lang="ts">`, but there is no TypeScript toolchain. Vite only strips the types, so nothing is type-checked, and props are mostly `any`. The entry file is `resources/js/app.js`.
- Roles: a single `users.role` enum (`admin | staff | finance`), checked by two middlewares (`admin`, `staff`) and by `role === 'admin'` checks in Vue.

### Folder structure (actual)
```
app/Http/Controllers/            14 flat controllers (Customer, Vehicle, WorkOrder, WorkOrderItem, Invoice, Part,
                                 StockEntry, Supplier, Mechanic, Expense, Dashboard, Profile) + Admin/ (Dashboard,
                                 Report, Setting, User) + Auth/ (Breeze + Google)
app/Http/Middleware/             AdminMiddleware, StaffMiddleware, HandleInertiaRequests
app/Http/Requests/               only Breeze's LoginRequest + ProfileUpdateRequest
app/Models/                      16 models; no Services/, Policies/, Support/
app/Traits/UserTracking.php      fills created_by / updated_by
database/migrations/             43 migrations (3 uncommitted)
database/db_template.sql         HeidiSQL dump with schema AND data (committed)
resources/js/Pages/              ~30 pages; Components/ (Breeze) + Components/ui/ (shadcn-vue)
tests/                           Breeze defaults only
```

### Features that exist
| Area | What is there |
|---|---|
| Auth | Login, logout, forgot/reset password, email verification pages, profile edit/delete, **public registration (open)**, Google OAuth login for existing emails |
| Customers | List (search by name, phone, or plate; paginated), detail page with vehicles, create/update. Three phone fields, each globally unique. No delete, no soft deletes. |
| Vehicles | Add to customer, update, soft delete, "medical record" page with work order history. `last_serviced_at` / `last_serviced_odometer` drive 3-month service reminders (WhatsApp link) on the staff dashboard. |
| Work orders | Check-in by plate search, a "quick check-in" that creates customer + vehicle + WO in one form, WO detail with a task checklist (`work_order_items`), parts added by SKU or search (deducts stock immediately), qty edit and removal, mechanic assignment (one mechanic per WO), monitor list with date/status filter. Statuses: `Pending, Proses, Selesai, Batal`. |
| Invoices | One invoice per WO (`updateOrCreate`), built in Vue from WO parts plus free "lain-lain" lines (services, fees). Per-line discount, global discount, draft/paid/unpaid, due date, partial `amount_paid`, a 3% card/debit surcharge added in Vue, WhatsApp share link, public invoice page by UUID, receivables list with "settle", and "reopen" (admin only, writes `activity_logs`). No invoice numbers (`INV-#id`). No tax. |
| Inventory | Parts catalog (`type` Part/Service, buy/sell price, `discount_percent`, `stock`, `min_stock`), part detail with sales and purchase history, cheapest-supplier stats, quick-add, restock with weighted average cost, stock entries (goods received from a supplier, multiple lines), suppliers with purchase history. |
| Mechanics | Separate non-login table: name, phone, NIK, KTP photo upload, active flag. |
| Expenses | Current-month list, create, soft delete; free-text category. |
| Dashboards | Admin: monthly income/expense/profit, chart by week/month/year, mechanic car counts, low stock. Staff/finance: active WOs, receivables due, service reminders. |
| Reports | Admin monthly revenue/expense/margin, top items, daily chart. |
| Settings | Single `settings` row: shop name, address, 2 phones, logo. |
| Users | Admin CRUD of users with role. |
| **Uncommitted WIP** | `payments` table (polymorphic `payable`: invoice or stock entry), supplier-debt columns on `stock_entries`, `expenses.payment_source` + `settings.petty_cash_balance` (petty cash), the new `finance` role in middleware/layout. Migrations and models exist; **no controller or page uses `Payment` yet**. |

### What I could not verify
- **Tests:** `php artisan test` is not registered and `vendor/bin/phpunit` is missing. `vendor/` looks like a `--no-dev` install. I did not install anything.
- **Database:** the local MySQL on port 3308 was not running, so I could not check which migrations have run or compare the live schema with the migrations. The schema findings come from the migrations and `db_template.sql`.
- **Frontend:** `npm run build` **passes**. `npm run type-check` does not exist.

---

## 2. SPEC section 10 checklist, item by item

| # | Item | Finding |
|---|---|---|
| 1 | Versions; Inertia or API; structure | Laravel 13.5 / PHP 8.3 / Vue 3.5 / Inertia 2. Inertia monolith. Flat controller folder, no Services, Policies, or Support classes. |
| 2 | Tenant/branch concept | **None.** No table has `tenant_id` or `branch_id`. Settings is a single global row. All uniques are global: `customers.phone_1/2/3`, `vehicles.plate_number`, `invoices.uuid`. `parts.sku` is unique only through validation, not in the DB. |
| 3 | Roles / server-side authorization | One `role` column and two middlewares. **Most write routes only require `auth`.** The four Gates in `AppServiceProvider` are never called. The `routes/web.php:77` comment says "Protected by `$user->canManageData()` in Controllers", but `canManageData()` is never called. With the uncommitted change, the `staff` middleware lets every role through, so it no longer restricts anything. See must-fix M2. |
| 4 | Money columns, rounding in Vue | All money is `decimal(15,2)`, except `expenses.amount` (`int`) and `parts.discount_percent`. PHP uses floats (`Part::getFinalPriceAttribute`, the WAC in `PartController::restock`, `(float)` casts). **Invoice totals, line subtotals, and the 3% surcharge are computed in Vue** (`Invoices/Create.vue`), and the server recomputes from client-supplied prices. |
| 5 | Where business logic lives | In controllers (invoice totals, stock, WhatsApp text, WAC, status changes) and in Vue (totals, surcharge, status juggling). A few model accessors run queries. No services. |
| 6 | Hardcoded strings vs translations | **No translations at all.** No `lang/` directory. 41 hardcoded flash messages in PHP, and every Vue label is inline Indonesian (with some English). Shop-specific text is hardcoded too: "Bengkel Kalimas Lumas" in `DashboardController`, and "Transfer Anton / Martino" in `Invoices/Create.vue`. |
| 7 | Inventory: direct or via movements | **Direct.** `parts.stock` is incremented or decremented in `WorkOrderController` (add, change qty, remove, destroy), `InvoiceController` (store, reopen), `StockEntryController`, `PartController::restock`, and can be overwritten through `PartController::update`. No movements table, no row locking, and a transaction only in some places. |
| 8 | Document numbering | **None.** Invoices are shown as `INV-#{id}`. WOs have no number. Nothing is race-safe because nothing is numbered. |
| 9 | Validation / mass assignment | Inline `$request->validate()` in most actions; **`InvoiceController::store` and `settle` validate nothing.** Every model uses `$fillable` (good). `WorkOrder` allows `created_at` to be filled, and it is set from an unvalidated request date. |
| 10 | Tests / N+1 / indexes | Only Breeze tests. N+1 sources: `Customer::$appends['service_count']` (one query per row), `WorkOrder::$appends['parts_total']` (lazy-loads parts on every serialized WO), and the admin dashboard (two queries per day: about 62 per load in month view). No `preventLazyLoading`. No indexes on `work_orders.status/created_at`, `invoices.status/due_date`, `expenses.expense_date`, or `parts.sku`. Several lists are unpaginated (suppliers, mechanics, expenses, receivables, active WOs, restock, stock entry part picker, users). |
| 11 | Security | CSRF is fine (Inertia/axios XSRF). `v-html` is only used on Laravel paginator labels (low risk). No raw SQL with user input. `.env` is not committed. **Issues:** public registration is open, server-side authorization is missing, KTP scans are on the public disk, and the committed SQL dump contains real emails, password hashes, a remember token, and session IDs. Details below. |

---

## 3. Findings

### Must fix (security, data correctness, or blocks the architecture)

**M1. No tenancy or branch scoping.** Rules 1–2 of CLAUDE.md are unmet everywhere. Every table, unique key, query, and the settings model needs `tenant_id` (and `branch_id` for operational data). This is the base for everything else, so it goes first, through additive, backfilling migrations (section 5).

**M2. Authorization is not enforced on the server.** Any logged-in user can:
- delete vehicles, suppliers, mechanics, and expenses (no check in `destroy`)
- edit or delete parts and overwrite stock (`parts.update`/`destroy` sit in the `staff` group, which now lets every role through)
- settle any invoice (`InvoiceController::settle`)
- create or overwrite an invoice with any prices (`InvoiceController::store`)
- delete work orders (`WorkOrderController::destroy` checks `auth()->user()->is_admin`, an attribute that does not exist)
- upload a mechanic KTP: the role check only skips the file, but NIK and the other fields still update

Hiding links in `AuthenticatedLayout.vue` by role is the only "protection". Replace this with spatie permissions, Policies, and FormRequests using the SPEC section 2 permission names.

**M3. Public self-registration grants staff access.** `/register` is open (`routes/auth.php`). New users get the default role `staff`. `User` does not implement `MustVerifyEmail`, so the `verified` middleware passes. On a deployed instance, anyone can sign up and read every customer, invoice, and phone number. SPEC defers public sign-up until after the pilot, so close this now.

**M4. Invoice save trusts the client completely.** `InvoiceController::store` has no validation. Items, `price`, `discount`, `qty`, `status`, `amount_paid`, `payment_method`, `due_date`, and `work_order_id` all come from the request. Totals are summed from client prices. The 3% surcharge is a client-made line. `status` can be any string (`'Draft'` vs `'draft'` vs `'paid'`). Prices must come from the server (WO lines and catalog). Only users with `invoice.discount` may set discounts.

**M5. Stock is wrong by design: parts on invoices are deducted twice.**
1. `WorkOrderController::addPart` decrements `parts.stock` when a part is added to the WO.
2. `Invoices/Create.vue` sends those parts back with `part_id`, and `InvoiceController::store` decrements them **again**.
3. Re-saving an invoice gives back stock for the old lines and takes it again. `reopen` gives stock back, and the next save gives it back again for the same lines.

Combined with direct writes in about ten places (checklist item 7) and the stock field in the part edit form, current `parts.stock` values cannot be trusted. **Plan for a physical stock count before the pilot goes live** and load it as the opening balance through `StockService`.

**M6. Data-corrupting quick check-in.** In `WorkOrderController::storeUnified`:
- `Customer::updateOrCreate(['phone_1' => $request->phone_1], …)`: when the phone is left empty, this matches the **first existing customer with a null phone** and overwrites their name and address.
- `Vehicle::updateOrCreate(['plate_number' => …])` silently reassigns an existing vehicle to the new customer and overwrites its make and model, with no ownership history.

**M7. Money as `decimal` and float math.** This breaks rule 5. Convert to `BIGINT` Rupiah. Before converting, check for fractional values: the restock WAC writes fractional `buy_price` values.

**M8. No document numbers.** Rule 10. Needed for WO and INV, and historical invoices need numbers assigned during migration.

**M9. Broken code paths that will crash or report wrong numbers.**
- `WorkOrderController::destroy`: `$workOrder->parts` returns `Part` models, so `$woPart->part` is null and `$woPart->quantity` does not exist. Deleting any WO that has parts throws a 500. It also checks `'finished'`, a status that never exists.
- `WorkOrderController::adminIndex` eager-loads `customer`, but `work_orders` has no `customer_id`, so it is always null.
- `AdminDashboardController` counts active WOs with status `'In Progress'`, which never exists (the real value is `Proses`). It also uses a hardcoded low-stock threshold of 5 instead of `min_stock`.
- `ReportController` "top items" reads `invoice_items`, a table nothing writes to, so it is always empty. Its expenses are filtered by `created_at` while the dashboard uses `expense_date`, so the two disagree.
- `Invoice::calculateTotals()` writes `total_amount`, `final_amount`, and `global_discount`, which do not exist (dead code). The Vue pages read `existingInvoice.discount_total`, which also does not exist, so the global discount is lost when an invoice is reopened.
- `Invoice::getRouteKeyName()` returns `uuid`, so `PUT /invoices/{invoice}/settle` binds by UUID while `reopen` binds by `{invoice:id}`.

**M10. Migrations cannot rebuild the schema.**
- The dump's `invoice_details` has `type` and `discount` columns that no migration creates, but the code depends on them. A fresh `php artisan migrate` gives a schema the invoice code fails on.
- `2026_06_25_123548_update_role_enum_in_users_table` uses a MySQL-only `ALTER … MODIFY`. Tests run on SQLite (`phpunit.xml`), so the test suite cannot migrate.
- `2026_04_10_173452_add_uuid` uses the `Invoice` model inside a migration, which will break as the model changes.

Fix with a new "reconcile" migration that adds missing columns guarded by `hasColumn` (never edit migrations that already ran), and run tests on MySQL.

**M11. Mechanic KTP (national ID) scans are publicly reachable.** They are stored on the `public` disk (`ktp_mechanics/`), so anyone with the URL can fetch them without logging in. Move them to a private disk and serve them through an authorized download.

**M12. Personal data and credentials committed to git.** `database/db_template.sql` contains real email addresses, bcrypt password hashes, a remember token, and session IDs (customer rows are mostly Faker data). Remove it from the repo, rotate the affected passwords, and decide whether to scrub history (see question 14).

**M13. No test or tooling baseline.** Dev dependencies are not installed, there is no Pest, Larastan, or `vue-tsc`, and the only tests are Breeze's. Every refactor below needs characterization tests first, especially for invoice totals and stock.

### Should fix

- **S1. Timestamps are stored in WIB, not UTC.** `config/app.php` sets `timezone = Asia/Jakarta`, so every `created_at` is local time (breaks rule 11). Switching to UTC needs a one-off data migration that shifts timestamp columns by −7h. Date-only columns (`expense_date`, `due_date`, `entry_date`) stay as they are. This must happen in the same release as the config change.
- **S2. Business logic in controllers and Vue.** Move it to `InvoiceService`, `StockService`, `WorkOrderService`, and `PaymentService`. Vue should only display totals the server returns.
- **S3. FormRequests.** Replace inline validation, with `authorize()` using permissions.
- **S4. i18n.** Add `laravel-vue-i18n`, `lang/id.json` and `lang/en.json`, and a per-user `locale`. Move all strings, including the 41 flash messages.
- **S5. N+1 queries and pagination.** Remove the querying `$appends` (`service_count`, `parts_total`). Use `withCount`/`withSum` instead. Collapse the dashboard loops into grouped queries. Paginate every list. Enable `Model::preventLazyLoading()` outside production.
- **S6. Indexes.** Add composite indexes starting with `tenant_id`/`branch_id`, plus `status`, dates, `sku`, and `plate_number`.
- **S7. Audit log.** `activity_logs` is written in exactly one place (invoice reopen). Replace it with SPEC `audit_logs` written by a shared mechanism, and migrate the existing rows.
- **S8. Customers have no soft deletes.** The SPEC requires them. Customer phones should be normalized to `+62…`. The three globally unique phone columns will clash across tenants.
- **S9. Shop-specific hardcoding.** The shop name in the reminder text, named bank accounts as payment methods, and the 3% surcharge rates should become branch settings or data.
- **S10. Settings bugs.** The logo path is stored as `/storage/logos/x` and then prefixed again with `asset('storage/'.…)`, so the URL is broken. `shop_phone2` is never saved.
- **S11. The only admin can delete themselves** through Profile → Delete account. SPEC requires at least one active owner.
- **S12. Make TypeScript real.** Add `typescript`, `vue-tsc`, `tsconfig.json`, and an `npm run type-check` script, and rename `app.js` to `app.ts`. Type the Inertia page props.
- **S13. Dead and duplicate code.** `invoice_items` table and model references, `WorkOrderController::createInvoice`/`searchParts` (no routes), `Pages/Dashboard.vue` (Breeze default), two component systems (Breeze `Components/*` and shadcn `Components/ui/*`), Tailwind 4 vite plugin installed alongside Tailwind 3, and the closure route `/api/vehicles/search`.
- **S14. Quick-add part (`PartController::quickStore`)** returns any existing part by SKU before validation. It is fine as a feature, but it should go through `PartService` and a permission check.
- **S15. Uncommitted WIP.** The polymorphic `payments` table conflicts with the SPEC's `payments` (branch-scoped, `invoice_id`, integer amount, method enum, `received_by`). Do not run these three migrations anywhere persistent before deciding question 9.
- **S16. Ops (pilot launch items).** No error tracking, no backup setup, and the queue/cache use the `database` driver (fine for the pilot; SPEC says Redis).

### Fine as is

- Inertia + Vue 3 `<script setup>` + Tailwind + Ziggy. The Vite build passes. This matches the target stack.
- The shadcn-vue style `Components/ui` kit (reka-ui, lucide, vue-sonner) is a reasonable base for shared UI components.
- CSRF protection through the default `web` middleware. Every state-changing route is POST, PUT, PATCH, or DELETE.
- No raw SQL with user input. `DB::raw` is only used for static aggregates, and `LIKE` searches use bindings.
- `v-html` only renders Laravel paginator labels (server-generated, not user content). Replace it when the shared paginator component is built; not urgent.
- Breeze login: throttling in `LoginRequest`, session regeneration, hashed passwords. Google login only signs in emails that already exist.
- `$fillable` on every model; no `$guarded = []`.
- Soft deletes on vehicles, parts, suppliers, users, work orders, expenses, and mechanics.
- `work_order_parts.price_sold` and `cost_at_sale` snapshot price and cost at the time of use. This matches the SPEC's `unit_price`/`unit_cost` snapshots.
- `UserTracking` (`created_by`/`updated_by`) is useful and can stay next to the audit log.
- The public invoice link uses an unguessable UUID, which is a good base for the deferred WhatsApp sharing.
- The service reminder idea (`last_serviced_at`) is deferred in the SPEC but cheap to keep.

---

## 4. Feature verdicts

| Feature | Verdict | Reason |
|---|---|---|
| Auth (Breeze login, logout, forgot/reset) | **Keep** | Matches MVP. Add `locale` and `tenant_id`, an `is_active` check, and rate limiting on forgot-password. |
| Public registration | **Remove for MVP** | SPEC defers sign-up; it is currently a security hole (M3). Replaced by `tenant:create` and owner-created users. |
| Google login | **Keep or remove: question 11** | Not in the SPEC. Harmless if kept: it only signs in emails that already exist. |
| Customers | **Refactor** | Pages and search are good. Add tenant scope, soft deletes, `type`, `whatsapp`, `email`, `npwp`, phone normalization, and per-tenant uniques. Map `phone_1/2/3` to `phone`/`whatsapp` (question 13). |
| Vehicles | **Refactor** | Rename `brand` → `make` and `chassis_number` → `vin`. Add `last_odometer`, `transmission`, `fuel_type`, and `vehicle_ownerships`. Add `PlateNumber` normalization and `unique(tenant_id, plate_number)`. Keep the history page. |
| Quick check-in (customer + vehicle + WO) | **Rewrite backend, keep UI** | Its `updateOrCreate` corrupts data (M6). The idea is good for the front desk: rebuild it on `CustomerService`/`WorkOrderService` with an explicit "existing customer or vehicle?" step. |
| Work orders | **Rewrite backend, refactor UI** | SPEC needs `number`, `branch_id`, `customer_id`, the full status flow, a mechanic pivot, priced `work_order_services`, and stock via `StockService`. The current controller mixes stock, status, and invoicing, and has crashing paths. `CheckIn.vue`, `Show.vue`, and the monitor page are reusable. |
| WO task checklist (`work_order_items`) | **Question 6** | Not in the SPEC (which has priced `work_order_services`). Either fold it into services or keep it as a checklist. |
| Invoices | **Rewrite** | Missing: numbers, tax, revisions, `InvoiceService::revise()`, server-side totals, the payments table, and correct stock. The current flow double-deducts stock and trusts client prices. Keep the layout ideas from `Create.vue` and `PublicView.vue`. |
| Receivables + settle | **Rewrite** | Becomes "invoices with status unpaid/partial" plus recording `payments` with `payment.create`. The current `settle` has no checks and overwrites `amount_paid`. |
| Invoice reopen | **Replace** | Becomes `InvoiceService::revise()` (reason, revision snapshot, stock diff). |
| Parts catalog | **Refactor** | Keep the pages. Move `stock`/`min_stock` to `part_branch_stock`, make `sku` unique per tenant, use integer prices, rename `buy_price` → `cost_price`, and move `type = Service` rows to `service_catalog`. `discount_percent`: question 8. |
| Restock (`PartController::restock`, WAC) | **Rewrite into `StockService`** | Writes stock directly with float math. Purchases go through stock entries instead, so it may become redundant. |
| Stock entries (goods received) | **Refactor** | Good fit for SPEC "stock in" (`purchase` movements). Route each line through `StockService`, compute `total_cost` on the server, and add `branch_id`. |
| Suppliers | **Keep (light refactor)** | Close to the SPEC. Add `tenant_id`, pagination, and phone format. The extra columns (sales name, several phones) can stay. |
| Supplier debt (uncommitted) | **Question 9** | Not in the SPEC. |
| Mechanics | **Question 5** | SPEC models mechanics as users with the Mechanic role. Current mechanics are non-login records with NIK and KTP. |
| Expenses | **Refactor** | Add `branch_id`, `expense_categories`, receipt upload (S3), update with audit log, integer amount, pagination, and any-month filter. Petty cash: question 9. |
| Admin dashboard | **Rewrite** | Wrong statuses, about 62 queries per load, no branch or permission scoping. Rebuild against the SPEC section 7 dashboard with `dashboard.view_financials`. Reuse the chart components. |
| Staff dashboard | **Refactor** | Useful operational view. Scope it to the branch, paginate, and fix the statuses. |
| Reports | **Defer (hide)** | Reports are "after pilot", and the current one reads the wrong table. Leave it behind a permission until rebuilt. |
| Settings | **Refactor** | Split into tenant profile and branch settings (logo, tax, invoice footer, timezone, code). Fix the logo path bug. |
| Users | **Refactor** | Move to spatie roles per tenant, `branch_user`, `is_active`, the owner safeguard, and the permission-matrix UI. |
| Activity log | **Replace** | Becomes `audit_logs` (old/new values, reason, ip, branch). Migrate the existing rows. |
| WhatsApp invoice link and service reminders | **Keep, park** | Deferred in the SPEC. They already work, so keep them behind a setting and do not extend them for now. |

---

## 5. Migration plan

> **Superseded (2026-10-24):** the pilot shop is not using the app, so there is no production data to preserve. The local database is dev data only, and the repo cannot rebuild it anyway (7 missing migration files). **New plan:** replace `database/migrations/` with a clean baseline that matches SPEC section 4, written in dependency order as part of Phase 0. Seed it with `tenant:create` plus demo data. Bring the shop's real customers, vehicles, and parts in through the MVP CSV import. The step-by-step plan below is kept only as reference for the field mappings. Once the pilot goes live, the normal rule applies again: never edit a migration that has run in production.

### Original data-preserving plan (reference only)

Rules for every step: new migrations only (never edit ones that already ran), back up before each deploy, backfill in the same migration that adds a nullable column, and only add `NOT NULL` or tighten uniques in a later migration once the backfill is verified. Each step ships with tests.

**Step 0 — Safety and tooling**
1. Take a production DB backup and get a restore test working (question 1).
2. `composer install` with dev deps. Add Pest, Larastan (level 6), the Pint config, `typescript` + `vue-tsc` + `tsconfig.json`, and `npm run type-check`.
3. Switch tests to MySQL (or make the enum migration driver-aware in a *new* migration). Add a **reconcile migration** that adds `invoice_details.type`/`discount` if missing.
4. Write characterization tests for today's invoice totals and stock behaviour before changing them.
5. Close `/register` (M3). Move KTP files to a private disk (M11). Remove `db_template.sql` from the repo (M12).

**Step 1 — Tenancy and branches**
1. Create `tenants` and `branches`. Insert tenant #1 and branch #1 from the current `settings` row (name, address, phones, logo).
2. Add nullable `tenant_id` to every tenant-owned table, and `branch_id` to `work_orders`, `invoices`, `expenses`, and `stock_entries`. Backfill with 1/1. Make them `NOT NULL` with composite indexes in a follow-up migration.
3. Replace global uniques with per-tenant uniques: `(tenant_id, plate_number)`, `(tenant_id, sku)`, and customer phones.
4. Add `BelongsToTenant`/`BelongsToBranch`, the active-branch middleware, `users.tenant_id`, `branch_user`, `all_branches`, and `locale`.

**Step 2 — Permissions**
Install spatie with teams. Seed the SPEC permissions and default roles per tenant. Map existing users with a data migration: `admin` → Owner, and `finance`/`staff` → see question 4. Keep `users.role` until the switch is verified, then drop it.

**Step 3 — Money to integer Rupiah**
For each `decimal` money column, first report rows where `col <> ROUND(col)`. Then add a `BIGINT` shadow column, backfill with `ROUND(col)` (half-up), swap the names, and drop the old column in a later migration. Columns: parts, work_order_parts, invoices, invoice_details, stock_entries, stock_entry_items, part_supplier, and expenses (`int` → `bigint`).

**Step 4 — Stock ledger**
Create `part_branch_stock` and `stock_movements`. Seed `part_branch_stock` from `parts.stock` and `min_stock`, with one `adjustment` movement per part noted "saldo awal (migrasi)". Then **replace it with the physical count** (question 12) as a second adjustment, so the correction stays visible. Drop `parts.stock` once all writes go through `StockService`.

**Step 5 — Work orders**
Add `number`, `customer_id` (backfilled from the vehicle), `started_at`/`completed_at`, and the new status column. Map `Pending` → `queued`, `Proses` → `in_progress`, `Batal` → `cancelled`, and `Selesai` → `invoiced` if an invoice exists, otherwise `completed`. Move `mechanic_id` into the `work_order_mechanic` pivot. Rename `complaints` → `complaint`. Make `odometer` an integer (cast the current strings; log the ones that fail). Backfill numbers per branch and year by `created_at`, using `DocumentNumber` sequences seeded with the last value. Handle `work_order_items` according to question 6.

**Step 6 — Invoices and payments**
Create `invoice_lines`, `invoice_revisions`, and the SPEC `payments`. Copy `invoice_details` → `invoice_lines`: map `type` (`part`/`service`/`fee` → `part`/`service`/`other`), and keep `discount` as `discount_amount`. For each invoice with `amount_paid > 0`, create one `payments` row (method mapped from `payment_method`, `paid_at = updated_at`, note "migrated"). Recompute `status` from `amount_paid` vs `total`. Set `tax_enabled = false` and `revision_no = 0`. Backfill `number` by date. Handle draft invoices according to question 7. Drop `invoice_items` if it is empty.

**Step 7 — Remaining modules**
Customers (soft deletes, phone normalization with a conflict report), vehicles (renames and ownership rows from the current owner), expenses (categories from the distinct `category` values), and `activity_logs` → `audit_logs`.

**Step 8 — UTC switch**
Shift every `timestamp`/`datetime` column by −7h in one migration and set `app.timezone = UTC`, with display in the branch timezone, in the same deploy.

---

## 6. Answers so far (2026-10-24)

| Question | Answer |
|---|---|
| 1. Live shop | Bengkel Kalimas Lumas is the pilot shop and is **not using the app yet**, so there is no production data (see section 5). |
| 4. Roles | Owner (the shop owner), Front Desk, Finance. Approved; written into SPEC section 2. |
| 5. Mechanics | Records without a login. NIK and KTP are kept, encrypted and on a private disk (SPEC section 4). |
| 9. Supplier debt and petty cash | MVP scope. Design approved; written into SPEC sections 4, 7, 9. |
| New | Customers can pay one invoice with several methods in one checkout. Designed in SPEC section 4 (`payment_receipts` + `payments` + `payment_methods`). This also answers questions 8 (surcharge) and 10 (named accounts). |
| New | UX, security, and engineering principles added as SPEC sections 8.1–8.3. |
| 14. Repo visibility | **Public.** M12 progress: the dump is untracked and `*.sql` is ignored (commits `d5405ae`, then a follow-up; not pushed yet). **Still to do by the owner:** rewrite history and force-push, then change the leaked passwords. |

**Local database check (2026-10-24).** All 43 migration files have run, including the three uncommitted ones (`payments` has 0 rows). The `migrations` table also records 7 migrations that **no longer exist as files**:
- `create_invoices_table` (03-31)
- `create_work_order_parts_table` (04-02)
- `create_invoice_details` (04-03)
- `upgrade_mechanics_table`
- `create_suppliers_table` (04-10)
- `add_soft_deletes_to_suppliers`
- `add_notes_to_suppliers_table`

There is also a hand-made `suppliers_backup` table. This confirms M10: the repo cannot rebuild the schema. The production schema must be dumped and compared before writing any data migration. The local data is dev/test data (15 customers, 11 invoices, 32 parts).

## 7. Proposal: roles, supplier payables, petty cash

> **Approved 2026-10-24 and moved into SPEC sections 2 and 4.** SPEC is the source of truth. One refinement: payment options are now a `payment_methods` table (each method points to a cash account and has its own surcharge), and the surcharge is stored on the payment line, not as an invoice line.

### Roles
Keep three accounts, even though only two people work in the system day to day. SPEC requires at least one active **Owner** who manages users, roles, settings, voids, and price changes. The current users map directly:

| Current role | New role | Permissions |
|---|---|---|
| `admin` | **Owner (Pemilik)** | Everything |
| `staff` | **Front Desk (Admin Depan)** | `customer.*` except delete, `vehicle.*` except delete, `work_order.view/create/update/assign/change_status`, `invoice.view/create`, `payment.view/create` (takes payment at the counter), `part.view/create`, `stock.view`, `purchase.view/create` (receives goods from a supplier), `supplier.view/create`, `cash.view` (own drawer only) |
| `finance` | **Finance (Keuangan)** | Everything Front Desk has, plus `invoice.update/update_paid/void/discount/toggle_tax`, `payment.update/delete`, `expense.*`, `purchase.update`, `supplier_payment.*`, `cash.*` (top-ups, transfers, adjustments), `part.update` (prices), `stock.adjust`, `dashboard.view_financials`, `report.view` |

- **Mechanic role:** dropped from the default roles. Mechanics stay in a `mechanics` table with `branch_id`. `work_order_mechanic` and `work_order_services.mechanic_id` point to `mechanics`, not `users`.
- **Other default roles:** Branch Manager and Warehouse stay in the SPEC as defaults for future tenants. They are just not assigned in the pilot.

### Supplier payables
Use separate tables, not the polymorphic `payments`. Customer and supplier money have different permissions, reports, and audit needs.

- **`purchases` [branch]** (evolves from `stock_entries`): `number` (`{BRANCH}/PB/{YYYY}/{NNNNN}`), `supplier_id`, `supplier_ref`, `purchase_date`, `due_date`, `total`, `amount_paid`, `status` (unpaid|partial|paid).
  - Lines: `purchase_lines` (evolves from `stock_entry_items`).
  - Each line creates a `purchase` stock movement through `StockService`.
- **`supplier_payments` [branch]:** `purchase_id`, `paid_at`, `amount`, `cash_account_id`, `reference`, `paid_by`. Recalculates the purchase status in `PurchaseService`.

### Petty cash and named accounts
One small ledger replaces both `settings.petty_cash_balance` (a stored balance drifts) and the hardcoded "Transfer Anton / QRIS BCA" list:

- **`cash_accounts` [branch]:** `name`, `type` (cash_drawer|petty_cash|bank|qris|card_terminal), `is_active`.
  - Pilot accounts: Laci Kasir, Kas Kecil, BCA Anton, BCA Martino, QRIS BCA, QRIS Mega, EDC BCA.
- **`cash_movements` [branch]:** `cash_account_id`, `amount` (signed), `type` (customer_payment|supplier_payment|expense|transfer_in|transfer_out|adjustment), `reference_type/id`, `note`, `created_by`.
  - The balance is the sum of movements.
  - Petty-cash top-ups are a transfer from Laci Kasir to Kas Kecil.
- **Links:**
  - `payments.method` stays the SPEC enum, plus a `cash_account_id`.
  - Expenses record which account paid. This replaces `payment_source`.
- **Card surcharge:** the 3% fee becomes a branch setting per account type and is added as an `other` invoice line on the server.

The three WIP migrations have only run on dev databases (to be confirmed for production; see question 1), so they can be reversed by new migrations.

## 8. Questions for you

1. **Is this code already running live at a shop** (the reminder text names "Bengkel Kalimas Lumas")? Is that the pilot shop? If it is, I need a schema-only dump of production to confirm which migrations ran there and whether the schema drift in M10 exists in production too.
2. **Laravel version:** the code is on Laravel 13.5, while CLAUDE.md says 12. Should I update CLAUDE.md to 13? (Recommended; downgrading has no benefit.)
3. **TypeScript:** OK to add the real toolchain (`typescript`, `vue-tsc`, `tsconfig.json`, rename `app.js` → `app.ts`)? Pages already use `lang="ts"`.
4. **Role mapping:** `admin` → Owner is clear. What should `finance` and `staff` become: Cashier, Service Advisor, Branch Manager, or a custom role per person?
5. **Mechanics:** should mechanics become user accounts with the Mechanic role, as in the SPEC, or stay as non-login records you assign? Do you still need NIK and KTP storage? If so, it moves to private storage with restricted access.
6. **WO task checklist** (`work_order_items`, e.g. "Cek bunyi kaki-kaki"): keep it as a checklist next to the SPEC's priced `work_order_services`, or turn each task into a service line?
7. **Draft invoices:** today an invoice is saved as a draft while the car is still being worked on. The SPEC has no draft invoice status; the WO holds the lines until it is invoiced. Is it OK to drop invoice drafts and migrate existing drafts back onto their WOs?
8. **Pricing details:** should `parts.discount_percent` (per-part standing discount) stay? Should the **3% surcharge** for credit card and non-BCA debit stay, and if so, as an `other` invoice line driven by a branch setting?
9. **Uncommitted WIP:** supplier debt (unpaid stock entries plus their payments) and petty cash (`payment_source`, `petty_cash_balance`) are not in the SPEC. Should I add them to the MVP scope (and the SPEC), or park them? Either way, I'd like to discard the polymorphic `payments` migration in favour of the SPEC design. Has it been run anywhere?
10. **Payment methods:** the shop uses specific accounts ("Transfer Anton", "Transfer Martino", "QRIS BCA", "QRIS MEGA", "Debit BCA"). Is it OK to model these as the SPEC method enum plus a configurable "account" per branch?
11. **Google login:** keep it (it is not in the SPEC) or remove it?
12. **Stock count:** since current stock numbers are unreliable (M5), can the shop do a physical stock count just before go-live to set opening balances?
13. **Customer phones:** customers have up to three phones. Should they map to `phone` + `whatsapp` with any third number moved to `notes`, or should I keep a separate list of contacts?
14. **`db_template.sql`:** is this repository public? If it is, the dump should also be purged from git history (a force-push), and the two accounts' passwords rotated.
15. **Mechanic commission** (SPEC open question): the admin dashboard has a commented-out `work_orders_count * 3000`. Is per-job commission a real requirement for the pilot?

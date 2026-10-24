# CLAUDE.md — Bengkel Management System (SaaS)

Multi-tenant SaaS for Indonesian automotive repair shops (bengkel). One account (tenant) can run multiple branches.
Full requirements: @docs/SPEC.md. Read the relevant section before starting any feature.

**Current goal: MVP for a pilot in one shop** (SPEC section 9). Build only MVP items; do not start "After pilot" features unless asked. Keep the multi-tenant/multi-branch architecture intact even though the pilot uses one tenant and one branch.

## Tech stack
- Laravel 13 (PHP 8.3+), MySQL 8
- Vue 3 (Composition API, `<script setup lang="ts">`) + TypeScript (strict, checked by `vue-tsc`). Frontend style: **Inertia.js v2 monolith** (controllers return `Inertia::render()`, Ziggy `route()` in Vue, session auth; no separate API). Small JSON endpoints for autocomplete only.
- Tailwind CSS
- spatie/laravel-permission with the **teams** feature enabled (team = tenant)
- laravel-vue-i18n: one set of translation files shared by Laravel and Vue
- Redis for cache and queues; S3-compatible storage for uploads (logos, receipts)
- Pest for tests, Larastan (PHPStan) level 6+, Laravel Pint for formatting

## Commands
- `composer dev` / `php artisan serve` + `npm run dev`
- `php artisan test` must pass before a task is done. Tests run on the MySQL database `openbengkel_test` (not SQLite), wrapped in a rolled-back transaction per test
- `php artisan tenant:create` creates a shop (tenant + first branch + default roles + owner)
- `./vendor/bin/phpstan analyse` and `./vendor/bin/pint --test` must pass
- `npm run build` and `npm run type-check` must pass for frontend changes
- `php artisan migrate`. Never edit a migration that has already run in production; create a new one
- `php artisan db:seed` seeds permissions, a demo tenant with two branches, and demo users

## Current codebase state
Phase 0 (foundation) is built on the `phase-0` branch (2026-10-24). The previous app is kept under the git tag `legacy-v0` for UI reference only; don't copy its patterns. Background: `docs/AUDIT.md`, `docs/PHASE0_PLAN.md`.
- **What exists:** tenants, branches, users (`branch_user`, `all_branches`), spatie roles per tenant, audit log, document sequences; login, forgot/reset password, profile (name, phone, language, password), branch switcher, dashboard placeholder, friendly error pages; `tenant:create`; mobile-first `AppLayout` (bottom bar on phones, sidebar from `md`) and the shared UI kit in `resources/js/Components/ui`.
- **Phase 1 progress** (branch `phase-1-customers`): customers, vehicles and ownership history are done (`CustomerService`, `VehicleService`, pages under `Pages/Customers` and `Pages/Vehicles`). Phones are not unique (the form warns), plates are unique per tenant including deleted vehicles (restore flow), customers with vehicles cannot be deleted. The service catalog (`service_items`, `ServiceItemService`, `Pages/ServiceItems`, branch `phase-1-services`) is done; `ServiceItemService::isUsed()` must start checking `work_order_services` when work orders land. Mechanics (branch `phase-1-mechanics`) are done: branch-scoped, NIK `encrypted` cast and masked without `mechanic.view_identity`, KTP photos on `config('filesystems.private_disk')` served only by `MechanicKtpController`; `MechanicService::isUsed()` must check `work_order_mechanic` later. Parts, branch stock, suppliers and `StockService` (branch `phase-1-parts`) are done, with in-app low-stock notifications (bell icon). Cash accounts, payment methods and `CashService` (branch `phase-1-cash`) are done. Purchases, supplier debt and supplier payments (branch `phase-1-purchases`) are done: `PurchaseService` (create/revise/void, weighted average cost), `SupplierPaymentService` (pay/void through `CashService`), `PaymentAccountRules` (Front Desk pays on the spot only in full, from cash, up to `branches.cash_purchase_limit`), daily `purchases:notify-due` bell reminders (needs the scheduler cron). Use `TenantContext::today()` for "today" in the branch timezone (e.g. `before_or_equal` date rules). Test helpers used by several test files live in `tests/Pest.php`, so a single file runs on its own. Work orders (branch `phase-1-work-orders`) are done: `WorkOrderService` (check-in incl. new vehicle/customer, status flow in `WorkOrderStatus::nextManual()`, checklist, mechanics, service and part lines; parts move stock as soon as they are added), lines bound with `scopeBindings()` under `work-orders/{work_order}`, printable SPK, vehicle service history across branches. `BranchTime` converts typed local times to UTC and back. The line model for `work_order_services` is `WorkOrderServiceLine` (to avoid clashing with the service class). Invoices (branch `phase-1-invoices`) are done: `InvoiceService` (from a completed work order, which becomes `invoiced`; walk-in sales with `sale` stock movements and "Pelanggan umum" = null customer; void), all totals in `InvoiceCalculator` (reuse it in `revise()`), branch tax settings page (`BranchSettingsService`), A5 print page. Customer payments (branch `phase-1-payments`) are done: `PaymentService` (checkout with several methods = one kwitansi, surcharge, cash change, due date required for a remaining balance; void one line or a whole kwitansi, never deleting, with reversing cash movements; keeps invoice status and work order closed/invoiced in sync), customer debt list and `invoices:notify-due` reminders, A5 kwitansi with `Terbilang`. Invoice editing (branch `phase-1-invoice-edits`) is done: `InvoiceService::revise()` (reason, snapshot in `invoice_revisions`, totals with the invoice's own tax setting, `invoice_edit` stock moves, refuses a total below `amount_paid`), `InvoiceSettlement` keeps invoice status and work order closed/invoiced in sync for payments and edits, `RevisionComparer` for the change history page. **Phase 1 is complete.** Phase 2 user management (branch `phase-2-users`) is done: `Settings/Users` (add, edit, new password, deactivate/delete via `UserService`), `RoleService` (custom roles, permission matrix, `canAssign()`: non-owners only give roles whose permissions they hold), `Settings/Roles` and `Settings/RoleMatrix`. `{role}` route bindings are tenant-scoped in `AppServiceProvider`. Expenses (branch `phase-2-expenses`) are done: `ExpenseService` (record via `CashService`, edit details, void with reversing movement, receipt photo on the private disk), `ExpenseCategoryService` (defaults in `TenantProvisioner`), restricted categories via `expense.view_restricted` and `ExpenseCategory::visibleTo()`. Remaining Phase 2: dashboard (with petty cash balances), shop & branch settings; ask the user which next.
- **Next:** SPEC Phase 1 modules, in SPEC order. Each module adds its models with `BelongsToTenant` (+ `BelongsToBranch` for operational data), a Policy, FormRequests, a Service, an entry in `resources/js/lib/navigation.ts`, and its defaults in `TenantProvisioner`.
- **Pilot decisions:** pilot shop is Bengkel Kalimas Lumas, not live yet (no production data). Accounts: Owner, Front Desk, Finance. Mechanics are non-login records (NIK encrypted, KTP on a private disk). Supplier debt, petty cash and paying one invoice with several methods are MVP scope (SPEC §4). The repo is **public**: never commit dumps, real personal data, or secrets.
- **Quality bar:** follow SPEC §8.1 (mobile-first, simple UX), §8.2 (security), and §8.3 (engineering principles) for every change.

### How the foundation works (use it, don't reinvent it)
- `App\Support\Tenancy\TenantContext` holds the tenant and active branch. The `tenancy` route middleware sets it from the logged-in user; console code, jobs and tests use `$context->run($tenant, $branch, fn () => ...)`. Querying a tenant model without a tenant throws `TenancyViolation` (only `User` is allowed, for login).
- Permissions live in `App\Enums\Permission`; default roles in `App\Enums\RoleName`. Check permissions with `$user->can(Permission::X->value)` in Policies/FormRequests; in Vue use `usePermissions().can('x.y')` only to hide UI.
- `App\Models\Role` is deliberately not tenant-scoped (spatie caches roles globally); always query it with `Role::query()->forTenant($tenant)`.
- Services throw `App\Exceptions\BusinessRuleViolation` with a translated message for rule violations; it is shown to the user as a flash error and also put in the error bag as `business`, so Inertia calls `onError` and open dialogs keep what the user typed.
- User writes go through `UserService` (owner safeguards, audit). Models use `Auditable` for automatic create/update/delete logs and `AuditLogger::record()` for actions that need a reason.
- Every model declares its database defaults in `$attributes`: strict mode (`Model::shouldBeStrict()` outside production) throws on missing attributes.
- Shared props (`auth.user`, `auth.permissions`, `tenant`, `branch`, `branches`, `locale`, `flash`) are typed in `resources/js/types/index.d.ts`.
- `InitializeTenancy` runs before `SubstituteBindings` (middleware priority), so `{customer}`-style route bindings are tenant-scoped: another tenant's record is a 404.
- Present models to Vue through `App\Http\Resources\*` and `->resolve()` (nested collections too); paginate with `->paginate()->withQueryString()->through(...)`, which matches the `Paginated<T>` type.
- New permissions: add them to `Permission`, `RoleName` and SPEC §2. Before the pilot goes live, `migrate:fresh --seed` picks them up; after go-live, existing tenants' default roles need an explicit migration or command to receive them (the synchronizer only updates the Owner role).
- Sensitive uploads go to `config('filesystems.private_disk')` (the `local` disk has `serve` off) and are served by a controller that authorizes first and sends `Cache-Control: private, no-store`. Store a new file before the DB transaction and delete the old one in `DB::afterCommit()`. Uploads with PUT: send POST with `_method: 'put'` and `forceFormData`.
- Stock quantities: in PHP always `int` hundredths via `App\Support\Quantity` (4,2 L = 420; never floats), DECIMAL(12,2) in the database, `<QuantityInput>` in Vue. Only units with `PartUnit::allowsDecimals()` take decimals. Use `StockService::move()` for every change; it blocks going below zero and sends low-stock notifications after commit. Line totals: `Quantity::priceOf($qty, $unitPrice)`.
- Inertia page props must not reuse a shared prop name (`notifications`, `auth`, `branch`, …): the page prop silently replaces it.
- Translation keys in `lang/*.json` must not start with a PHP group name (`auth`, `pagination`, `passwords`, `validation`): laravel-vue-i18n lets the PHP file win (a test enforces this).

## Project structure (target)
```
app/
  Models/                       Eloquent models
  Models/Concerns/BelongsToTenant.php, BelongsToBranch.php, Auditable.php
  Enums/                        Permission, RoleName, DocumentType, statuses
  Services/<Module>/            business logic (InvoiceService, StockService, ...)
  Http/Controllers/<Module>/    thin controllers
  Http/Requests/<Module>/       FormRequest validation + authorization
  Policies/                     one policy per model
  Support/                      Money, DocumentNumber, PlateNumber, Phone helpers
  Support/Tenancy/              TenantContext, scopes, TenancyViolation
resources/js/
  Pages/<Module>/               pages
  Components/<Module>/          module components
  Components/ui/                shared UI components
lang/id.json, lang/en.json      translations (Indonesian is the source language)
docs/SPEC.md
```

## Non-negotiable rules
1. **Tenant isolation.** Every tenant-owned model uses the `BelongsToTenant` trait (global scope + auto-fill `tenant_id`). Never query tenant data with `withoutGlobalScopes()` except in clearly marked platform-admin code. Every new model gets a test proving tenant A cannot read or write tenant B's data (use `assertTenantIsolated()` from `tests/Pest.php`).
2. **Branch scoping.** Operational data (work orders, invoices, payments, stock, expenses) has `branch_id`. Users only see branches they are assigned to. The active branch comes from the session, never from an unchecked request parameter.
3. **Authorization on the server.** Every controller action is authorized through a Policy or FormRequest using permission names from SPEC section 2. Hiding a button in Vue is not security.
4. **No hardcoded UI text.** All strings go through `__()` / `trans()` / `$t()`. Add keys to BOTH `lang/id.json` and `lang/en.json` in the same change.
5. **Money is integer Rupiah** in `BIGINT UNSIGNED` columns (or signed where negatives are valid). Never float or double. Format with the `Money` helper (`Rp 1.250.000`).
6. **Inventory changes only through `StockService`**, which writes a `stock_movements` row and updates `part_branch_stock.qty` in one transaction. Cash works the same way through `CashService` (`cash_movements`).
7. **Multi-step writes use `DB::transaction()`** (invoicing, payments, invoice edits, stock).
8. **Edits to issued invoices go through `InvoiceService::revise()`**, which checks permission, requires a reason, saves a revision snapshot, recalculates totals, syncs stock, and writes the audit log. Never update invoice rows directly.
9. **Audit log** for create/update/delete on work orders, invoices, payments, stock adjustments, expenses, users, roles, and settings.
10. **Document numbers** come from `DocumentNumber::next($branch, DocumentType::Invoice)`, which uses a locked sequence row (`SELECT ... FOR UPDATE`). Call it inside the transaction that saves the document. Never use `max(id)+1`.
11. Timestamps are stored in UTC and displayed in the branch timezone (default `Asia/Jakarta`).

## Conventions
- Business logic lives in Services. Controllers validate, authorize, call one service method, and return.
- Use Eloquent eager loading; avoid N+1 queries (`Model::shouldBeStrict()` is on outside production, so lazy loading throws).
- Paginate every list server-side, with search and sort.
- Soft deletes for customers, vehicles, parts, suppliers, users, branches.
- Indonesian formats: phone accepts `08...` and is stored as `+62...`; plate number uppercase with normalized spacing (`B 1234 XYZ`).
- Database names: snake_case plural tables, snake_case columns (Laravel defaults).

## Workflow for each task
1. Read the relevant part of `docs/SPEC.md`. Ask if anything is ambiguous.
2. Propose a plan (migrations, models, services, pages). Wait for approval when it changes the schema or touches existing data.
3. Implement migration → model → service → policy/request → controller → Vue page → translations.
4. Write Pest tests: service logic, permissions, and tenant isolation.
5. Run tests, PHPStan, Pint, `npm run type-check`, and the frontend build. Fix failures before saying done.
6. Tick the checklist in `docs/SPEC.md` section 9.

## Don't
- Don't add packages without explaining why.
- Don't put business logic in Vue components or controllers.
- Don't rename permissions without updating `App\Enums\Permission`, `RoleName`, and SPEC section 2 together.
- Don't "fix" tests by weakening assertions.

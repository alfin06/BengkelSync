# SPEC — Bengkel Management System (SaaS)

## 1. Overview
Online, multi-tenant SaaS for Indonesian automotive repair shops.
Anyone can sign up, creating a **tenant (account)**. A tenant owns one or more **branches (cabang)**. Users belong to one tenant and are assigned to one or more branches.
Primary language: Bahasa Indonesia. Secondary: English. Language is chosen per user.

Main flow per branch:
**Customer → Vehicle → Work Order (services + parts) → Invoice → Payment(s)**,
with parts consumption reducing branch stock and everything feeding the dashboard.

Customers and vehicles are shared across all branches of a tenant (a customer can visit any branch). Operational data belongs to a branch.

## 2. Users, roles & permissions

Uses spatie/laravel-permission with teams (`team_id` = `tenant_id`).
- **Permissions** are global, defined by the platform, and seeded. Format `resource.action`.
- **Roles** are per tenant. Each new tenant gets the default roles copied in; the tenant owner can create or edit roles in a permission matrix UI.
- **Branch access**: `branch_user` pivot. A user only works in the branches assigned to them. Owner and tenant-wide roles can be given "all branches".

Resources: `customer, vehicle, mechanic, work_order, invoice, payment, part, stock, supplier, purchase, supplier_payment, cash, expense, dashboard, report, branch, user, role, settings, audit_log`.
Actions: `view, create, update, delete`, plus:
- `mechanic.view_identity` (see NIK and the KTP photo)
- `work_order.assign`, `work_order.change_status`, `work_order.update_closed`
- `invoice.update` (edit an unpaid invoice), `invoice.update_paid` (edit an invoice with payments), `invoice.void`, `invoice.discount`, `invoice.toggle_tax`
- `payment.update`, `payment.delete`
- `stock.adjust`, `stock.transfer` (between branches)
- `cash.transfer` (move money between cash accounts, e.g. topping up petty cash), `cash.adjust` (correct a balance after a cash count)
- `dashboard.view_financials`
- `audit_log.view` (read the audit log)
- `billing.manage` (subscription, owner only)

Default roles per tenant (the owner can edit them or add more in the permission matrix):
| Role | Typical permissions |
|---|---|
| Owner (Pemilik) | everything; system role, cannot be deleted; at least one active owner must exist |
| Branch Manager (Kepala Cabang) | everything within assigned branches except billing, roles, tenant settings |
| Front Desk (Admin Depan) | customers and vehicles (no delete), mechanics (view), work orders incl. assign and change status, create invoices, record customer payments, parts (view/create), stock (view), purchases (view/create: receiving supplier deliveries), suppliers (view/create), cash (view) |
| Finance (Keuangan) | everything Front Desk has, plus invoice edit/void/discount/tax, payment edit/delete, expenses, purchase edit, supplier payments, cash transfer/adjust, part prices, stock adjust, mechanic identity, financial dashboard, reports |
| Warehouse (Gudang) | parts, stock, suppliers, purchases, transfers |

Mechanics do not log in (section 11). There is no Mechanic role; mechanics are records that staff assign to work orders.

Pilot accounts: Owner (the shop owner), one Front Desk user, one Finance user.

## 3. Tenancy & SaaS
- Single database, shared schema, `tenant_id` on every tenant-owned table (indexed, part of composite indexes and unique keys, e.g. `unique(tenant_id, plate_number)`).
- Sign-up creates: tenant, first branch, owner user, default roles, default expense categories, default settings. Email verification required.
- **Subscription plans** (phase 4): limits by number of branches and users. Payment gateway: Midtrans or Xendit (support QRIS, VA, cards in Indonesia). Trial period, then read-only mode when unpaid (data is never deleted for non-payment).
- **Platform admin** (super admin) area, separate from tenants: list tenants, plans, suspend, impersonate with audit.
- Tenant data export (CSV/Excel) available to owners.

## 4. Data model (initial)

All tables below have `tenant_id` unless marked [global]. [branch] = also has `branch_id`.

**tenants**: id, name, slug, plan_id, trial_ends_at, status, default_locale
**branches**: id, tenant_id, name, code (used in document numbers), address, phone, timezone, logo_path, npwp, tax settings (see section 5), invoice_footer, deleted_at
**users**: id, tenant_id, name, email (unique globally; one email = one tenant), phone, password, locale, is_active, all_branches (bool), deleted_at; **branch_user** pivot
**permissions** [global], **roles** (per tenant via team_id), spatie pivot tables

**customers**: id, type (individual|company), name, phone, whatsapp, email, address, npwp, notes, deleted_at
**vehicles**: id, customer_id, plate_number (unique per tenant), make, model, year, color, vin, engine_number, transmission, fuel_type, last_odometer, notes, deleted_at
**vehicle_ownerships**: vehicle_id, customer_id, started_on, ended_on (null = current owner), reason (history of transfers)

**service_catalog**: id, code, name, default_price, estimated_minutes, is_active (tenant-wide; same price in all branches)

**work_orders** [branch]: id, number, vehicle_id, customer_id, status, complaint, diagnosis, odometer_in, estimated_completion, started_at, completed_at, notes, created_by
Status flow: `draft → queued → in_progress → waiting_parts → completed → invoiced → closed`; `cancelled` from any state before `invoiced`.
**mechanics** [branch]: id, name, phone, nik (encrypted at rest; shown only with `mechanic.view_identity`), ktp_path (private disk, served only through an authorized download), is_active, notes, deleted_at. Mechanics have no login.
**work_order_mechanic** pivot (work_order_id, mechanic_id); **work_order_services**: description, service_id (nullable), qty, unit_price, mechanic_id (nullable → mechanics)
**work_order_tasks**: work_order_id, description, is_done, sort (the front-desk checklist of what to check or do, e.g. "Cek bunyi kaki-kaki"; no price)
**work_order_parts**: part_id, qty, unit_price, unit_cost (snapshots)

**invoices** [branch]: id, number, work_order_id (nullable, allows walk-in/parts-only sales), customer_id, issue_date, due_date, subtotal, discount_amount, tax_enabled, tax_rate, tax_inclusive, tax_amount, total, amount_paid, status (unpaid|partial|paid|void), void_reason, revision_no
**invoice_lines**: invoice_id, type (service|part|other), part_id (nullable), description, qty, unit_price, discount_amount, line_total
**invoice_revisions**: invoice_id, revision_no, snapshot (JSON of header + lines), reason, edited_by, created_at

**Customer payments (several methods in one checkout).** A customer can pay one invoice with any mix of methods at once (e.g. Rp 500.000 cash + the rest by QRIS), and can pay in instalments over time.
**payment_receipts** [branch]: id, number (`KW`), invoice_id, paid_at, amount (sum of its lines, excl. surcharge), surcharge_total, received_by, note. One receipt = one checkout = one printed kwitansi.
**payments** [branch]: receipt_id, invoice_id, payment_method_id, amount (applied to the invoice), surcharge_amount, cash_tendered (cash only; change = tendered − amount), reference (transfer ref, card approval code). Each line writes a `cash_movements` row into its method's cash account.
- The sum of lines may not exceed the invoice's outstanding balance. Overpayment is only possible as cash change.
- Card surcharge is stored on the payment line, not as an invoice line, so paying never changes an issued invoice. It is reported as surcharge income.

**payment_methods** [branch]: id, name (e.g. "Tunai", "QRIS BCA", "Debit BCA", "Debit non-BCA", "Kartu Kredit", "Transfer BCA Anton"), type (cash|transfer|qris|debit|credit_card|other), cash_account_id (where the money lands), surcharge_percent (default 0), needs_reference (bool), is_active, sort. Managed in settings; replaces hardcoded payment lists.

**Cash and petty cash.**
**cash_accounts** [branch]: id, name (e.g. "Laci Kasir", "Kas Kecil", "BCA Anton"), type (cash_drawer|petty_cash|bank|other), is_active, sort.
**cash_movements** [branch]: cash_account_id, amount (signed), type (customer_payment|supplier_payment|expense|transfer_in|transfer_out|adjustment), reference_type, reference_id, note, created_by, created_at. The balance of an account is the sum of its movements; it is never stored as an editable number.
- A top-up of petty cash is a transfer: one `transfer_out` and one `transfer_in` row, written together with `cash.transfer`.
- A cash count that differs from the system balance is recorded as an `adjustment` with a required reason (`cash.adjust`).
- All writes go through `CashService` in a transaction (same rule as `StockService`).

**parts**: id, sku (unique per tenant), name, brand, category, unit, cost_price, sell_price, is_active, deleted_at (catalog and prices are tenant-wide, same in all branches; stock is per branch)
**part_branch_stock**: part_id, branch_id, qty, min_stock, location
**suppliers**: id, name, phone, whatsapp, sales_name, email, address, npwp, notes, deleted_at
**stock_movements** [branch]: part_id, type (purchase|work_order_use|invoice_edit|return|adjustment|transfer_in|transfer_out), qty (+/−), unit_cost, reference_type, reference_id, note, created_by

**Purchases and supplier debt.** Receiving goods from a supplier is a purchase. It can be paid immediately, partly, or later (hutang supplier).
**purchases** [branch]: id, number (`PB`), supplier_id, supplier_ref (the supplier's own invoice number), purchase_date, due_date (nullable), total, amount_paid, status (unpaid|partial|paid|void), notes, created_by
**purchase_lines**: purchase_id, part_id, qty, unit_cost, line_total. Saving a purchase writes `purchase` stock movements through `StockService` and updates `parts.cost_price` (weighted average, integer, rounded half-up).
**supplier_payments** [branch]: purchase_id, paid_at, amount, cash_account_id, reference, paid_by, note. Writes a `supplier_payment` cash movement. Recalculates the purchase status.
- Editing a purchase after saving needs `purchase.update` and a reason; line changes create stock movements for the difference and are written to the audit log.

**expense_categories**: id, name; **expenses** [branch]: date, category_id, description, amount, cash_account_id (which account paid; e.g. petty cash), attachment_path (private disk), created_by. Writes an `expense` cash movement.

**document_sequences** [branch]: type (WO|INV|KW|PB), year, last_number. Locked during increment.
**audit_logs**: user_id, branch_id (nullable), action, auditable_type, auditable_id, old_values (JSON), new_values (JSON), reason, ip, created_at

Document numbers: `{BRANCH_CODE}/{TYPE}/{YYYY}/{NNNNN}`, e.g. `JKT1/INV/2026/00001`. Types: WO (work order), INV (invoice), KW (payment receipt / kwitansi), PB (purchase / pembelian).

## 5. Tax (optional)
Many small bengkel are not PKP and must not charge PPN, so tax is fully optional.
- **Branch setting**: `tax_enabled` (default **off**), `tax_name` (default "PPN"), `tax_rate` (default 11%, editable), `tax_inclusive` (prices include tax or tax is added on top). Set per branch, because branches can be separate legal entities.
- **Per invoice**: new invoices copy the branch setting. A user with `invoice.toggle_tax` can turn tax on or off for a single invoice.
- The rate and mode are **snapshotted on the invoice**, so changing branch settings later never changes existing invoices.
- When tax is off, the invoice and print layout show no tax rows at all.
- Rounding: integer Rupiah, tax rounded half-up. Inclusive: `tax = round(total × rate / (100 + rate))`.

## 6. Editing data (including invoices)
Users with the right permission can edit any record, but every edit is traceable.
- **General records** (customers, vehicles, parts, expenses, work orders): editable with `*.update`; changes go to the audit log with old and new values.
- **Closed work orders**: need `work_order.update_closed`.
- **Invoices**:
  - Unpaid invoice: `invoice.update`. Partial or paid invoice: `invoice.update_paid`. Voided invoices cannot be edited.
  - Every edit requires a **reason**, increments `revision_no`, and saves the previous version to `invoice_revisions`. Users can view and compare revisions.
  - Totals are recalculated. Changing part lines creates `invoice_edit` stock movements for the difference.
  - If the new total is lower than `amount_paid`, the system blocks the save and asks the user to record a refund or adjust payments first.
  - The invoice number never changes. The printed invoice can show "Revisi 2" when `revision_no > 0`.
- **Payments**: editing or deleting needs `payment.update` / `payment.delete`, a reason, and recalculates invoice status. The matching cash movement is reversed with a new movement, never deleted.
- **Supplier payments and cash movements** follow the same rule: corrections are new rows with a reason, so balances can always be traced.
- **Settings**: tenants can choose to allow or block editing invoices older than N days (lock period), so closed months stay stable for bookkeeping.

## 7. Screens
- **Auth**: sign up (create shop), login, forgot password, email verification, accept invitation.
- **Branch switcher** in the header (only assigned branches) + "all branches" view for dashboards/reports when permitted.
- **Dashboard**: work orders today by status, revenue today/this month, receivables, expenses, gross profit (revenue − parts cost − expenses), 12-month revenue chart, top services, top parts, low stock, mechanic workload. Filter by branch and date range. Financials need `dashboard.view_financials`.
- **Customers**, **Vehicles** (with service history across branches), **Work orders** (list + kanban), **Invoices** (list, detail, edit, revisions, payments, print/PDF, share via WhatsApp link), **Inventory** (parts, branch stock, stock in, adjustments, transfers, history, suppliers), **Expenses**, **Reports**.
- **Payment dialog** ("Bayar"), used from the invoice page:
  - It opens with the outstanding amount prefilled on one line, using the default method (Tunai).
  - "+ Metode lain" adds a line. Each line has a method picker (large buttons, not a dropdown), an amount, and a reference field when the method needs one.
  - A sticky footer shows total due, total entered, remaining, and the card surcharge. Cash lines also show "Uang diterima" and the change (kembalian).
  - The save button stays disabled until the lines add up to at most the outstanding amount. Saving prints or shares one kwitansi.
- **Purchases (Pembelian)**: list with filters (supplier, unpaid/partial/paid, due soon), create (scan or search parts, qty, cost), pay now / pay later, detail with payment history.
- **Supplier debts (Hutang Supplier)**: outstanding purchases per supplier, sorted by due date, with "Bayar" (supports several payments over time).
- **Cash (Kas)**: one card per cash account with its balance. Actions: transfer (e.g. top up petty cash), cash count / adjustment, and movement history with filters.
- **Mechanics**: list, add/edit, active toggle. NIK and the KTP photo are visible only with `mechanic.view_identity`.
- **Settings**: tenant profile, branches (incl. tax settings), payment methods and cash accounts, users & invitations, roles & permission matrix, lock period, subscription/billing.
- **Platform admin** (separate area).

## 8. Non-functional
- Online only, HTTPS, responsive (desk tablet + mechanic phone).
- Queues (Redis) for PDFs, emails, exports, WhatsApp notifications.
- Rate limiting on auth and public endpoints.
- Daily automated MySQL backups with tested restore.
- Indexes: every tenant query path has a composite index starting with `tenant_id` (and `branch_id` where relevant).
- Error tracking (e.g. Sentry) and basic uptime monitoring.

### 8.1 UX and design principles
- **Mobile first.** Every screen works on a 360px-wide phone and scales up to a desk tablet and desktop. Lists become cards on small screens. Primary actions sit in a sticky bottom bar on mobile. Touch targets are at least 44px.
- **Few steps for frequent tasks.** Check-in, adding parts, and taking payment each fit on one screen. Search by plate number is available everywhere. Sensible defaults are prefilled (today's date, the outstanding amount, the default payment method).
- **Indonesian first, plain words.** Use "Simpan", "Bayar", and "Hutang", not jargon. Rupiah inputs format as you type (`1.250.000`). Dates use `d MMM yyyy`, and phone input accepts `08…`.
- **Clear feedback.** Validate inline, next to the field, in plain language. Show a toast after saving. Show a loading state on buttons and prevent double submit.
- **Safe actions.** Destructive or financial actions (delete, void, payment correction, cash adjustment) need a confirmation that says what will happen. The reason field is shown wherever a reason is required.
- **Consistency.** One shared component set (`Components/ui`) for buttons, inputs, money input, dialogs, tables/cards, empty states, and pagination. Status colours are the same everywhere.
- **Accessibility.** Labels on every input, keyboard navigable, visible focus, and contrast at WCAG AA. Never rely on colour alone.
- **Hide what the user cannot do** (for clarity only). The server still enforces every permission.

### 8.2 Security
- Authorization on every route through Policies/FormRequests. Every policy is tested, including tenant and branch isolation.
- Server-calculated money: the server never trusts prices, totals, discounts, or statuses sent by the browser. It recomputes everything from the database and permissions.
- Sensitive files (KTP photos, receipts, attachments) live on a private disk and are served only through authorized, short-lived download routes. NIK uses Laravel's encrypted cast.
- No public sign-up in the MVP. Login and password reset are rate limited. Sessions are regenerated on login. Inactive users are logged out. Passwords use Laravel's default rules.
- Security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy) are set in production. `APP_DEBUG=false` in production.
- Mass assignment is limited to `$fillable` attributes and validated data only. No raw SQL with user input. No `v-html` with user content.
- The repository is public: never commit dumps, real personal data, `.env` files, or keys. Dependencies are checked with `composer audit` and `npm audit` in CI.
- The audit log covers every financial change (invoices, payments, purchases, supplier payments, cash movements, expenses).

### 8.3 Engineering principles
- Single responsibility: controllers authorize, validate (FormRequest), call one service method, and return. Business rules live in Services (`InvoiceService`, `PaymentService`, `PurchaseService`, `StockService`, `CashService`, …). Models hold relations, casts, and scopes.
- Ledgers over mutable totals: stock and cash are append-only movement tables. Balances are derived or kept in sync inside the same transaction.
- Small, well-named units and no duplication. Shared rules go in Support helpers (`Money`, `DocumentNumber`, `Phone`, `PlateNumber`).
- Tests first for money and stock rules (Pest). Every bug fix gets a regression test.
- Typed code: PHP return/param types, Larastan level 6+, TypeScript with typed Inertia page props.

## 9. MVP scope & build phases

**Goal:** an MVP piloted in one real shop. The architecture stays multi-tenant and multi-branch from day one (tenant_id, branch_id, scoping, isolation tests), because adding it later means migrating live data. Only SaaS-facing features that the pilot shop does not need are deferred.

Pilot setup: one tenant, one branch (the schema and code support more). The tenant is created with `php artisan tenant:create` instead of public sign-up. The branch switcher is hidden when a user has only one branch.

### MVP — Phase 0: Audit & foundation
- [x] Audit existing code (see section 10) and fill in "Current codebase state" in CLAUDE.md
- [x] Security quick fixes: committed DB dump untracked, public sign-up removed, legacy KTP upload retired (git history rewrite of the dump is still pending, see AUDIT §6)
- [x] Clean migration baseline (no production data exists; see docs/AUDIT.md)
- [x] Tooling: Pest, Larastan, Pint, TypeScript checks
- [x] Shared UI kit (mobile-first layout, money input, dialogs, list/card, pagination, empty states)
- [x] Tenancy: tenants table, `BelongsToTenant` trait, isolation tests
- [x] Branches, branch assignment, active-branch middleware
- [x] spatie/permission with teams, permission seeder, default roles per tenant
- [x] `tenant:create` artisan command (tenant + first branch + owner + defaults)
- [x] Login, logout, forgot password
- [x] i18n shared between Laravel and Vue; locale per user
- [x] Support helpers: Money, DocumentNumber, PlateNumber, Phone (with tests)
- [x] Audit log

### MVP — Phase 1: Core operations
- [x] Customers, vehicles, ownership history
- [ ] Service catalog
- [ ] Mechanics (records without login; NIK encrypted, KTP on a private disk)
- [ ] Parts, branch stock, suppliers, StockService (stock in, adjust)
- [ ] Purchases with supplier debt and supplier payments
- [ ] Cash accounts, payment methods, CashService
- [ ] Work orders with status flow, task checklist, and mechanic assignment
- [ ] Invoices (from work order and walk-in), optional tax, print/PDF
- [ ] Customer payments with several methods per checkout, card surcharge, kwitansi
- [ ] Invoice editing with reason + revisions

### MVP — Phase 2: Management
- [ ] Expenses + receipt upload (paid from a cash account, e.g. petty cash)
- [ ] Petty cash: transfers between cash accounts, cash count / adjustment, balances on the dashboard
- [ ] Dashboard (single branch view)
- [ ] User management (owner creates users with a password) + role/permission matrix UI
- [ ] Shop & branch settings (profile, logo, tax, invoice footer)

### MVP — Pilot launch
- [ ] Production server, HTTPS, daily backups with a tested restore, error tracking
- [ ] Import the shop's existing customers, vehicles, and parts (CSV)
- [ ] Short user guide in Indonesian for the shop staff

### After pilot (deferred)
- [ ] Public sign-up, email verification, user invitations
- [ ] Multi-branch UI: branch switcher, "all branches" dashboard, stock transfers
- [ ] Edit lock period setting
- [ ] Reports + Excel export, tenant data export
- [ ] WhatsApp invoice sharing, service reminders
- [ ] Kanban board for work orders
- [ ] Plans and limits, trial, Midtrans/Xendit billing, read-only mode
- [ ] Platform admin area

## 10. Audit checklist for existing code
Claude should report on each item before any refactor:
1. Laravel/PHP/Vue versions; Inertia or separate API; folder structure.
2. Is there any tenant/branch concept? Which tables lack `tenant_id`/`branch_id`?
3. How are roles/permissions done? Is authorization enforced server-side on every route?
4. Money columns: any float/double? Any rounding logic in Vue instead of PHP?
5. Where does business logic live (controllers, models, Vue)?
6. Hardcoded strings vs translations.
7. Inventory: is stock updated directly or via movements?
8. Document numbering: race-condition safe?
9. Validation: FormRequests or inline? Mass-assignment protection?
10. Tests: any? N+1 queries? Missing indexes?
11. Security: CSRF, XSS (`v-html`), SQL built with raw strings, exposed `.env`/debug.
Output: a prioritized list (must fix / should fix / fine as is) and a proposed migration plan that keeps existing data.

## 11. Decisions
- One email = one tenant. A person cannot belong to two tenants.
- Service and part prices are the same across all branches of a tenant (per-branch pricing may come after the pilot).
- First release is an MVP piloted in one shop (section 9).
- Pilot shop: Bengkel Kalimas Lumas (2026-10-24).
- The pilot shop is not using the app yet. There is no production data to migrate.
- Mechanics are records without a login (no device at their workstation). Staff assign them to work orders. NIK and KTP photo are kept (needed by the shop) and protected.
- Pilot accounts: Owner (shop owner), Front Desk, Finance (section 2).
- Supplier payables, petty cash, and customer payments with several methods in one checkout are in MVP scope (section 4).

## 12. Open questions
- Do mechanics earn commission per job (affects reports)?
- Is e-Faktur integration needed for PKP tenants in the future?

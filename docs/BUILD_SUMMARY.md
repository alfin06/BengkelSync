# BengkelSync: build summary (Phase 0 – Phase 2)

Status on 2026-10-24. Written for the review before pushing to `main`, and as the starting point for later work sessions. The full requirements are in [SPEC.md](SPEC.md); the working rules for code are in [CLAUDE.md](../CLAUDE.md).

## 1. At a glance

| | |
|---|---|
| Scope done | SPEC §9 **Phase 0** (foundation), **Phase 1** (core operations) and **Phase 2** (management): every checklist item ticked |
| Not done yet | SPEC §9 **Pilot launch**: production server and backups, CSV import of the shop's data, Indonesian user guide (first draft: [PANDUAN.md](PANDUAN.md)) |
| Pilot shop | Bengkel Kalimas Lumas (not live; no production data) |
| Tests | 425 Pest tests, 2.098 assertions, all passing (`php artisan test --parallel`) |
| Checks | PHPStan level 6 clean, Pint clean, `vue-tsc` clean, `npm run build` passes |
| Pushed to GitHub | **Nothing from Phase 0 onwards.** Only the rewritten `main` (history without the old DB dump) and, by accident, an early copy of `phase-1-purchases` |
| Size | 603 files changed against `main` (+42.447 / −14.693 lines) |

## 2. How to review

### Branches

Every module is its own commit on its own branch, and each branch is stacked on the previous one. **The last branch, `phase-2-settings`, contains everything.** Reviewing that one branch against `main` is enough.

```
main
└─ 9de4412  WIP (legacy): finance role, payments and petty-cash drafts   ← legacy work, superseded
   └─ 2d6fc92  Project instructions, SPEC, audit and Phase 0 plan
      └─ 84b20f5  Phase 0: multi-tenant foundation             (phase-0)
         └─ 367ebb5  customers, vehicles, ownership history     (phase-1-customers)
            └─ a058d18  service catalog                          (phase-1-services)
               └─ 8e1805e  mechanics                              (phase-1-mechanics)
                  └─ 954b84c  Finance may edit mechanics
                     └─ 8a33e0f  parts, stock, suppliers          (phase-1-parts)
                        └─ c6fa265  cash accounts, CashService    (phase-1-cash)
                           └─ 6b7d18d  purchases, supplier debt   (phase-1-purchases)
                              └─ 80088a9  work orders (SPK)       (phase-1-work-orders)
                                 └─ dce9118  invoices             (phase-1-invoices)
                                    └─ 5061dc0  customer payments (phase-1-payments)
                                       └─ c0642aa  invoice editing (phase-1-invoice-edits)
                                          └─ 94064ea  users & roles (phase-2-users)
                                             └─ 0929516  expenses (phase-2-expenses)
                                                └─ c2847b6  dashboard (phase-2-dashboard)
                                                   └─ 2930e9b  shop settings (phase-2-settings)
```

A sensible way to merge after review: fast-forward `main` to `phase-2-settings` (one push), then delete the intermediate branches. The `tailwind-4` branch (in a separate worktree) is an unrelated experiment and is not part of this chain.

### Running it locally

```bash
composer install && npm install
php artisan migrate:fresh --seed     # wipes the local DB, creates "Bengkel Demo" with two branches
composer dev                          # or: php artisan serve + npm run dev
php artisan test --parallel
```

Demo accounts (password `password1`, fictional `.test` addresses, the seeder refuses to run in production):

| Email | Role |
|---|---|
| owner@demo.test | Owner, all branches |
| manager@demo.test | Branch Manager, both branches |
| frontdesk@demo.test | Front Desk |
| finance@demo.test | Finance |
| warehouse@demo.test | Warehouse, both branches |

The demo data covers every state worth seeing: work orders in each status, invoices (one part-paid with two methods, one counter sale), purchases (paid, part-paid, overdue), expenses (including a restricted salary entry), low-stock parts.

Scheduled jobs (`purchases:notify-due`, `invoices:notify-due`, daily 07:00 Asia/Jakarta) need the standard Laravel cron on the server (see README).

### Suggested reading order

1. `CLAUDE.md` "How the foundation works": the rules every module follows.
2. `app/Support/Tenancy/*`, `app/Models/Concerns/*`: tenant and branch isolation.
3. The services, one per module, in `app/Services/<Module>/`: all business rules live there.
4. `tests/Feature/*`: one file per module, readable as a specification.

## 3. What the shop can do now

Each module: what users do, then the code that does it and the rules worth knowing.

### Foundation (Phase 0)
- Login, forgot/reset password, profile (name, phone, language, password), branch switcher, friendly error pages, mobile-first layout (bottom bar on phones, sidebar from tablet up), shared UI kit (`resources/js/Components/ui`).
- Multi-tenant from day one: `TenantContext`, `BelongsToTenant` / `BelongsToBranch` global scopes, `TenancyViolation` on any cross-tenant read or write, tenant-scoped route bindings.
- Roles per tenant (spatie/permission with teams), `Permission` and `RoleName` enums as the source of truth, `tenant:create` command, audit log (`Auditable` trait + `AuditLogger`), locked document numbers (`DocumentNumber::next()`), Indonesian/English translations shared by Laravel and Vue.

### Customers and vehicles
- Customers (individual/company), phone stored as `+62…` (duplicates allowed but warned), vehicles with plate normalised (`B 1234 XYZ`), unique per shop including deleted ones (restore flow), ownership history when a car changes hands, service history across branches.
- `CustomerService`, `VehicleService`. A customer with vehicles cannot be deleted.

### Service catalog and mechanics
- Price list of jobs (shop-wide), mechanics per branch (no login; NIK encrypted, KTP photo on the private disk, shown only with `mechanic.view_identity`).
- Used jobs and mechanics can only be deactivated, never deleted.

### Parts, stock and suppliers
- Parts catalog (shop-wide prices), stock per branch with decimals for liter/meter/kg, low-stock threshold per part per branch with bell notifications, stock correction (opname) with a reason, suppliers.
- **All stock changes go through `StockService::move()`**: append-only `stock_movements`, stock can never go below zero (also a DB check).

### Cash
- Cash accounts per branch (Laci Kasir, Kas Kecil, banks), payment methods (each paying into one account, optional card surcharge, optional reference number), transfers between accounts, cash count with a reason.
- **All money movements go through `CashService`**: append-only `cash_movements`; cash drawer and petty cash can never go below zero, banks can.

### Purchases and supplier debt
- Receive goods (stock in, weighted average cost), pay now / partly / later, "Hutang supplier" list, payments over time from any account, cancel a payment or a purchase with a reason, bell reminders before and after the due date.
- Front Desk may pay small purchases on the spot: full amount only, from cash only, up to the branch's cash limit (default Rp 500.000).

### Work orders (SPK)
- One-screen check-in (plate search, or a new vehicle and customer inline), complaint, odometer (warns when lower), ready-by time, checklist, mechanics; status flow Draft → Antri → Dikerjakan ⇄ Tunggu part → Selesai → Ditagih → Ditutup (Batal with a reason before invoicing); services (typed price, catalog prefill) and parts (leave stock when added, return when removed or cancelled); printable job sheet without prices.

### Invoices
- From a finished SPK (quantities from the SPK, prices and discounts adjustable) or as a counter sale (any lines; "Pelanggan umum" allowed; parts leave stock), optional PPN (branch setting, copied onto each invoice), A5 print layout in plain black for 2-ply paper.
- Totals always calculated on the server (`InvoiceCalculator`): integer Rupiah, half-up rounding, tax on top or included.

### Customer payments
- One checkout = one kwitansi with any mix of methods; later checkouts may use other methods; card surcharge on top (kept on the payment, not the invoice); cash change; a due date is required when part stays unpaid; "Piutang pelanggan" list with bell reminders; A5 kwitansi with the amount in words (`Terbilang`).
- Corrections: Finance cancels one line or a whole kwitansi with a reason; nothing is deleted; reversing cash movements; the SPK closes when the invoice is paid and reopens if a payment is cancelled (`InvoiceSettlement`).

### Invoice editing
- Edit an issued invoice with a required reason; the number never changes; each previous version is kept (`invoice_revisions`), with a "Riwayat perubahan" page showing who, when, why and what changed; stock follows the difference; a total below what is paid is refused.

### Users, roles and the permission matrix (Phase 2)
- The owner adds staff with a password, edits role/branches, deactivates (logs them out), sets a new password; custom roles; a permission matrix per role (cards with toggles, phone-friendly).
- Nobody hands out more than they have; owner accounts only changed by an owner; nobody edits their own account there; at least one active owner always remains.

### Expenses (Phase 2)
- Record an expense from a cash account with an optional receipt photo (camera or gallery, private disk), filters and totals, corrections by voiding.
- Front Desk: cash only, up to the branch cash limit. Restricted categories ("Gaji & bonus" by default) are invisible without `expense.view_restricted` (Owner).

### Dashboard (Phase 2)
- Workshop today, low stock, customer debt, supplier bills due, cash balances for everyone allowed; sales, money received, expenses, 12-month chart, top services and parts, jobs per mechanic for `dashboard.view_financials`; gross profit only for the Owner.

### Shop and branch settings (Phase 2)
- Shop name and invoice lock period (Owner); branch name, address, phone, NPWP and logo (printed on all documents); tax and invoice footer on "Pajak & invoice".
- Invoices older than the lock period can't be changed except by the Owner (`invoice.update_locked`), and such changes are marked "after lock".

## 4. Roles as delivered

Roles are per shop and editable in the permission matrix; these are the defaults new shops get (`App\Enums\RoleName`).

| Role | In short |
|---|---|
| Owner (Pemilik) | everything; locked to all permissions |
| Branch Manager (Kepala Cabang) | everything in their branches except billing, roles, shop settings, restricted expenses, profit and locked invoices |
| Front Desk (Admin Depan) | customers, vehicles, SPK (incl. assigning mechanics and status), invoices (create, discount, correct unpaid ones), take payments, parts and stock (view), purchases (create, pay small ones from cash), small cash expenses, cash (view) |
| Finance (Keuangan) | Front Desk plus mechanics (incl. identity), invoice edit of paid invoices / cancel / tax, payment corrections, supplier payments, cash transfers and counts, expenses and categories, financial dashboard, reports |
| Warehouse (Gudang) | parts, stock, suppliers, purchases |

Permissions added during the build (all in `Permission`, `RoleName` and SPEC §2): `supplier_payment.on_receipt`, `expense.create_cash`, `expense.view_restricted`, `expense.manage_categories`, `dashboard.view_profit`, `invoice.update_locked`.

## 5. Pilot decisions (from our conversations)

These shaped the design. They are also recorded in SPEC §11.

- Accounts: Owner, Front Desk, Finance. Roles must stay configurable per shop.
- Mechanics are records without a login; NIK and KTP kept and protected. Finance may edit mechanics.
- Supplier debt, petty cash and paying one invoice with several methods are MVP scope.
- Stock quantities can be decimal; out-of-stock parts cannot be used; low-stock threshold per part per branch.
- Front Desk may pay small purchases and record small expenses from cash, up to one branch limit.
- Work orders: parts leave stock when added; Front Desk and Finance set line prices; a draft status for estimates; printable SPK.
- Invoices: A5 on 2-ply paper; "Pelanggan umum" for counter sales; the branch manager decides whether invoices carry tax; Front Desk may give discounts and correct unpaid invoices (to be made stricter later).
- Payments: a due date is required when part stays unpaid; Finance may cancel single payment lines; nothing is ever deleted.
- Expenses: no rent category (the shop owns its place); salaries restricted to the Owner.
- Dashboard: card fees not counted as income; gross profit only for the Owner.
- Settings: logo per branch; the Owner can still fix locked invoices, marked in the history.

## 6. Security measures in place

- Tenant isolation on every model (global scopes, write guards, tenant-scoped route bindings), with an isolation test per model.
- Branch scoping for all operational data; the active branch comes from the session only.
- Every action authorized on the server (policies and form requests); hiding buttons is only for clarity.
- Money and totals always recalculated on the server; integer Rupiah everywhere, no floats.
- Append-only ledgers for stock and cash; corrections are new rows with a reason; nothing financial is deleted.
- Audit log for every change to work orders, invoices, payments, purchases, stock, cash, expenses, users, roles and settings.
- Private storage for KTP photos, receipt photos and logos, served only after an authorization check, never cached publicly.
- Rate limits on login, password reset and lookup endpoints; sessions ended on deactivation and password reset.
- Repository hygiene: the old database dump was removed from the git history (all branches and tags); nothing personal or secret is committed.

## 7. Known limitations and deferred items

- **Not built yet (pilot launch):** production setup and backups, CSV import. The user guide has a first draft in `docs/PANDUAN.md`.
- **Deferred by the SPEC (after pilot):** public sign-up and invitations, subscription billing, kanban board, WhatsApp sharing and reminders, multi-branch dashboard, reports beyond the dashboard, stock transfers between branches (permission exists, no screen).
- **Server-side PDFs:** printing uses the browser's print dialog ("Save as PDF" works). A real PDF and WhatsApp sharing belong to the deferred WhatsApp item.
- **Printing was not tested on paper** (no A5 paper available yet). Check the layout on the shop's printer before go-live.
- **Salaries paid from cash** show as an amount leaving that cash account to anyone who can view it (the description is hidden). Paying salaries from a bank account avoids this.
- **Permissions after go-live:** once a shop is live, new permissions only reach its existing roles through an explicit migration or command (the synchronizer only updates the Owner role). Before go-live, `migrate:fresh --seed` is enough.
- **Front Desk discounts and invoice corrections** are deliberately generous for the pilot; tighten them later in the permission matrix.

## 8. Next steps

1. **Review** this branch chain and push to `main` (section 2).
2. **Pilot launch** (SPEC §9):
   - production server with HTTPS, daily backups with a tested restore, error tracking;
   - import Bengkel Kalimas Lumas' customers, vehicles and parts from CSV/Excel;
   - a short user guide in Indonesian for Front Desk, Finance and the Owner.
3. **Before go-live:** check printing on the real printer and paper; create the real shop with `php artisan tenant:create`; set the branch details, logo, tax choice, cash accounts, payment methods and opening balances.

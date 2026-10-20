# Lending Management — Phased Roadmap

A working reference for building the lending domain on top of the existing
Laravel + Inertia (React) starter. Phases are meant to be delivered in order;
each later phase depends on the data model established earlier.

> Status legend: ✅ done · 🔨 in progress · ⏳ planned

---

## Phase 0 — Foundations ✅

**Goal:** identity, access, and admin scaffolding.

- Fortify auth (login, register, password reset, 2FA, passkeys)
- `Role` enum + `role` column on `users` (`SuperAdmin` / `Admin` / `User`)
- Admin panel + user management with a policy matrix
- ⚠️ Email-verification gate temporarily removed for development
  (`verified` middleware pulled from `routes/web.php` and `routes/settings.php`)
  — **restore before production**

**Remaining:** ~~decide the permissions model~~ — **resolved:** stick with the
three roles for now; add granular abilities only when a concrete need appears.

---

## Phase 1 — Borrowers & Loan Products ✅

**Goal:** the actors and the terms they can be offered.

| Deliverable | Detail |
|---|---|
| `Borrower` model | name, national ID, phone, email, address, KYC status, optional link to `users` |
| `LoanProduct` model | name, interest rate, `InterestMethod`, term options, fees, min/max principal, repayment frequency |
| Enums | `InterestMethod` (Flat, DecliningBalance), `RepaymentFrequency` (Monthly, Weekly, Biweekly) |
| Admin UI | Borrowers CRUD, Loan Products CRUD (list/create/edit) |
| Policies | Admins manage all; a borrower may view only their own profile |
| Tests | validation, authorization, relationship loading |

**Acceptance:** an admin can create a product and register a borrower; a
borrower cannot see others. ✅

**Delivered:** `Borrower` + `LoanProduct` models, `InterestMethod` /
`RepaymentFrequency` / `KycStatus` enums, factories + seeders, policies,
Form Requests, admin CRUD controllers + routes, React pages
(list/create/edit) and forms, sidebar nav, unit + feature tests.

---

## Phase 2 — Loans (Origination) ✅

**Goal:** capture a loan from application to approval.

| Deliverable | Detail |
|---|---|
| `Loan` model | borrower_id, loan_product_id, principal, rate (snapshot), term, frequency, `LoanStatus`, applied/approved/disbursed dates |
| `LoanStatus` enum | Draft → Pending → Approved → Rejected → Disbursed → Active → Closed → Defaulted → WrittenOff |
| Flow | apply → review → approve/reject → (disburse handled in Phase 4) |
| `LoanService` | create loan, snapshot product terms, guard illegal transitions |
| Admin UI | loan list (filterable by status), create, detail view |
| Policies | admins manage; borrower sees only own loans |

**Acceptance:** a loan can be created from a product and moved Pending → Approved,
with terms snapshotted so later product edits do not change existing loans. ✅

**Delivered:** `Loan` model + `loans` migration (term-snapshot columns, status
index), `LoanStatus` enum (labels, `allowedTransitions`, `canTransitionTo`,
`isFinal`), `LoanFactory` + `LoanSeeder`, `LoanPolicy`, `StoreLoanRequest`
(validates principal/term within the product bounds and rejects inactive
products), `LoanService` (`originate` snapshots terms; guarded
`approve`/`reject`/`transition`), `Admin\LoanController` (list with status
filter, create, detail, approve/reject/delete), React pages
(`admin/loans` index/create/show) with `LoanForm` + `LoanStatusBadge`, sidebar
nav, and unit + feature tests.

**Deferred:** a borrower-facing "my loans" view — there is no borrower portal
yet, so admins manage all loans, mirroring the Phase 1 borrower policy.

---

## Phase 3 — Amortization Schedule ✅

**Goal:** turn an approved loan into dated installments.

| Deliverable | Detail |
|---|---|
| `LoanInstallment` model | loan_id, sequence, due_date, principal_due, interest_due, fee_due, balance |
| `AmortizationCalculator` | Flat and DecliningBalance strategies |
| Trigger | schedule generated on approval; regenerated on disbursement (Phase 4) |
| Admin UI | schedule shown on loan detail |
| Tests | golden-number tests for both methods; totals reconcile to principal |

**Acceptance:** for a known principal/rate/term, installments sum exactly to
principal + interest. ✅

**Delivered:** `LoanInstallment` model + `loan_installments` migration
(sequence, due_date, principal/interest/fee due, running balance, unique
`loan_id` + `sequence`), `AmortizationCalculator` (Flat and DecliningBalance,
weekly/biweekly/monthly frequencies, processing-fee spread, cents-exact
reconciliation), `ScheduleService` (builds or regenerates a schedule from a
start date), `LoanService::approve` builds the schedule, a schedule table on
the loan detail page, and golden-number unit tests plus schedule feature tests.

---

## Phase 4 — Disbursement & Lifecycle ✅

**Goal:** money out and a real state machine.

| Deliverable | Detail |
|---|---|
| `Disbursement` record | loan_id, amount, method, reference, disbursed_at, recorded_by |
| State machine | enforce valid transitions; immutable once disbursed |
| Admin UI | disburse action + confirmation, lifecycle timeline on loan detail |
| Audit | who did what, when (loan_events + recorded_by on disbursements) |

**Acceptance:** a loan cannot be disbursed twice or edited after disbursement.

**Delivered:** `Disbursement`/`LoanEvent` models + migrations + factories, `DisbursementMethod`/`LoanEventType` enums, `LoanService::disburse()` (creates disbursement, regenerates schedule from disbursed amount/date, transitions Approved→Disbursed→Active), `DisburseLoanRequest`, controller action + payloads (`disbursements`, `events`, `disbursementMethods`), `LoanPolicy::disburse()` + immutable-after-disbursement guards, routes, frontend dialog + disbursements/lifecycle sections, backend tests (7) + policy coverage. Seeders updated for fresh installs; backend generates audit events with actor on all transitions.

---

## Phase 5 — Repayments & Ledger (Money In) ✅

**Goal:** record payments, allocate to installments, close loan when fully repaid.

| Deliverable | Detail |
|---|---|
| `Payment` model + migration | loan_id, installment_id (nullable), amount (decimal 15,2), method (enum), reference, notes, payment_date, recorded_by |
| Allocation model | `LoanPaymentAllocation` with principal/interest/fee split, ties payment → installment |
| Allocation service | apply payments oldest-first; handle partials/remainders; update balances |
| State machine | when fully repaid transition `Active` → `Closed` (set `closed_at`); block payments on non-Active |
| Admin UI | record payment dialog, payments history, totals (paid, outstanding, arrears) on loan detail |
| Audit | loan events; who recorded payment |
| Tests | unit (allocation math) + feature (auto-close, blocking, correct allocation) |

**Acceptance:** fully repaid loan closes automatically; partial payments allocate correctly; cannot pay closed/rejected loans.

**Delivered:** `Payment`/`LoanPaymentAllocation` models + migrations + factories, `PaymentMethod` enum, `RecordPaymentRequest` with Active-only guard, `LoanPolicy::recordPayment`, `Loan` relations (`payments`, `allocations`), `RepaymentService` (oldest-installment-first allocation, fee → interest → principal priority, overpayment rejection, auto-close) and `closed_at` on loans. Controller action + `loans.payments.store` route, payment methods/totals payloads, record-payment dialog, payments history and paid/outstanding/arrears totals on the loan detail page, per-installment paid/outstanding progress, and `LoanEventType::PaymentRecorded` audit entries. Covered by allocation tests (oldest-first, partial, spill-over, overpayment, auto-close) and feature tests (record, block non-active, validation, auto-close, audit, detail payload) plus policy coverage.

---

## Phase 6 — Arrears, Penalties & Rescheduling ✅

**Goal:** handle the real world (late payers).

- Overdue detection + scheduled command
- Penalty / late-fee accrual rules (per product)
- Restructuring / refinance / write-off actions
- Admin UI: aging view, overdue flags

**Acceptance:** overdue installments flagged daily; penalties accrue per
configured rule.

**Delivered:** penalty configuration on both `loan_products` and `loans`
(`penalty_rate` percent per month, `grace_period_days`, snapshotted at
origination like the other product terms) and `days_past_due` on loans.
`PenaltyService` accrues one month's penalty per whole 30-day period an
installment sits past its due date plus the grace period, tracking
`penalty_accrued` / `penalty_accrued_periods` per installment so repeated runs
are idempotent, and records `LoanEventType::PenaltyAccrued`. Two scheduled
commands run daily: `loans:accrue-penalties` (01:00) and `loans:flag-overdue`
(01:30, refreshes `days_past_due`, clears it on terminal loans and defaults
active loans past `config('lending.default_after_days')`). Payments now settle
penalties first (`penalty_allocated` on allocations, overpayment guard and
auto-close include penalties). Loan detail exposes per-installment
`penalty_accrued` / `overdue` / `days_past_due`, penalty and aging totals
(current / 1–30 / 31–60 / 61–90 / 90+) and the loans index gains an arrears
column plus an "Overdue only" filter. Lifecycle actions `markDefaulted`,
`writeOff` and `restructure` (re-amortises the outstanding principal onto a
fresh term, reactivates defaulted loans) with policies, Form Request, routes
and dialogs. Restructuring preserves the payment audit trail: allocations
detach from deleted installments (`nullOnDelete`) but keep their `sequence`,
and the paid total is now lifetime cash received. Covered by penalty accrual
tests (grace, idempotency, repeat periods, command, event), arrears tests
(flag refresh, threshold defaulting, index filter, detail payload) and
lifecycle tests (default, write-off, restructure, validation, authorisation,
history preservation) plus policy coverage.

---

## Phase 7 — Reporting & Dashboards ✅

**Goal:** see the portfolio.

- Portfolio summary, collections due (today/week), aging buckets,
  PAR (portfolio at risk), interest income
- CSV / PDF export
- Role-aware dashboards (admin overview vs. borrower summary)

**Acceptance:** numbers reconcile with raw data; exports match on-screen.

**Delivered:** `ReportingService` is the single source for every figure:
portfolio summary (loans by status, principal disbursed, outstanding
principal/interest/fees/penalties, arrears, collected totals and interest
collected), aging buckets (current / 1–30 / 31–60 / 61–90 / 90+), portfolio at
risk (PAR, PAR30, PAR60, PAR90 as amount and share of outstanding), collections
(due today, due within 7 days, overdue, with installment lists) and recent
payments. Only schedules on disbursed/active/defaulted loans count as
receivables, so a schedule generated for an approved-but-undisbursed loan is a
plan rather than exposure — the loan report column therefore sums exactly to
the summary's outstanding total. The admin dashboard now shows portfolio KPIs,
collections, PAR, aging, recent payments and account counts; the borrower
dashboard shows the linked borrower's outstanding, arrears, next due date,
upcoming installments, loans and payments, with an empty state when no borrower
profile is linked. A new `/admin/reports` page renders the aging table,
collections and loan report with CSV exports (summary, loans, collections)
built from the same service methods the page uses. PDF export is deferred
(Decision 10). Covered by `ReportingTest` (reconciliation with raw records,
aging, PAR, collection windows, undisbursed schedules, page payloads, CSV
contents, borrower dashboard) plus the reports route in the
`AdminAccessTest` role matrix.

---

## Phase 8 — Notifications ⏳

**Goal:** proactive comms.

- Due-date reminders, overdue notices, payment receipts, statements
- Email + (optional) SMS channels, queued
- Templates + per-borrower preferences

---

## Phase 9 — Hardening & Deployment ⏳

- Audit log, granular permissions, rate limiting on money endpoints
- Backups, queue workers, scheduled tasks
- Deploy via Laravel Cloud (`deploying-to-cloud` skill)

---

## Cross-cutting (every phase)

- Policies + Form Requests + feature tests
- Factories / seeders for each model
- Wayfinder regeneration, `pint`, `types:check`, production build
- Migrations are additive; no destructive edits to shipped migrations

---

## Decisions

Settled during Phase 1, except where noted.

| # | Decision | Resolution |
|---|---|---|
| 1 | Lending type | Consumer/personal loans for now; model stays extensible (collateral/guarantors deferred) |
| 2 | Interest convention | Both, per product — `InterestMethod` (Flat, DecliningBalance) |
| 3 | Currency & rounding | Single currency; money `decimal(15,2)`, rates `decimal(8,4)` percent |
| 4 | Borrower vs. User | Borrower is a staff-managed record with an optional `user_id` link |
| 5 | Schedule generation trigger | On approval, regenerated on disbursement (Phase 4 re-runs the calculator against the actual disbursed amount and date) |
| 6 | Permissions model | Stick with the three roles |
| 7 | Overpayment handling | Rejected — a payment may not exceed the outstanding balance (incl. accrued penalties); no advance-credit ledger |
| 8 | Penalty model | Per product, a monthly percentage of the overdue amount plus a grace period; accrued by a daily command, one period per whole 30 days past due |
| 9 | Restructuring | Re-amortises the outstanding principal onto a new term from the loan's own snapshotted terms; payment history is preserved and never rewritten |
| 10 | Report export format | CSV first (summary, loans, collections); PDF is deferred until a rendering dependency (`barryvdh/laravel-dompdf` or `spatie/laravel-pdf`) is approved, and will reuse the same `ReportingService` figures |

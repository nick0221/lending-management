# Lending Management — Enhancement & UX Roadmap

Phases 0–9 delivered the lending domain end to end. This roadmap revisits that
work for **consistency, usability, and shadcn-first composition** — it does not
replace `docs/LENDING_ROADMAP.md`, it layers on top of it.

> Status legend: ✅ done · 🔨 in progress · ⏳ planned · 🔒 needs dependency approval

---

## Ground rules

1. **shadcn first.** Use `resources/js/components/ui/*` before hand-rolled
   markup. New primitives follow the same `data-slot` + `cn()` conventions and
   the `new-york` style in `components.json`.
2. **No surprise dependencies.** Radix packages that are already installed are
   fair game; anything new is listed in the dependency gates below and needs
   explicit approval.
3. **One pattern per intent.** Filters = tabs, row actions = dropdown menu,
   destructive actions = alert dialog, detail panes = sheet or tabs, transient
   feedback = sonner. No page invents its own variant.
4. **The URL is the state.** Filters, sorting and pagination live in query
   strings so lists stay bookmarkable and server-driven.
5. **Server truth.** Money and derived balances come from Inertia props; the
   client never recomputes totals.
6. **Accessible by default.** Radix primitives, labelled inputs, visible focus
   rings, keyboard navigation, and a layout that works at 375 px.
7. **Behaviour changes get tests.** Pure styling and layout changes do not.

---

## Current UI audit (October 2026)

**Installed primitives:** alert, alert-dialog, avatar, badge, breadcrumb, button,
calendar, card, checkbox, collapsible, dialog, dropdown-menu, input, input-otp,
label, navigation-menu, pagination, popover, select, separator, sheet, sidebar,
skeleton, sonner, spinner, switch, table, tabs, toggle, toggle-group, tooltip.

**Missing / not yet added:** progress, scroll-area, chart.

**Findings**

| Area                | Finding                                                                                                                   |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Tables              | 14 hand-written `<table>` blocks across 8 pages, each with its own borders, spacing and empty row.                        |
| Filters             | Loans used 8 stacked buttons for status (now tabs); Borrowers and Products have no status filter at all.                  |
| Pagination          | The same Previous / Page x of y / Next block is duplicated on 5 pages.                                                    |
| Destructive actions | Row-level deletes submit immediately — no confirmation step anywhere.                                                     |
| Loan detail         | One long scroll mixing schedule, payments, disbursements and audit trail, with 7 primary actions competing for attention. |
| Reports             | Three sections stacked on one page, no section navigation and one export button per table.                                |
| Cards               | `Card` is only used by `StatCard` and auth screens; most panels are ad-hoc `rounded-xl border` divs.                      |
| Empty states        | Single line of muted text; no illustration, guidance or primary action.                                                   |
| Loading             | Inertia progress bar only; no skeletons for deferred props.                                                               |
| Borrowers           | No detail page — a borrower exists only as a row and an edit form.                                                        |
| Search              | No search on any list; large datasets are paginated only.                                                                 |

---

## E0 — Design system foundation (cross-cutting)

Do these once, then reuse everywhere.

| ID    | Item              | Detail                                                                | Effort | Status |
| ----- | ----------------- | --------------------------------------------------------------------- | ------ | ------ |
| E0.1  | `ui/tabs`         | Standard shadcn Tabs on `@radix-ui/react-tabs`.                       | S      | ✅     |
| E0.2  | `ui/table`        | Styled Table primitives with consistent header, row and cell spacing. | S      | ✅     |
| E0.3  | `ui/alert-dialog` | Confirmation dialog for destructive actions.                          | S      | ✅     |
| E0.4  | `ui/pagination`   | Shared page navigator with first/last and current page.               | S      | ✅     |
| E0.5  | `PageHeader`      | Title, description, breadcrumbs slot and action slot.                 | S      | ✅     |
| E0.6  | `EmptyState`      | Icon, title, description and optional action.                         | S      | ✅     |
| E0.7  | `SectionCard`     | `Card` wrapper with header, description and actions.                  | S      | ✅     |
| E0.8  | `ConfirmDelete`   | `AlertDialog` + Inertia `Form` composition for row deletes.           | S      | ✅     |
| E0.9  | `FilterTabs`      | URL-driven tabs used by every list page.                              | S      | ✅     |
| E0.10 | Status badges     | Keep `LoanStatusBadge`, `KycBadge`, `RoleBadge`; align colour tokens. | S      | ✅     |
| E0.11 | `ui/switch`       | Boolean preferences instead of raw checkboxes.                        | S      | ✅     |
| E0.12 | Date range picker | `popover` + `calendar` for reports and audit filters.                 | M      | ✅     |

**Dependency gates**

| Package                         | Needed for           | State                  |
| ------------------------------- | -------------------- | ---------------------- |
| `@radix-ui/react-tabs`          | E0.1                 | ✅ approved, installed |
| `@radix-ui/react-alert-dialog`  | E0.3 / E0.8          | ✅ approved, installed |
| `@radix-ui/react-switch`        | E0.11                | ✅ approved, installed |
| `react-day-picker` + `date-fns` | E0.12                | ✅ approved, installed |
| `@radix-ui/react-popover`       | E0.12                | ✅ approved, installed |
| `cmdk`                          | global search (F2)   | ✅ approved, installed |
| `recharts`                      | report charts (E7.4) | ✅ approved, installed |

---

## E1 — Borrowers & Loan Products

| ID   | Item                 | Detail                                                                                                                                                              | Status |
| ---- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------ |
| E1.1 | Borrower tabs        | All / Verified / Pending / Rejected with counts, using `FilterTabs`. Requires a `kyc_status` filter on the index query.                                             | ✅     |
| E1.2 | Borrower search      | Search by name, email or phone; debounced, URL-backed.                                                                                                              | ✅     |
| E1.3 | Borrower table       | `ui/table` + `EmptyState` + shared pagination.                                                                                                                      | ✅     |
| E1.4 | Row actions          | `DropdownMenu` with View, Edit, Email statement, Delete.                                                                                                            | ✅     |
| E1.5 | Delete confirmation  | `ConfirmDelete` on borrowers, users, products and loans.                                                                                                            | ✅     |
| E1.6 | Borrower detail page | `/admin/borrowers/{borrower}`: profile card, KYC status, notification preferences, active loans, payment history and activity. The biggest missing flow in the app. | ✅     |
| E1.7 | Product tabs         | Active / Inactive with usage counts (how many loans reference the product).                                                                                         | ✅     |
| E1.8 | Product cards        | Terms at a glance: rate, method, frequency, min/max amount and term.                                                                                                | ✅     |
| E1.9 | Form layout          | Create/edit forms split into `SectionCard`s with a sticky summary and inline validation.                                                                            | ✅     |

**Done when:** borrowers can be found, opened, reviewed and removed safely
without leaving the list; products show their status and usage.

---

## E2 — Loans

| ID    | Item                | Detail                                                                                                                | Status |
| ----- | ------------------- | --------------------------------------------------------------------------------------------------------------------- | ------ |
| E2.1  | Status tabs         | Replace 8 filter buttons with `FilterTabs`; keep the overdue toggle.                                                  | ✅     |
| E2.2  | Tab counts          | Show per-status counts in tab labels (single grouped query).                                                          | ✅     |
| E2.3  | Loan search         | Search by borrower name or loan id; URL-backed.                                                                       | ✅     |
| E2.4  | Sortable columns    | Principal, applied date and arrears via query params and table header buttons.                                        | ✅     |
| E2.5  | Row actions         | `DropdownMenu`: View, Email statement, Delete — instead of two inline buttons.                                        | ✅     |
| E2.6  | Delete confirmation | `ConfirmDelete` before deleting a loan.                                                                               | ✅     |
| E2.7  | Detail tabs         | Loan page split into Overview / Schedule / Payments / Disbursements / Activity.                                       | ✅     |
| E2.8  | Sticky summary      | Principal, outstanding, arrears and next due pinned while scrolling.                                                  | ✅     |
| E2.9  | Action hierarchy    | Approve / Reject / Disburse as primary actions; default, write-off, restructure and statement under an overflow menu. | ✅     |
| E2.10 | Status timeline     | Vertical timeline built from `loan_events`.                                                                           | ✅     |
| E2.11 | Empty states        | Explain what each status filter means and offer "New loan".                                                           | ✅     |

**Done when:** the loan list reads like a queue, and the detail page reads like a
file — not a wall of sections.

---

## E3 — Interest engine

| ID   | Item               | Detail                                                                                                                   | Status |
| ---- | ------------------ | ------------------------------------------------------------------------------------------------------------------------ | ------ |
| E3.1 | Schedule preview   | On the create form, show the first three installments before submitting (needs a preview endpoint or shared calculator). | ✅     |
| E3.2 | Method explainer   | Tooltip / popover explaining flat vs reducing balance with a worked example.                                             | ✅     |
| E3.3 | Validation summary | Show product limits as helper text and block out-of-range input client-side.                                             | ✅     |

---

## E4 — Disbursements

| ID   | Item                 | Detail                                                                                                       | Status |
| ---- | -------------------- | ------------------------------------------------------------------------------------------------------------ | ------ |
| E4.1 | Disbursement dialog  | Confirm amount, method, reference and date in a `Dialog`; default to approved principal.                     | ✅     |
| E4.2 | Schedule impact      | Warn when the disbursed amount or date differs from the approved terms and the schedule will be regenerated. | ✅     |
| E4.3 | Disbursement history | Move into the detail page's Disbursements tab with recorded-by and reference.                                | ✅     |

---

## E5 — Repayments

| ID   | Item               | Detail                                                                                      | Status |
| ---- | ------------------ | ------------------------------------------------------------------------------------------- | ------ |
| E5.1 | Payment dialog     | Outstanding summary above the form; quick-fill chips for next installment and full balance. | ✅     |
| E5.2 | Allocation preview | Show how the amount will be applied (penalty → fee → interest → principal) before saving.   | ✅     |
| E5.3 | Receipt            | View / print / re-send the receipt for a payment.                                           | ✅     |
| E5.4 | Payment filters    | Filter the Payments tab by method and date.                                                 | ✅     |
| E5.5 | Reversal           | Reverse a payment with reason, audit event and schedule recalculation. Functional change.   | ✅     |

---

## E6 — Penalties & collections

| ID   | Item              | Detail                                                                              | Status |
| ---- | ----------------- | ----------------------------------------------------------------------------------- | ------ |
| E6.1 | Aging visual      | CSS bar breakdown for current / 1-30 / 31-60 / 61-90 / 90+.                         | ✅     |
| E6.2 | Arrears column    | Sortable arrears with a days-past-due badge on the loan list.                       | ✅     |
| E6.3 | Bulk reminders    | Select overdue loans and queue reminders (uses existing notifications).             | ✅     |
| E6.4 | Penalty explainer | Card showing the product's monthly rate and grace period next to accrued penalties. | ✅     |

---

## E7 — Reports

| ID   | Item        | Detail                                                                       | Status |
| ---- | ----------- | ---------------------------------------------------------------------------- | ------ |
| E7.1 | Report tabs | Portfolio / Aging / Collections / Arrears as tabs instead of one long page.  | ✅     |
| E7.2 | Date range  | Shared date-range control (E0.12) applied to every report.                   | ✅     |
| E7.3 | Export menu | One `DropdownMenu` with a CSV action per report instead of separate buttons. | ✅     |
| E7.4 | Charts      | `recharts` bars for portfolio risk, aging and collections.                    | ✅     |
| E7.5 | Print view  | Print stylesheet for the portfolio summary.                                  | ✅     |

---

## E8 — Notifications

| ID   | Item           | Detail                                                                       | Status |
| ---- | -------------- | ---------------------------------------------------------------------------- | ------ |
| E8.1 | Delivery log   | Notifications tab on the loan / borrower page backed by `notification_logs`. | ✅     |
| E8.2 | Resend         | Resend a failed or skipped notification from the log.                        | ✅     |
| E8.3 | Switch toggles | Replace the four preference checkboxes with `ui/switch` rows.                | ✅     |
| E8.4 | Email preview  | Preview the rendered markdown email before sending a statement.              | ✅     |
| E8.5 | SMS channel    | Admin-configurable HTTP SMS gateway with log fallback and per-channel logs. | ✅     |

---

## E9 — Hardening & operations

| ID   | Item          | Detail                                                                                             | Status |
| ---- | ------------- | -------------------------------------------------------------------------------------------------- | ------ |
| E9.1 | Audit table   | `ui/table` with event badges, actor and relative time.                                             | ✅     |
| E9.2 | Diff sheet    | Open an audit row in a `Sheet` showing before/after values.                                        | ✅     |
| E9.3 | Settings page | Organisation name, currency symbol, default penalty/grace and rate limits — currently `.env` only. | ✅     |
| E9.4 | System status | Cards for queue, scheduler, mail and storage health.                                               | ✅     |
| E9.5 | Role matrix   | Read-only table of what each role can do, sourced from the policies.                               | ✅     |

---

## Functional backlog (non-UI)

| ID  | Item                                 | Why                                                       | Effort | Status |
| --- | ------------------------------------ | --------------------------------------------------------- | ------ | ------ |
| F1  | Borrower detail + statement download | Support conversations start with "show me this borrower". | M      | ✅     |
| F2  | Global search                        | Find a borrower or loan from anywhere (`cmdk` gate).      | M      | ✅     |
| F3  | Loan calculator                      | Quote a schedule before creating a loan.                  | S      | ✅     |
| F4  | Payment reversal                     | Correct mistakes without editing the database.            | M      | ✅     |
| F5  | Borrower CSV import                  | Onboard existing books.                                   | M      | ✅     |
| F6  | Bulk loan actions                    | Approve / remind in batches.                              | M      | ✅     |
| F7  | PDF statements & exports             | Per-loan printable statement with Print / Save as PDF; report summary print view. Server exports stay CSV. | M      | ✅     |
| F8  | SMS channel                          | Second notification channel using an admin-configurable generic HTTP gateway. | L      | ✅     |
| F9  | Multi-currency                       | Only if the business needs it.                            | L      | 🔒     |

---

## Milestones

| Milestone              | Scope                              | Status  |
| ---------------------- | ---------------------------------- | ------- |
| M1 — Foundations       | E0.1, E0.9, E2.1                   | ✅      |
| M2 — List consistency  | E0.2–E0.8, E1.1–E1.5, E2.2–E2.6    | ✅      |
| M3 — Detail pages      | E1.6, E2.7–E2.10, E7.1–E7.3        | ✅      |
| M4 — Find anything     | E1.2, E2.3, F1, F2                 | ✅      |
| M5 — Operational depth | E5.5, E6.3, E8.1, E9.1–E9.4, F3–F5 | ✅      |

---

## Definition of done (per item)

- Uses a `components/ui` primitive; no new one-off visual pattern.
- `npm run types:check` and `npm run build` pass.
- `vendor/bin/pint --dirty` when PHP changed.
- Behaviour changes covered by a feature test; filters tested for query state.
- Checked at 375 px and 1280 px, light and dark.
- Keyboard reachable, labelled, and no console errors.
- Committed with a message that names the roadmap ID (e.g. `E2.1`).

---

## Progress log

| Date       | Item                                                                                                        |
| ---------- | ----------------------------------------------------------------------------------------------------------- |
| 2026-10-20 | Added `ui/tabs`, `FilterTabs`, and moved the Loans status filter from 8 buttons to tabs (E0.1, E0.9, E2.1). |
| 2026-10-20 | Shared list kit: `ui/table`, `ui/pagination`, `DataPagination`, `EmptyState`, `ui/alert-dialog`, `ConfirmDelete`; applied to borrowers, users, loan products and loans. Borrowers filter by KYC status with counts (E1.1); loans show per-status counts (E2.2) and keep the overdue toggle across tabs (E0.2, E0.3, E0.4, E0.6, E0.8, E1.1, E1.3, E1.5, E2.2, E2.6, E2.11). |
| 2026-10-20 | Added `PageHeader` and URL-backed `SearchInput`; borrowers search name/email/phone (E1.2) and loans search borrower/loan id (E2.3), both debounced and preserved across tabs. Loan products gained Active/Inactive tabs with counts plus a loans-usage column (E1.7); list pages now share `PageHeader` (E0.5). |
| 2026-10-20 | Loan queue: sortable Principal/Arrears/Applied headers backed by query params (E2.4), and row actions moved into a `DropdownMenu` with View, Email statement and Delete behind the shared confirm dialog (E2.5). Sorting by arrears uses a correlated subquery that mirrors the list calculation. |
| 2026-10-20 | Borrower file: `/admin/borrowers/{borrower}` with summary stats, profile, notification preferences, loans, payments and super-admin activity (E1.6). Added `SectionCard` for detail sections (E0.7); the register links names to the file and uses a View/Edit/Delete row menu (E1.4). Row statements stay on the loan file. M2 complete. |
| 2026-10-20 | Loan file redesigned around URL-backed tabs (Overview, Schedule, Payments, Payouts, Activity) with a sticky summary of status, principal, outstanding, arrears and next due (E2.7, E2.8). Actions are split into primary decisions and a More menu for restructure, statement, default, write-off and delete (E2.9); the lifecycle is now a vertical timeline (E2.10). |
| 2026-10-20 | Reports split into URL-backed Portfolio, Aging, Collections and Arrears tabs, with a new arrears view sorted by exposure and consolidated loan tables (E7.1). The three CSV buttons became one Export menu (E7.3). E7.2 stays gated on the date-range dependency. |
| 2026-10-20 | The payment dialog now opens with the outstanding balance split by penalty, fee, interest and principal, offers next-installment and full-balance chips, and previews the allocation before saving (E5.1, E5.2). The preview walks installments oldest-first to mirror `RepaymentService`. The detail page's tabs also completed the disbursement history move (E4.3) and the existing disbursement dialog satisfied E4.1. |
| 2026-10-20 | Disbursement warns when the amount or date changes and the schedule will be regenerated (E4.2). The aging report now draws its bucket bars in CSS (E6.1), the loan list already had sortable arrears plus a days-late caption (E6.2), and the loan balance card explains the monthly penalty rate, grace period, and amount accrued so far (E6.4). |
| 2026-10-20 | Added a Notifications tab to the loan file listing the emails sent to the borrower, with type labels and sent times from `notification_logs` (E8.1). The loan and report tab strips now scroll the active tab into view on narrow screens. |
| 2026-10-20 | Rebuilt the audit log on the shared table, page header, cards and pagination with event badges, actor details and relative times (E9.1). Every row opens a `Sheet` with the actor, record, IP, user agent and a before/after table for each changed field (E9.2); the payload now carries old and new values for every attribute. |
| 2026-10-20 | Overdue loans can now be selected on the loan list, all at once or one by one, and sent an overdue reminder from a confirmation dialog (E6.3). The endpoint reuses the scheduled notice logic, so preferences and the once-a-day dedupe still apply, and reports how many were queued or skipped through a flash toast. |
| 2026-10-20 | Added a super-admin system status page with application, database, queue, mail, scheduler and storage cards (E9.4). The queue card reports pending, failed and the oldest pending job, the scheduler lists each command with its next run, and storage shows free space and a usage bar. |
| 2026-10-21 | Rebuilt the loan origination form around two section cards and a sticky summary, with a live preview of the first three installments and full-term totals from a new preview endpoint (E3.1). Product limits now appear as helper text under the amount and term inputs (E3.3), and the interest rate has a tooltip explaining flat versus reducing balance with an example (E3.2). |
| 2026-10-21 | Replaced the loan product table with a responsive card grid that shows the rate and method, repayment frequency, amount and term limits, processing fee, late penalty and loan usage at a glance (E1.8). Edit and delete moved into the card's overflow menu, with deletion behind the shared confirm dialog. |
| 2026-10-21 | Payment rows now open a receipt sheet with the allocation breakdown, a print action and an email re-send that bypasses the once-only receipt key (E5.3). The standalone receipt page prints cleanly and reports the recipient through a toast. The Payments tab gained URL-backed method and date filters, with a total count in the tab badge and a clear action (E5.4). |
| 2026-10-21 | Added a printable portfolio summary at `/admin/reports/print`, reachable from a Print summary button on the reports page (E7.5). It lays out the headline figures, outstanding and collected composition, portfolio at risk, aging buckets and collections on one clean sheet, with print-specific colours and no app chrome. |
| 2026-10-21 | Rebuilt the borrower, user and loan product create/edit forms around section cards with a sticky live summary and the primary action (E1.9). Each summary reflects the entered name, contact, role or product terms, and the forms keep inline validation and a cancel route back to the list. The shared `SummaryRow` was extracted from the loan form so every form uses the same label/value rhythm. |
| 2026-10-21 | Gave the loan, KYC and role badges a shared semantic palette: blue for approved and admin, emerald for active and verified, amber for pending, red for rejected and defaulted, violet for super admin, and muted grey for closed, written off and regular users (E0.10). The badges all use the outline variant with dark-mode-aware tinted backgrounds. |
| 2026-10-21 | Email statement now opens a preview dialog instead of sending immediately (E8.4). A new JSON endpoint renders the queued markdown mail with the same schedule data as the real send, the dialog shows the recipient, subject and an isolated iframe of the email, and the send action stays disabled when the borrower has no address or has opted out. The preview is available from both the loan file and the loan queue. |
| 2026-10-21 | Added a read-only role matrix that asks the policies for every cell instead of duplicating a hand-written table (E9.5). The page groups borrower, product, loan, user and audit abilities, compares super admin, admin and user side by side, and is linked from the user register. Unsaved policy targets carry stable keys so the self-account checks stay accurate. |
| 2026-10-21 | Added payment reversal (E5.5). A payment can be reversed with a required reason; its allocations are released, the outstanding schedule is restored, a closed loan reopens, and a payment_reversed audit event is recorded. Reversed payments stay in the history with a badge and reason, are excluded from paid and collected totals, and their receipts are marked invalid and cannot be emailed. |
| 2026-10-21 | Added a loan calculator (F3). Admins can choose an active product, enter a principal and term, and see the full indicative schedule and whole-term totals without creating a loan. The quote is URL-backed, validates the product amount and term limits, and is linked from the loan queue. |
| 2026-10-21 | Added a super-admin settings page backed by a single settings row (E9.3). Organisation name, currency symbol, new-product penalty/grace defaults and the money, report and reminder rate limits are editable without touching .env; values are cached, shared with the UI, and used by receipts, reports, system status and the rate limiters. |
| 2026-10-21 | Added a borrower CSV importer (F5). Admins can download a template, upload a CSV and review imported, skipped and failed rows with line-level messages. Rows use the same validation as the create form, duplicate national IDs are skipped, valid rows are created in one transaction, and the import is create-only. |
| 2026-10-21 | Added bulk loan actions (F6). Pending loans can be selected in the loan queue and approved together in one transaction, while the same selection surface sends overdue reminders. Each action applies only to the selected loans it supports, and the confirmation dialogs report the affected subset. |
| 2026-10-21 | Added notification delivery tracking and resend (E8.2). Logs now distinguish queued, sent, failed and skipped mail, queue events record the sent time and failure reason, and staff can re-queue a notification from the loan file's Notifications tab after its source and the borrower's preferences are checked again. |
| 2026-10-21 | Added per-loan CSV statement downloads (F1). The statement carries the loan summary, the full schedule and the payment history, is reachable from the borrower file, the loan queue and the loan file, and works even when the borrower has no email address. A shared CSV exporter now backs report exports, the import template and statements. |
| 2026-10-21 | Added the shadcn `ui/switch` primitive and replaced the four borrower notification checkboxes with labelled switch rows (E0.11, E8.3). The live summary count follows the toggles and the existing hidden inputs keep the form payload unchanged. |
| 2026-10-22 | Added the shared popover and calendar primitives plus a date-range picker (E0.12, E7.2). Reports now narrow the loan cohort by application date, the audit log uses the same picker, and print and CSV exports carry the selected period. |
| 2026-10-22 | Added global search (F2). Staff can open a cmdk command palette with Ctrl/⌘ K or the header button, search borrowers by name, phone, email or national ID and loans by number or borrower, and jump straight to the record. The endpoint is limited to five matches per group and is staff-only. |
| 2026-10-22 | Restored the `verified` middleware on the borrower dashboard, admin area and settings routes. Unverified accounts now return to the verification prompt, including staff accounts, and the full suite is green. |
| 2026-10-22 | Added a printable loan statement (F7). The standalone page lays out the borrower and loan details, summary totals, full repayment schedule and payment history, and uses the browser Print / Save as PDF action, so no PDF package was needed. It is reachable from the loan file and loan queue, and PDF statements work without an email address. Server-side exports remain CSV. |
| 2026-10-22 | Upgraded the report charts to Recharts (E7.4). Portfolio at risk shows the PAR thresholds, aging compares outstanding bucket totals, and collections compares due today, this week and overdue. The standalone table remains the accessible source of the figures. |
| 2026-10-22 | Added the SMS notification channel (E8.5, F8). Due-soon, overdue and receipt notifications can now go by email and SMS, with per-channel delivery logs and resend support. A generic HTTP gateway handles JSON or form posts with configurable fields, headers and token; the log driver is the safe default until credentials are supplied. |
| 2026-10-22 | Moved SMS gateway configuration into Settings. Super admins can choose the log or HTTP driver, set the endpoint, method, payload format, sender ID, field mapping, timeouts, custom headers and an encrypted API token without editing `.env`. Environment values remain the fallback until a driver is saved. |
| 2026-10-22 | Standardised table navigation. The loan, borrower, product, user and activity lists now accept a 15/25/50/100 page size while keeping their filters, inner loan and borrower tables scroll within a fixed height with sticky headers, and the report loan and collection tables reveal 50 more rows at a time while totals and exports still cover the full dataset. |
| 2026-10-23 | Fixed the Recharts zero-size warning by giving the report bar chart a responsive fill instead of `ResponsiveContainer`, and aligned the page-size select with the other list controls. |
| 2026-10-24 | Improved admin list filtering and navigation. Page size survives filter tabs and activity filter submissions, filtered empty states offer Clear filters, the user register gained search, role tabs, per-role counts, verification status and joined dates, and the role matrix gained breadcrumbs, role drill-down counts and a policy check per cell. |
| 2026-10-24 | Closed account recovery and product workflow gaps. Admin-created accounts receive verification emails and an email change resets verification, users can resend verification or password reset links from a shared row menu, products with loans can no longer be deleted and can be activated or deactivated inline, and borrower files preselect the borrower when opening the loan form. |
| 2026-10-24 | Made the dashboard actionable. KPI cards link to filtered reports and queues, a Needs attention worklist combines overdue and upcoming installments, due-today and upcoming counts are surfaced, and aging and recent payments use the shared section card. |
| 2026-10-24 | Made the activity log searchable and navigable. Search covers description, event, record type, record ID, actor name and email; reset keeps the page size; and surviving borrower, loan, product and user records link from both the trail and the detail sheet. |
| 2026-10-24 | Added upcoming installments across open loans to the borrower file and linked every payment history row to its printable receipt. |
| 2026-10-25 | Kept the full validation error list for the last borrower import, showed the first 20 errors on the page, and added a permission-checked, session-scoped CSV export for the rest. |
| 2026-10-25 | Added bulk KYC updates to the borrower register. Staff can select up to 100 borrowers on the current page and apply verified, pending or rejected; every borrower is updated through its model so the audit trail records each change. |
| 2026-10-25 | Made the report loan tables sortable by loan, borrower, principal, paid, outstanding, arrears or days past due, with sorting over the full cohort before the 50-row window. Added a dedicated arrears CSV export and shared the loan export columns between both reports. |
| 2026-10-27 | Added This month, Last month, This quarter and This year presets to the shared date-range picker. Presets apply immediately and work on both reports and the activity log. |
| 2026-10-27 | Added a Test SMS card to Settings. Super admins can send a one-off message through the saved driver and gateway configuration, see gateway failures as an error toast, and get an honest result when the log driver is active. |
| 2026-10-27 | Made system status diagnostic. The page reports the SMS driver, endpoint host, sender ID and the last seven days of sent, failed, skipped and queued SMS deliveries, surfaces the latest failure reason, and adds a Copy diagnostics action for support requests. Clipboard copying now falls back to a hidden textarea when the async clipboard API is unavailable outside a secure context. |
| 2026-10-27 | Tightened the loans register. Every row now shows a linked loan number, borrower names open their file, principal and arrears use the configured currency, applied dates use the shared date formatter, and filtered empty states offer Clear filters alongside New loan. |
| 2026-10-27 | Added a product filter to the loans register. Status counts follow the selected product, page size survives filter changes, and the loan count on each product card links straight to that product's loans. |
| 2026-10-27 | Made the user register security-aware. Each account shows whether two-factor authentication is on, names open the edit form when the viewer may update the account, and the page description covers access levels and 2FA. |
| 2026-10-27 | Made the borrower file honest about truncated lists. Upcoming installments, payment history and activity now report their full totals when the visible window is shorter, so staff know more records exist and where to find them. |
| 2026-10-27 | Added an outstanding balance column to the loans register and moved the product under the loan number, so the queue shows what is still owed without pushing the row actions off screen. |
| 2026-10-27 | Added a CSV export to the activity log. The download honours the applied search, event, record type, actor and date filters, streams in chunks so long trails stay cheap, and includes the before and after values for every changed field. |
| 2026-10-27 | Connected the calculator to origination. A qualified quote offers **Use these terms**, which opens the New loan form with the product, principal and term prefilled. Terms outside the product limits, or an inactive product, are ignored rather than carried over. |
| 2026-10-27 | Made every dropdown show its current value on first paint. Filters, page sizes, form defaults and the SMS gateway settings used to look empty until the menu was opened once; they now label the selected option immediately. |
| 2026-10-27 | Added portfolio context to the borrower register. Each row reports how many loans are open against how many were filed and the outstanding balance those open loans carry, so collections can spot exposure before opening a file. KYC switches and Clear filters now keep the chosen page size. |
| 2026-10-27 | Added a CSV export to the borrower register. The download honours the applied search and KYC filter, streams in chunks, and includes contact details, national ID, KYC status, linked portal account and join date for reconciliation or mail merges. |
| 2026-10-27 | Kept the chosen page size across the remaining registers. Switching role or product tabs, or clearing filters, no longer drops a user or product list back to 15 rows. |
| 2026-10-27 | Made the registers usable on a phone. The borrower, user and loan lists hide secondary columns below the tablet breakpoint and fold the essentials under the primary cell: borrower contact and KYC context, 2FA state, and the loan's borrower, status and arrears. The activity log folds its actor, event, record and description under the timestamp, so the trail reads as a stack of events instead of a sideways-scrolling table. |
| 2026-10-27 | Brought the report and dashboard tables in line with the registers. The loan report keeps the loan, outstanding and arrears on a phone, with the borrower, product and status folded under the loan number and days past due under the arrears; principal and paid appear once there is room, and the totals row follows the same breakpoints instead of forcing a sideways scroll. The dashboard worklist and recent payments keep the borrower and amount visible and fold the due date, status, method and payment date underneath, and the audit trail's first column is labelled Activity on phones. |
| 2026-10-27 | Stopped the top bar from wrapping on a phone. Below the small breakpoint the breadcrumb trail shows only the current page, so a borrower or loan name stays on one line; the parent links return as soon as there is room. |
| 2026-10-27 | Gave the new-loan form and the activity log their breadcrumbs. Both pages now name themselves in the top bar and link back to loans or the dashboard, instead of leaving an empty strip beside the menu button. |
| 2026-10-28 | Sent the root URL to the right place. `/` no longer serves the starter welcome page; guests are redirected to the login screen, staff to the admin dashboard and borrowers to their own dashboard, so signing in or reopening the app always lands on a working screen. Redirects are covered by feature tests. |
| 2026-10-28 | Rebuilt the borrower dashboard to the same standard as the admin pages. The page now uses the shared page header, section cards, empty states and empty-state actions, reports how much of a long repayment schedule or payment history is visible, and folds the loan reference, product, paid amount and status under the primary cell on a phone so nothing is cut off at 375 px. |
| 2026-10-28 | Branded the sign-in and sign-up screens. The organisation name from Settings now sits under the logo instead of an invisible page title, the log-in copy points at continuing to the dashboard, and the registration screen explains that an account is for following loans and repayments. |
| 2026-10-28 | Aligned the new-loan form with the other forms. The primary action and Cancel now share the summary card footer, so on a phone the two buttons sit together at the end of the form instead of the cancel link being stranded after the notes.
| 2026-10-29 | Made the new-loan borrower field searchable. A plain select stops working once there are hundreds of borrowers, so the field is now a searchable picker that filters as you type, shows each borrower phone number to tell namesakes apart, and stays within the viewport on a phone. |
| 2026-10-29 | Tightened the phone layout of the registers and dashboards. The borrower KYC badge folds under the name below the small breakpoint so names and phone numbers stop wrapping, the loans product filter and Overdue only toggle now share one row, and KPI cards use a smaller value size and tighter padding on a phone so a dashboard takes fewer swipes. |
| 2026-10-29 | Made the role matrix readable on a phone. The policy grid now becomes a stacked list below the tablet breakpoint, each ability listing every role as a chip with an allowed or denied mark, so the role columns no longer scroll off screen. The table is unchanged from 768 px up. |
| 2026-10-30 | Folded the loan and borrower detail tables on a phone. The repayment schedule keeps the installment, due date and outstanding balance, with principal, interest, fee, paid and any penalty on a full-width line beneath each row; payments, payouts and notifications fold their method, reference, actor, channel and sent time under the date or type; and the borrower file folds product, status, principal and arrears under the loan, with the payment history collapsing to date, amount and receipt. All tables are unchanged from the tablet breakpoint up. |
| 2026-11-03 | Started the collections workflow. Staff can log a call, SMS, email or field visit against an active or defaulted loan, record the outcome (promised, contacted, no answer, refused, paid) and keep notes, a follow-up date and — for a promise — the promised amount and date. Promises must carry both figures; any other outcome clears them. The loan file gains a Collections card with the assigned officer, last contact, next follow-up, the open promise and the five most recent contacts, plus a Log contact action in the loan menu. The role matrix lists the new logging and assignment abilities. |
| 2026-11-03 | Opened a collections queue at /admin/collections. It lists only overdue active or defaulted loans, with tabs for every overdue loan, unassigned, open promises and follow-ups due, plus officer, aging (1-30, 31-60, 61-90, 90+) and borrower search filters. Each row shows the borrower and product, arrears and total outstanding, days late, the last contact, the open or missed promise and the next follow-up, flagged when it has lapsed. Collection officers are assigned inline from a staff-only select, and the cards summarise the filtered overdue count, arrears, open promises and unassigned loans. The dashboard arrears and overdue figures and its Needs attention action now open the queue, which also has a sidebar entry. |
| 2026-11-04 | Gave borrowers a loan page of their own. The loan number and View action on the borrower dashboard now open /loans/{loan}, which shows the terms and status, cards for outstanding, paid, arrears and the next installment, the full repayment schedule with principal, interest, fee, penalty, paid and outstanding per installment, and every payment with its method, reference, status and where the money was allocated. The page only resolves for the borrower linked to the loan: another borrower, an account with no borrower profile, staff and guests are all refused, and detail tables scroll sideways on a phone. |
| 2026-11-04 | Gave borrowers a printable statement of their own loan. A Statement button on /loans/{loan} opens /loans/{loan}/statement, which reuses the staff statement document with the organisation, borrower and loan terms, summary cards, the repayment schedule and every payment, plus browser Print / Save as PDF. The statement payload now comes from one LoanStatementService shared by the staff and borrower pages instead of duplicate controller helpers, and it is read-only. Another borrower, an account without a borrower profile, staff and guests are refused. |
| 2026-11-04 | Let borrowers choose their own notifications. A Notifications item appears in settings for accounts linked to a borrower profile, with switches for payment reminders, overdue notices, payment receipts, statements and text message alerts. The page shows the email and phone the office has on file and says plainly when one is missing, explains that statements stay on email, saves with a toast, and refuses staff, unlinked accounts and guests. Staff see no item because they have no borrower profile, exposed through the shared account flag. |
| 2026-11-04 | Added product performance to reports. A Products tab rolls the loan cohort up by loan product with loans, open loans, principal disbursed, collected, outstanding, arrears and a collection rate, plus headline cards, an all-products total row and a Product performance CSV export. The same rows and period filter feed the page and the export, and secondary columns fold away on a phone. |
| 2026-11-04 | Added officer performance to reports. An Officers tab groups the collection contact log by the officer who worked it, with contacts, loans touched, promises, promised amount, paid outcomes and no-answer attempts, plus headline cards, an all-officers total row and an Officer performance CSV export. The date filter narrows this tab by contact date rather than loan application date, and secondary columns fold away on a phone. |
| 2026-11-04 | Made failed queue jobs recoverable. The system status page lists the 20 most recent failed jobs with the job name, queue, when it failed and the first line of the exception, and offers Retry and Forget on each row. Retry requeues the job through the queue:retry command; Forget removes it behind a confirmation dialog. Both actions are super admin only and return a toast, so a failed reminder or notification no longer needs a terminal. |
| 2026-11-04 | Made a silent scheduler visible. Each scheduled command now records a heartbeat in the cache when it completes successfully, and the system status page shows the last successful run, flags a command as overdue when no heartbeat arrives within 26 hours, and says so plainly when nothing has been recorded yet. That covers penalty accrual, overdue flagging, due reminders and overdue notices. |
| 2026-11-04 | Added payments to global search. Ctrl+K now matches a payment by reference or by its number alongside borrowers and loans, with a Payments group that shows the reference or payment number, borrower, method, date and amount, marks a reversed payment, and opens the loan directly on its payments tab. The dialog copy names payments throughout, results stay capped at five per group, and a blank query returns an empty payments list. |
| 2026-11-04 | Added a collections trend to reports. The Collections tab now shows payments received per month over the last six months, or within the chosen period when one is set, as a bar chart and a month-by-month table with counts and an all-months total. A fourth card shows the total received and how many payments it covers. Reversed payments are excluded, so the trend reflects money actually kept. |
| 2026-11-04 | Gave borrowers printable payment receipts. A Receipt button on each payment in /loans/{loan} opens /loans/{loan}/payments/{payment}/receipt, which reuses the staff receipt document with the organisation, borrower, loan, allocations, reversal notice and Print / Save as PDF, and no email action. Receipt payloads now come from one PaymentReceiptService shared by staff and borrowers, and the receipt document moved out of the admin folder. Another borrower, staff, unlinked accounts and a payment that does not belong to the loan are refused. |
| 2026-11-04 | Logged collection contacts from the queue. Each overdue row now has a Log contact button that opens the same dialog as the loan file, so an officer can record the channel, outcome, promise amount and date, follow-up and notes without leaving /admin/collections. The queue shares the channel and outcome options the dialog needs, and the button only appears for staff allowed to log a contact against that loan. |
| 2026-11-04 | Added a CSV export to the loan register. The loans page exports the current view, honouring the status, product, overdue and search filters plus the requested sort, with one row per loan carrying the borrower, phone, product, status, principal, outstanding, arrears, days past due and applied/disbursed dates. The export streams in chunks and shares the queue filter and sort helpers with the page, so the file and the screen cannot drift. |
| 2026-11-04 | Added a CSV export to the collections queue. The queue exports exactly what the current tab and filters show, with the borrower, phone, product, status, arrears, outstanding, days late, officer, last contact and outcome, promise amount and date, and next follow-up, streamed in chunks. The page and the export share one set of queue filter, tab and sort helpers, so the downloaded work list matches the screen. |
| 2026-11-04 | Filled out the global search palette. Opening Ctrl+K on an empty query now offers every administration page the signed-in user can reach, not just borrowers, loans and reports: Overview, Users, Borrowers, Loan products, Loans, Collections and Reports, plus the Activity log, System status and Settings for super admins. Each entry jumps straight to the page, so the palette works as a launcher as well as a record finder. |
| 2026-11-04 | Made the borrower dashboard a set of shortcuts. The Next due card now opens the loan itself and turns red once the installment is overdue, and every loan number on the upcoming repayments and recent payments tables links through to the loan, picking the staff loan file for administrators and the read-only borrower page for everyone else. The header also links straight to notification preferences. |
| 2026-11-04 | Added bulk officer assignment to the collections queue. Every row has a selection checkbox, and the selection bar assigns a whole batch of overdue loans to one officer or clears their assignment in a single request, skipping any loan that is no longer open and reporting how many were updated and skipped. The queue keeps working one loan at a time through the inline officer select, but a whole book can now be redistributed in one pass. |
| 2026-11-04 | Put the two irreversible loan decisions behind a confirmation. Mark defaulted and Write off used to fire straight from the overflow menu on a single click; both now open an AlertDialog that restates the loan, the borrower and what changes, and only submit on confirm. The delete confirmation and these two now share one dialog component, so future lifecycle actions start safe. |
| 2026-11-04 | Gave the loan file a Collections tab. The overview still summarises the officer, last contact, next follow-up and open promise with the five most recent contacts, but when there are more it offers View all, and the new tab lists the complete contact history newest first with every promise, follow-up, note and author. The tab is deep-linkable through ?tab=collections, and the contact list is now one component rather than two copies. |
| 2026-11-04 | Added a payments register at `/admin/payments`. Finance previously had to open loans one at a time to reconcile, so the register lists every repayment across the book with All / Completed / Reversed tabs, search by borrower, phone, reference or loan number, method and payment-date filters, sortable date and amount columns, a summary of what was received versus reversed, and a CSV export that honours the same filters. Each row links straight to the loan file with its payments tab open. |
| 2026-11-04 | Finished the edges of the payments register. Every row now links to that payment's printable receipt in a new tab, so finance can print or re-email a receipt without opening the loan, and the register has its own viewAny policy on the Payment model that the role matrix now lists as View payments, keeping the permissions page honest about the new screen. |
| 2026-11-04 | Added a payments-by-method breakdown to the collections report. The Collections tab now groups completed and reversed payments by cash, bank transfer, cheque, mobile money and other, showing payment counts, completed amounts, each method's share of what was received and the reversed total, with the report period narrowing payments by the date they were received. The same breakdown exports as a Payment methods CSV from the report export menu. |
| 2026-11-04 | Added a collection officer filter to the loans register. Admins can now narrow the whole book to a collector's portfolio alongside the status, product, overdue and search filters, and the filter travels through the status tabs, pagination and CSV export like the others. The officer list is built from staff accounts, so it stays in step with who can actually be assigned in the collections queue. |
| 2026-11-04 | Added arrears to the borrower register. Each row now shows how much of an open borrower's balance is already overdue, an In arrears toggle narrows the register to borrowers with at least one active or defaulted loan past its due date, and the borrower CSV export gains an Arrears column that follows the same filter. The arrears figure is summed from overdue installments, so it matches what the loan file and reports show. |
| 2026-11-04 | Added a CSV export to the user register. Admins can download the accounts currently on screen, with name, email, role, email verification, two-factor status and join date, and the export follows the role tab and search like the other registers. It is gated by the same viewAny policy as the page. |
| 2026-11-04 | Added a notification register at `/admin/notifications`. It lists every email and SMS delivery newest first with status tabs, channel, type, date-range and search filters, summary cards for sent, failed, skipped and queued, a CSV export that follows the filters, a resend action that only appears when the borrower still accepts that channel, and a deep link to the loan's notifications tab. The register is gated by a new NotificationLog policy and appears in the sidebar, global search and role matrix. |
| 2026-11-04 | The payments register can now be narrowed to one borrower. The filter travels in the URL like the other register filters, appears as a removable borrower chip, drives the summary cards and the CSV export, and ignores ids that no longer exist. The borrower file gains an All payments action on its payment history that opens the register pre-filtered, so staff no longer have to open each loan to see the full history. |
| 2026-11-04 | Added Retry all to the failed jobs panel on system status. After fixing a queue or gateway problem, a super admin can requeue the whole failed backlog in one confirmed action instead of retrying rows one by one; the toast reports how many jobs were queued and an empty backlog warns instead of erroring. Access follows the same super-admin gate as the other failed-job actions. |
| 2026-11-04 | Added a My queue view to collections. The queue gains a My queue toggle that narrows the overdue book to the signed-in officer and keeps the tab, aging, search, sort and page size intact, with its own empty state. The administration dashboard reports how many overdue loans are assigned to the current officer and offers a My queue button with that count, linking straight into the filtered queue. |
| 2026-11-04 | Failed jobs can now be opened. Each row gains a Trace action that slides out the full exception (up to 4,000 characters) with the queue and failure time, plus a Copy trace button for pasting into a ticket. The panel also states when it is showing only the most recent 20 of a larger backlog and that Retry all covers every job. |
| 2026-11-04 | Borrower names in the collections queue, payments and notification registers now link to the borrower file, matching the loans register. Staff can jump from a payment, a call log or a bounced message straight to the borrower profile instead of copying the name into search. |
| 2026-11-04 | The notification register can now be narrowed to one borrower. The filter lives in the URL like the other register filters, appears as a removable borrower chip, drives the summary cards and follows into the CSV export, and unknown ids fall back to the full register. The borrower file gains an All deliveries action on its Notifications card that opens the register pre-filtered, so support can check what a borrower actually received without searching by name. |
| 2026-11-06 | The recent payments panel on the administration dashboard now links through to the payments register, so the five-row summary is the entry point to the full reconciliation view instead of a dead end. |
| 2026-11-06 | The collections queue can be narrowed to one borrower. The filter travels in the URL like the other queue filters, shows as a removable borrower chip, survives the tab, officer, aging, sort and page-size changes, and drives the summary cards and CSV export; unknown ids are ignored. The Arrears card on the borrower file links straight to that borrower's overdue loans. |
| 2026-11-06 | The user register gains an Unverified toggle that narrows the list and role tab counts to accounts that have not confirmed their email. The CSV export follows the toggle, so an admin chasing onboarding stragglers can pull just those accounts. The empty state explains the toggle so an empty result is not mistaken for a broken register. |
| 2026-11-06 | The loans register accepts a borrower filter. The filter travels in the URL, shows as a removable borrower chip, survives the status tabs, product, officer, overdue, sort, search and page-size changes, and drives the counts and CSV export; unknown ids are ignored. The Loans section on the borrower file links straight to the register already narrowed to that borrower. |
| 2026-11-06 | The audit trail can be narrowed to one borrower, matching the entries on that borrower plus the loans filed against them. The filter travels in the URL, shows as a removable chip, is preserved when other filters are applied, and drives the CSV export. The Activity card on the borrower file links straight to the full trail instead of stopping at the ten most recent entries. |
| 2026-11-07 | The audit trail can also be pinned to a single loan, matching its own entries plus payments and disbursements recorded against it. The loan file's status timeline gains an Audit trail action that opens the register pre-filtered, the loan shows as a removable chip alongside the borrower chip, and stale borrower or loan ids are ignored instead of failing validation. |
| 2026-11-07 | The notification register can now be pinned to one loan. The filter lives in the URL, shows as a removable chip next to the borrower chip, drives the summary cards and CSV export, and ignores stale ids. The Notifications tab on the loan file gains an All deliveries action that opens the register pre-filtered, so support can inspect one loan's whole delivery history instead of only the rows on the tab. |
| 2026-11-07 | System status now reports mail delivery health alongside SMS: sent, failed, skipped and queued counts for the last seven days plus the most recent failure, using the same notification log as the register. Both panels gain an action that opens the notification register already filtered to that channel, so an operator can go from a red number to the exact deliveries behind it. |
| 2026-11-07 | The payments register can now be pinned to one loan. The loan shows as a removable chip next to the borrower chip, and the filter drives the summary cards, status counts and CSV export while ignoring stale ids. The Repayments tab on the loan file gains an All payments action that opens the register pre-filtered, so finance can reconcile one loan and still take the filtered export. |
| 2026-11-07 | A new disbursements register at `/admin/disbursements` lists every payout across the book, mirroring the payments register: method and date filters, search by borrower, reference or loan number, sortable date and amount columns, summary cards for count, total and average payout, and a CSV export that honours the filters. Borrowers and loans can be pinned as removable chips, stale ids are ignored, and the loan file's Disbursements tab gains an All disbursements action into the filtered register. |
| 2026-11-08 | The administration overview gains a Recent disbursements panel mirroring Recent payments: the latest payouts with borrower, method, date and amount, each row opening the loan file on its Disbursements tab, and an All disbursements action into the register. The borrower file's Loans card also gains an All payouts action that opens the register pre-filtered to that borrower. |

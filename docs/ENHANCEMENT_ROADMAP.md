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

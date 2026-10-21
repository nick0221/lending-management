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

**Installed primitives:** alert, avatar, badge, breadcrumb, button, card,
checkbox, collapsible, dialog, dropdown-menu, input, input-otp, label,
navigation-menu, select, separator, sheet, sidebar, skeleton, sonner, spinner,
tabs, toggle, toggle-group, tooltip.

**Missing / not yet added:** table, alert-dialog, pagination, popover, calendar /
date-range picker, switch, command palette, progress, scroll-area, chart.

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
| E0.11 | `ui/switch`       | Boolean preferences instead of raw checkboxes.                        | S      | 🔒     |
| E0.12 | Date range picker | `popover` + `calendar` for reports and audit filters.                 | M      | 🔒     |

**Dependency gates**

| Package                         | Needed for           | State                  |
| ------------------------------- | -------------------- | ---------------------- |
| `@radix-ui/react-tabs`          | E0.1                 | ✅ approved, installed |
| `@radix-ui/react-alert-dialog`  | E0.3 / E0.8          | ✅ approved, installed |
| `@radix-ui/react-switch`        | E0.11                | 🔒 awaiting approval   |
| `react-day-picker` + `date-fns` | E0.12                | 🔒 awaiting approval   |
| `cmdk`                          | global search (F2)   | 🔒 awaiting approval   |
| `recharts`                      | report charts (E7.4) | 🔒 awaiting approval   |

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
| E5.5 | Reversal           | Reverse a payment with reason, audit event and schedule recalculation. Functional change.   | ⏳     |

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
| E7.2 | Date range  | Shared date-range control (E0.12) applied to every report.                   | 🔒     |
| E7.3 | Export menu | One `DropdownMenu` with a CSV action per report instead of separate buttons. | ✅     |
| E7.4 | Charts      | Simple bars first (CSS); upgrade to `recharts` only if approved.             | 🔒     |
| E7.5 | Print view  | Print stylesheet for the portfolio summary.                                  | ✅     |

---

## E8 — Notifications

| ID   | Item           | Detail                                                                       | Status |
| ---- | -------------- | ---------------------------------------------------------------------------- | ------ |
| E8.1 | Delivery log   | Notifications tab on the loan / borrower page backed by `notification_logs`. | ✅     |
| E8.2 | Resend         | Resend a failed or skipped notification from the log.                        | ⏳     |
| E8.3 | Switch toggles | Replace the four preference checkboxes with `ui/switch` rows.                | 🔒     |
| E8.4 | Email preview  | Preview the rendered markdown email before sending a statement.              | ✅     |

---

## E9 — Hardening & operations

| ID   | Item          | Detail                                                                                             | Status |
| ---- | ------------- | -------------------------------------------------------------------------------------------------- | ------ |
| E9.1 | Audit table   | `ui/table` with event badges, actor and relative time.                                             | ✅     |
| E9.2 | Diff sheet    | Open an audit row in a `Sheet` showing before/after values.                                        | ✅     |
| E9.3 | Settings page | Organisation name, currency symbol, default penalty/grace and rate limits — currently `.env` only. | ⏳     |
| E9.4 | System status | Cards for queue, scheduler, mail and storage health.                                               | ✅     |
| E9.5 | Role matrix   | Read-only table of what each role can do, sourced from the policies.                               | ⏳     |

---

## Functional backlog (non-UI)

| ID  | Item                                 | Why                                                       | Effort |
| --- | ------------------------------------ | --------------------------------------------------------- | ------ |
| F1  | Borrower detail + statement download | Support conversations start with "show me this borrower". | M      |
| F2  | Global search                        | Find a borrower or loan from anywhere (`cmdk` gate).      | M      |
| F3  | Loan calculator                      | Quote a schedule before creating a loan.                  | S      |
| F4  | Payment reversal                     | Correct mistakes without editing the database.            | M      |
| F5  | Borrower CSV import                  | Onboard existing books.                                   | M      |
| F6  | Bulk loan actions                    | Approve / remind in batches.                              | M      |
| F7  | PDF statements & exports             | Printable documents (dependency gate).                    | M      |
| F8  | SMS channel                          | Second notification channel (provider decision).          | L      |
| F9  | Multi-currency                       | Only if the business needs it.                            | L      |

---

## Milestones

| Milestone              | Scope                              | Status  |
| ---------------------- | ---------------------------------- | ------- |
| M1 — Foundations       | E0.1, E0.9, E2.1                   | ✅      |
| M2 — List consistency  | E0.2–E0.8, E1.1–E1.5, E2.2–E2.6    | ✅      |
| M3 — Detail pages      | E1.6, E2.7–E2.10, E7.1–E7.3        | 🔨 in progress |
| M4 — Find anything     | E1.2, E2.3, F1, F2                 | ⏳      |
| M5 — Operational depth | E5.5, E6.3, E8.1, E9.1–E9.4, F3–F5 | ⏳      |

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

import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    MoreHorizontal,
    ReceiptText,
    Send,
    Undo2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { LoanActions } from '@/components/admin/loan-actions';
import { PaymentReceiptSheet } from '@/components/admin/payment-receipt-sheet';
import { ReversePaymentDialog } from '@/components/admin/reverse-payment-dialog';
import { LoanStatusBadge } from '@/components/loan-status-badge';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    formatDate,
    formatDateOnly,
    formatDateTime,
    formatMoney,
} from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard as adminDashboard } from '@/routes/admin';
import { show as showBorrower } from '@/routes/admin/borrowers';
import { index as loansIndex, show as showLoan } from '@/routes/admin/loans';
import { resend as resendLoanNotification } from '@/routes/admin/loans/notifications';
import type {
    Disbursement,
    DisbursementMethodOption,
    Loan,
    LoanAbilities,
    LoanEvent,
    LoanInstallment,
    NotificationLogEntry,
    NotificationStatus,
    Payment,
    PaymentFilters,
    PaymentMethodOption,
    PaymentTotals,
} from '@/types';

const tabs = [
    'overview',
    'schedule',
    'payments',
    'disbursements',
    'notifications',
    'activity',
] as const;

type LoanTab = (typeof tabs)[number];

function notificationStatusVariant(status: NotificationStatus) {
    return status === 'sent'
        ? 'default'
        : status === 'failed'
          ? 'destructive'
          : status === 'skipped'
            ? 'outline'
            : 'secondary';
}

function DetailField({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="space-y-1">
            <dt className="text-xs text-muted-foreground uppercase">{label}</dt>
            <dd className="text-sm font-medium break-words">{value}</dd>
        </div>
    );
}

function SummaryMetric({
    label,
    value,
    danger = false,
}: {
    label: string;
    value: string;
    danger?: boolean;
}) {
    return (
        <div className="flex items-baseline gap-2">
            <span className="text-xs text-muted-foreground uppercase">
                {label}
            </span>
            <span
                className={cn(
                    'text-sm font-medium tabular-nums',
                    danger && 'text-destructive',
                )}
            >
                {value}
            </span>
        </div>
    );
}

function BalanceMetric({
    label,
    amount,
    danger = false,
}: {
    label: string;
    amount: string;
    danger?: boolean;
}) {
    return (
        <div className="space-y-1">
            <p className="text-xs text-muted-foreground uppercase">{label}</p>
            <p
                className={cn(
                    'text-lg font-semibold tabular-nums',
                    danger && 'text-destructive',
                )}
            >
                {formatMoney(amount)}
            </p>
        </div>
    );
}

function TabCount({ value }: { value: number }) {
    return (
        <span className="rounded-full bg-muted-foreground/10 px-1.5 text-xs tabular-nums">
            {value}
        </span>
    );
}

function LoanTimeline({ events }: { events: LoanEvent[] }) {
    if (events.length === 0) {
        return (
            <p className="p-6 text-sm text-muted-foreground">
                No activity recorded yet.
            </p>
        );
    }

    return (
        <ol className="p-6">
            {events.map((event) => (
                <li
                    key={event.id}
                    className="relative pb-6 pl-6 before:absolute before:top-3 before:bottom-0 before:left-[4px] before:w-px before:bg-muted-foreground/25 last:pb-0 last:before:hidden"
                >
                    <span className="absolute top-1.5 left-0 size-2 rounded-full bg-primary" />

                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-medium">
                                {event.action_label}
                            </p>

                            {event.from_status_label &&
                                event.to_status_label && (
                                    <span className="text-xs text-muted-foreground">
                                        {event.from_status_label} →{' '}
                                        {event.to_status_label}
                                    </span>
                                )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                            {event.user ?? 'System'} ·{' '}
                            {formatDateTime(event.created_at)}
                        </p>
                    </div>
                </li>
            ))}
        </ol>
    );
}

export default function ShowLoan({
    name,
    loan,
    installments,
    disbursements,
    payments,
    paymentCount,
    paymentFilters,
    totals,
    events,
    notifications,
    disbursementMethods,
    paymentMethods,
    currentTab,
    can,
}: {
    name: string;
    loan: Loan;
    installments: LoanInstallment[];
    disbursements: Disbursement[];
    payments: Payment[];
    paymentCount: number;
    paymentFilters: PaymentFilters;
    totals: PaymentTotals;
    events: LoanEvent[];
    notifications: NotificationLogEntry[];
    disbursementMethods: DisbursementMethodOption[];
    paymentMethods: PaymentMethodOption[];
    currentTab: LoanTab;
    can: LoanAbilities;
}) {
    const [tab, setTab] = useState<LoanTab>(currentTab);
    const [receiptPayment, setReceiptPayment] = useState<Payment | null>(null);
    const [reversePayment, setReversePayment] = useState<Payment | null>(null);
    const [resendingNotificationId, setResendingNotificationId] = useState<
        number | null
    >(null);
    const tabsListRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        tabsListRef.current
            ?.querySelector<HTMLElement>('[data-state="active"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'center' });
    }, [tab]);

    const changeTab = (value: string) => {
        const next = tabs.includes(value as LoanTab)
            ? (value as LoanTab)
            : 'overview';

        setTab(next);

        const url = new URL(window.location.href);

        if (next === 'overview') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', next);
        }

        window.history.replaceState(window.history.state, '', url.toString());
    };

    const resendNotification = (notification: NotificationLogEntry) => {
        router.post(
            resendLoanNotification({
                loan: loan.id,
                notificationLog: notification.id,
            }).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setResendingNotificationId(notification.id),
                onFinish: () => setResendingNotificationId(null),
            },
        );
    };

    const hasPaymentFilters = Boolean(
        paymentFilters.method || paymentFilters.from || paymentFilters.to,
    );

    const applyPaymentFilters = (overrides: Partial<PaymentFilters>): void => {
        const next = { ...paymentFilters, ...overrides };
        const query: Record<string, string> = { tab: 'payments' };

        if (next.method) {
            query.payment_method = next.method;
        }

        if (next.from) {
            query.payment_from = next.from;
        }

        if (next.to) {
            query.payment_to = next.to;
        }

        router.get(showLoan(loan.id).url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const scheduleTotals = installments.reduce(
        (totals, installment) => ({
            principal: totals.principal + Number(installment.principal_due),
            interest: totals.interest + Number(installment.interest_due),
            fee: totals.fee + Number(installment.fee_due),
            penalty: totals.penalty + Number(installment.penalty_accrued),
            paid: totals.paid + Number(installment.paid),
            outstanding: totals.outstanding + Number(installment.outstanding),
        }),
        {
            principal: 0,
            interest: 0,
            fee: 0,
            penalty: 0,
            paid: 0,
            outstanding: 0,
        },
    );

    const nextInstallment = installments.find(
        (installment) => Number(installment.outstanding) > 0,
    );

    return (
        <>
            <Head title={`Loan #${loan.id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="space-y-1">
                    <Button variant="ghost" size="sm" asChild className="-ml-2">
                        <Link href={loansIndex()}>
                            <ArrowLeft /> Back to loans
                        </Link>
                    </Button>

                    <PageHeader
                        title={`Loan #${loan.id}`}
                        description={`${loan.borrower_name} · ${loan.loan_product_name}`}
                        actions={
                            <LoanActions
                                loan={loan}
                                outstanding={totals.outstanding}
                                breakdown={totals.allocation}
                                installments={installments}
                                can={can}
                                disbursementMethods={disbursementMethods}
                                paymentMethods={paymentMethods}
                            />
                        }
                    />
                </div>

                <Tabs value={tab} onValueChange={changeTab} className="gap-6">
                    <div className="z-20 -mx-4 space-y-3 border-b bg-background/95 px-4 py-3 backdrop-blur md:sticky md:top-0">
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                            <LoanStatusBadge status={loan.status} />

                            {loan.days_past_due > 0 && (
                                <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                    {loan.days_past_due} days past due
                                </span>
                            )}

                            <SummaryMetric
                                label="Principal"
                                value={formatMoney(loan.principal)}
                            />
                            <SummaryMetric
                                label="Outstanding"
                                value={formatMoney(totals.outstanding)}
                            />
                            <SummaryMetric
                                label="Arrears"
                                value={formatMoney(totals.arrears)}
                                danger={Number(totals.arrears) > 0}
                            />
                            <SummaryMetric
                                label="Next due"
                                value={
                                    nextInstallment
                                        ? `${formatMoney(nextInstallment.outstanding)} · ${formatDateOnly(nextInstallment.due_date)}`
                                        : '—'
                                }
                            />
                        </div>

                        <TabsList
                            ref={tabsListRef}
                            className="h-auto max-w-full justify-start overflow-x-auto"
                        >
                            <TabsTrigger value="overview">Overview</TabsTrigger>
                            <TabsTrigger value="schedule">
                                Schedule
                                <TabCount value={installments.length} />
                            </TabsTrigger>
                            <TabsTrigger value="payments">
                                Payments
                                <TabCount value={paymentCount} />
                            </TabsTrigger>
                            <TabsTrigger value="disbursements">
                                Payouts
                                <TabCount value={disbursements.length} />
                            </TabsTrigger>
                            <TabsTrigger value="notifications">
                                Notifications
                                <TabCount value={notifications.length} />
                            </TabsTrigger>
                            <TabsTrigger value="activity">
                                Activity
                                <TabCount value={events.length} />
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="overview" className="space-y-6">
                        <SectionCard
                            title="Loan details"
                            description="Terms captured when the loan was originated."
                            contentClassName="p-6"
                        >
                            <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                                <DetailField
                                    label="Borrower"
                                    value={
                                        <Link
                                            href={showBorrower(
                                                loan.borrower_id,
                                            )}
                                            className="hover:underline"
                                            prefetch
                                        >
                                            {loan.borrower_name}
                                        </Link>
                                    }
                                />
                                <DetailField
                                    label="Loan product"
                                    value={loan.loan_product_name}
                                />
                                <DetailField
                                    label="Principal"
                                    value={formatMoney(loan.principal)}
                                />
                                <DetailField
                                    label="Interest"
                                    value={`${Number(loan.interest_rate).toFixed(2)}% ${loan.interest_method_label}`}
                                />
                                <DetailField
                                    label="Repayment"
                                    value={loan.repayment_frequency_label}
                                />
                                <DetailField
                                    label="Term"
                                    value={`${loan.term_months} months`}
                                />
                                <DetailField
                                    label="Processing fee"
                                    value={`${Number(loan.processing_fee_rate).toFixed(2)}%`}
                                />
                                <DetailField
                                    label="Late penalty"
                                    value={`${Number(loan.penalty_rate).toFixed(2)}% / month${
                                        loan.grace_period_days > 0
                                            ? ` · ${loan.grace_period_days} day grace`
                                            : ''
                                    }`}
                                />
                                <DetailField
                                    label="Applied"
                                    value={formatDate(loan.applied_at)}
                                />
                                <DetailField
                                    label="Approved"
                                    value={formatDate(loan.approved_at)}
                                />
                                <DetailField
                                    label="Disbursed"
                                    value={formatDate(loan.disbursed_at)}
                                />
                                <DetailField
                                    label="Closed"
                                    value={formatDate(loan.closed_at)}
                                />
                                <div className="space-y-1 sm:col-span-2">
                                    <dt className="text-xs text-muted-foreground uppercase">
                                        Notes
                                    </dt>
                                    <dd className="text-sm whitespace-pre-line">
                                        {loan.notes || '—'}
                                    </dd>
                                </div>
                            </dl>
                        </SectionCard>

                        <SectionCard
                            title="Balance"
                            description="What is still owed, including accrued penalties."
                            contentClassName="p-6"
                        >
                            {installments.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    The schedule is generated when the loan is
                                    approved.
                                </p>
                            ) : (
                                <div className="space-y-6">
                                    <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                        <BalanceMetric
                                            label="Payable"
                                            amount={totals.payable}
                                        />
                                        <BalanceMetric
                                            label="Paid"
                                            amount={totals.paid}
                                        />
                                        <BalanceMetric
                                            label="Outstanding"
                                            amount={totals.outstanding}
                                        />
                                        <BalanceMetric
                                            label="Penalties"
                                            amount={totals.penalty}
                                            danger={Number(totals.penalty) > 0}
                                        />
                                        <BalanceMetric
                                            label="Arrears"
                                            amount={totals.arrears}
                                            danger={Number(totals.arrears) > 0}
                                        />
                                    </div>

                                    <div className="rounded-lg border bg-muted/30 px-4 py-3 text-sm">
                                        <p className="font-medium">
                                            Penalty policy
                                        </p>

                                        <p className="text-muted-foreground">
                                            {Number(loan.penalty_rate).toFixed(
                                                2,
                                            )}
                                            % per month
                                            {loan.grace_period_days > 0
                                                ? ` after a ${loan.grace_period_days}-day grace period`
                                                : ''}{' '}
                                            on overdue installments.
                                            {Number(totals.penalty) > 0 && (
                                                <>
                                                    {' '}
                                                    Accrued so far:{' '}
                                                    <span className="font-medium text-destructive">
                                                        {formatMoney(
                                                            totals.penalty,
                                                        )}
                                                    </span>
                                                    .
                                                </>
                                            )}
                                        </p>
                                    </div>

                                    <div className="border-t pt-4">
                                        <p className="mb-3 text-xs text-muted-foreground uppercase">
                                            Aging of outstanding balance
                                        </p>

                                        <div className="grid gap-4 sm:grid-cols-5">
                                            {(
                                                [
                                                    [
                                                        'Current',
                                                        totals.aging.current,
                                                    ],
                                                    [
                                                        '1–30 days',
                                                        totals.aging.days_1_30,
                                                    ],
                                                    [
                                                        '31–60 days',
                                                        totals.aging.days_31_60,
                                                    ],
                                                    [
                                                        '61–90 days',
                                                        totals.aging.days_61_90,
                                                    ],
                                                    [
                                                        '90+ days',
                                                        totals.aging
                                                            .days_over_90,
                                                    ],
                                                ] as const
                                            ).map(([label, amount]) => (
                                                <div
                                                    key={label}
                                                    className="space-y-1"
                                                >
                                                    <p className="text-xs text-muted-foreground">
                                                        {label}
                                                    </p>
                                                    <p className="text-sm font-semibold tabular-nums">
                                                        {formatMoney(amount)}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </SectionCard>
                    </TabsContent>

                    <TabsContent value="schedule">
                        <SectionCard
                            title="Repayment schedule"
                            description="Oldest first. Payments are allocated to the oldest installment."
                        >
                            {installments.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    The schedule is generated when the loan is
                                    approved.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Due date</TableHead>
                                            <TableHead className="text-right">
                                                Principal
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Interest
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Fee
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Penalty
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Paid
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {installments.map((installment) => (
                                            <TableRow
                                                key={installment.sequence}
                                            >
                                                <TableCell className="text-muted-foreground tabular-nums">
                                                    {installment.sequence}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {formatDateOnly(
                                                            installment.due_date,
                                                        )}

                                                        {installment.overdue && (
                                                            <span className="rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-medium text-destructive">
                                                                {
                                                                    installment.days_past_due
                                                                }{' '}
                                                                d late
                                                            </span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.principal_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.interest_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.fee_due,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.penalty_accrued,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        installment.paid,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right text-muted-foreground tabular-nums">
                                                    {formatMoney(
                                                        installment.outstanding,
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>

                                    <TableFooter>
                                        <TableRow>
                                            <TableCell colSpan={2}>
                                                Total payable
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.principal,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.interest,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.fee,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.penalty,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.paid,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    scheduleTotals.outstanding,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    </TableFooter>
                                </Table>
                            )}
                        </SectionCard>
                    </TabsContent>

                    <TabsContent value="payments">
                        <SectionCard
                            title="Repayments"
                            description={
                                hasPaymentFilters
                                    ? `${payments.length} of ${paymentCount} payments match the filters.`
                                    : `${paymentCount} payment${paymentCount === 1 ? '' : 's'} recorded.`
                            }
                        >
                            {paymentCount > 0 && (
                                <div className="flex flex-wrap items-end gap-3 border-b p-4">
                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="payment-method"
                                            className="text-xs"
                                        >
                                            Method
                                        </Label>

                                        <Select
                                            value={
                                                paymentFilters.method ?? 'all'
                                            }
                                            onValueChange={(value) =>
                                                applyPaymentFilters({
                                                    method:
                                                        value === 'all'
                                                            ? null
                                                            : (value as PaymentFilters['method']),
                                                })
                                            }
                                        >
                                            <SelectTrigger
                                                id="payment-method"
                                                className="w-44"
                                            >
                                                <SelectValue placeholder="All methods" />
                                            </SelectTrigger>

                                            <SelectContent>
                                                <SelectItem value="all">
                                                    All methods
                                                </SelectItem>

                                                {paymentMethods.map(
                                                    (method) => (
                                                        <SelectItem
                                                            key={method.value}
                                                            value={method.value}
                                                        >
                                                            {method.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="payment-from"
                                            className="text-xs"
                                        >
                                            From
                                        </Label>

                                        <Input
                                            id="payment-from"
                                            type="date"
                                            className="w-40"
                                            value={paymentFilters.from ?? ''}
                                            max={paymentFilters.to ?? undefined}
                                            onChange={(event) =>
                                                applyPaymentFilters({
                                                    from:
                                                        event.target.value ||
                                                        null,
                                                })
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-1.5">
                                        <Label
                                            htmlFor="payment-to"
                                            className="text-xs"
                                        >
                                            To
                                        </Label>

                                        <Input
                                            id="payment-to"
                                            type="date"
                                            className="w-40"
                                            value={paymentFilters.to ?? ''}
                                            min={
                                                paymentFilters.from ?? undefined
                                            }
                                            onChange={(event) =>
                                                applyPaymentFilters({
                                                    to:
                                                        event.target.value ||
                                                        null,
                                                })
                                            }
                                        />
                                    </div>

                                    {hasPaymentFilters && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                applyPaymentFilters({
                                                    method: null,
                                                    from: null,
                                                    to: null,
                                                })
                                            }
                                        >
                                            <X /> Clear
                                        </Button>
                                    )}
                                </div>
                            )}

                            {payments.length === 0 ? (
                                <div className="space-y-3 p-6">
                                    <p className="text-sm text-muted-foreground">
                                        {hasPaymentFilters
                                            ? 'No payments match these filters.'
                                            : 'Payments recorded against this loan will appear here.'}
                                    </p>

                                    {hasPaymentFilters && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                applyPaymentFilters({
                                                    method: null,
                                                    from: null,
                                                    to: null,
                                                })
                                            }
                                        >
                                            <X /> Clear filters
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Method</TableHead>
                                            <TableHead>Reference</TableHead>
                                            <TableHead>Recorded by</TableHead>
                                            <TableHead>Allocated to</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow
                                                key={payment.id}
                                                className={
                                                    payment.reversed_at
                                                        ? 'bg-destructive/5'
                                                        : undefined
                                                }
                                            >
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        payment.payment_date,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex flex-col items-end gap-1">
                                                        <span
                                                            className={cn(
                                                                'font-medium tabular-nums',
                                                                payment.reversed_at &&
                                                                    'text-muted-foreground',
                                                            )}
                                                        >
                                                            {formatMoney(
                                                                payment.amount,
                                                            )}
                                                        </span>

                                                        {payment.reversed_at && (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300"
                                                            >
                                                                Reversed
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.method_label}
                                                </TableCell>
                                                <TableCell>
                                                    {payment.reference ?? '—'}
                                                    {payment.notes && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {payment.notes}
                                                        </div>
                                                    )}
                                                    {payment.reversal_reason && (
                                                        <div className="text-xs text-red-700 dark:text-red-300">
                                                            Reversed:{' '}
                                                            {
                                                                payment.reversal_reason
                                                            }
                                                        </div>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.recorded_by ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.reversed_at
                                                        ? 'Released'
                                                        : payment.allocations
                                                                .length === 0
                                                          ? 'Unallocated'
                                                          : payment.allocations
                                                                .map(
                                                                    (
                                                                        allocation,
                                                                    ) =>
                                                                        `#${
                                                                            allocation.installment_sequence ??
                                                                            '—'
                                                                        }`,
                                                                )
                                                                .join(', ')}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger
                                                            asChild
                                                        >
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                aria-label={`Payment ${payment.id} actions`}
                                                            >
                                                                <MoreHorizontal />
                                                            </Button>
                                                        </DropdownMenuTrigger>

                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem
                                                                onSelect={() =>
                                                                    setReceiptPayment(
                                                                        payment,
                                                                    )
                                                                }
                                                            >
                                                                <ReceiptText />{' '}
                                                                View receipt
                                                            </DropdownMenuItem>

                                                            {payment.can_reverse && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        variant="destructive"
                                                                        onSelect={() =>
                                                                            setReversePayment(
                                                                                payment,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Undo2 />{' '}
                                                                        Reverse
                                                                        payment
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>
                    </TabsContent>

                    <TabsContent value="disbursements">
                        <SectionCard
                            title="Disbursements"
                            description={`${disbursements.length} payout${disbursements.length === 1 ? '' : 's'} recorded.`}
                        >
                            {disbursements.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    Payouts recorded against this loan will
                                    appear here.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead>Method</TableHead>
                                            <TableHead>Reference</TableHead>
                                            <TableHead>Recorded by</TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {disbursements.map((disbursement) => (
                                            <TableRow key={disbursement.id}>
                                                <TableCell className="text-muted-foreground">
                                                    {formatDate(
                                                        disbursement.disbursed_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium tabular-nums">
                                                    {formatMoney(
                                                        disbursement.amount,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {disbursement.method_label}
                                                </TableCell>
                                                <TableCell>
                                                    {disbursement.reference ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {disbursement.recorded_by ??
                                                        '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>
                    </TabsContent>

                    <TabsContent value="notifications">
                        <SectionCard
                            title="Notifications"
                            description={`${notifications.length} notification${
                                notifications.length === 1 ? '' : 's'
                            } recorded for ${loan.borrower_name}.`}
                        >
                            {notifications.length === 0 ? (
                                <p className="p-6 text-sm text-muted-foreground">
                                    Reminders, receipts and statements sent to
                                    the borrower will appear here, along with
                                    any delivery failures.
                                </p>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Channel</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Sent</TableHead>
                                            <TableHead className="text-right">
                                                Actions
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {notifications.map((notification) => (
                                            <TableRow key={notification.id}>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {
                                                            notification.type_label
                                                        }
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground capitalize">
                                                    {notification.channel}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="space-y-1">
                                                        <Badge
                                                            variant={notificationStatusVariant(
                                                                notification.status,
                                                            )}
                                                        >
                                                            {
                                                                notification.status_label
                                                            }
                                                        </Badge>
                                                        {notification.error && (
                                                            <p className="max-w-xs text-xs text-muted-foreground">
                                                                {
                                                                    notification.error
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {notification.sent_at
                                                        ? formatDateTime(
                                                              notification.sent_at,
                                                          )
                                                        : '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            resendNotification(
                                                                notification,
                                                            )
                                                        }
                                                        disabled={
                                                            !notification.can_resend ||
                                                            resendingNotificationId ===
                                                                notification.id
                                                        }
                                                    >
                                                        <Send />
                                                        {resendingNotificationId ===
                                                        notification.id
                                                            ? 'Sending…'
                                                            : 'Resend'}
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </SectionCard>
                    </TabsContent>

                    <TabsContent value="activity">
                        <SectionCard
                            title="Status timeline"
                            description={`${events.length} event${events.length === 1 ? '' : 's'} from application to today.`}
                        >
                            <LoanTimeline events={events} />
                        </SectionCard>
                    </TabsContent>
                </Tabs>
            </div>

            <PaymentReceiptSheet
                loan={loan}
                payment={receiptPayment}
                organisation={name}
                open={receiptPayment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReceiptPayment(null);
                    }
                }}
            />

            <ReversePaymentDialog
                loan={loan}
                payment={reversePayment}
                open={reversePayment !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReversePayment(null);
                    }
                }}
            />
        </>
    );
}

ShowLoan.layout = (props: { loan: Loan }) => ({
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Loans',
            href: loansIndex(),
        },
        {
            title: `Loan #${props.loan.id}`,
            href: showLoan(props.loan.id),
        },
    ],
});

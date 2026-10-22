import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateOnly, formatMoney, formatPercent } from '@/lib/format';
import { useCurrencySymbol } from '@/hooks/use-currency-symbol';
import { index as reportsIndex } from '@/routes/admin/reports';
import type {
    AgingBuckets,
    Collections,
    PortfolioRisk,
    PortfolioSummary,
    ReportFilters,
} from '@/types';

const agingRows: { key: keyof AgingBuckets; label: string }[] = [
    { key: 'current', label: 'Current' },
    { key: 'days_1_30', label: '1 – 30 days' },
    { key: 'days_31_60', label: '31 – 60 days' },
    { key: 'days_61_90', label: '61 – 90 days' },
    { key: 'days_over_90', label: 'Over 90 days' },
];

function Metric({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    return (
        <div className="border-b pb-2">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="font-medium tabular-nums">{value}</dd>
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
        </div>
    );
}

export default function PortfolioSummaryPrint({
    organisation,
    generated_at,
    summary,
    aging,
    risk,
    collections,
    filters,
}: {
    organisation: string;
    generated_at: string;
    summary: PortfolioSummary;
    aging: AgingBuckets;
    risk: PortfolioRisk;
    collections: Collections;
    filters: ReportFilters;
}) {
    useCurrencySymbol();

    const periodQuery = {
        ...(filters.from ? { from: filters.from } : {}),
        ...(filters.to ? { to: filters.to } : {}),
    };

    const periodLabel =
        filters.from && filters.to
            ? `${formatDateOnly(filters.from)} – ${formatDateOnly(filters.to)}`
            : filters.from
              ? `from ${formatDateOnly(filters.from)}`
              : filters.to
                ? `until ${formatDateOnly(filters.to)}`
                : null;

    const agingTotal = agingRows.reduce(
        (total, row) => total + aging[row.key],
        0,
    );

    const share = (amount: number) =>
        agingTotal > 0 ? (amount / agingTotal) * 100 : 0;

    const balanceRows = [
        {
            label: 'Principal',
            outstanding: summary.outstanding_principal,
            collected: summary.collected_principal,
        },
        {
            label: 'Interest',
            outstanding: summary.outstanding_interest,
            collected: summary.collected_interest,
        },
        {
            label: 'Fees',
            outstanding: summary.outstanding_fee,
            collected: summary.collected_fee,
        },
        {
            label: 'Penalties',
            outstanding: summary.outstanding_penalty,
            collected: summary.collected_penalty,
        },
    ];

    return (
        <>
            <Head title="Portfolio summary" />

            <div className="min-h-svh bg-muted/40 p-4 print:bg-white print:p-0">
                <div className="mx-auto max-w-4xl space-y-4 print:max-w-none">
                    <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                        <Button variant="ghost" asChild>
                            <Link href={reportsIndex({ query: periodQuery })}>
                                <ArrowLeft /> Back to reports
                            </Link>
                        </Button>

                        <Button
                            variant="outline"
                            onClick={() => window.print()}
                        >
                            <Printer /> Print
                        </Button>
                    </div>

                    <article className="rounded-xl border bg-card p-6 text-sm print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none print:[&_*]:bg-transparent print:[&_*]:text-black">
                        <header className="flex flex-wrap items-start justify-between gap-4">
                            <div className="space-y-1">
                                <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    {organisation}
                                </p>
                                <h1 className="text-xl font-semibold">
                                    Portfolio summary
                                </h1>
                                <p className="text-muted-foreground">
                                    Generated {formatDateOnly(generated_at)}
                                </p>
                                <p className="text-muted-foreground">
                                    {periodLabel
                                        ? `Loans applied ${periodLabel}`
                                        : 'All loans'}
                                </p>
                            </div>

                            <div className="text-right">
                                <p className="text-xs text-muted-foreground">
                                    Outstanding
                                </p>
                                <p className="text-2xl font-semibold tabular-nums">
                                    {formatMoney(summary.outstanding)}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {summary.open_loans} open loan
                                    {summary.open_loans === 1 ? '' : 's'}
                                </p>
                            </div>
                        </header>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Portfolio
                            </h2>

                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                                <Metric
                                    label="Borrowers"
                                    value={String(summary.borrowers)}
                                />
                                <Metric
                                    label="Loans on file"
                                    value={String(summary.loans)}
                                />
                                <Metric
                                    label="Open loans"
                                    value={String(summary.open_loans)}
                                    hint={`${summary.active_loans} active, ${summary.defaulted_loans} defaulted`}
                                />
                                <Metric
                                    label="Closed"
                                    value={String(summary.closed_loans)}
                                    hint={`${summary.written_off_loans} written off`}
                                />
                                <Metric
                                    label="Principal disbursed"
                                    value={formatMoney(summary.disbursed)}
                                />
                                <Metric
                                    label="Collected"
                                    value={formatMoney(summary.collected)}
                                />
                                <Metric
                                    label="Arrears"
                                    value={formatMoney(summary.arrears)}
                                />
                                <Metric
                                    label="Portfolio at risk"
                                    value={formatPercent(risk.par.ratio)}
                                    hint={formatMoney(risk.par.amount)}
                                />
                            </dl>
                        </section>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Outstanding and collected
                            </h2>

                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Component</TableHead>
                                        <TableHead className="text-right">
                                            Outstanding
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Collected
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {balanceRows.map((row) => (
                                        <TableRow key={row.label}>
                                            <TableCell className="font-medium">
                                                {row.label}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(row.outstanding)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(row.collected)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>

                                <TableFooter>
                                    <TableRow>
                                        <TableCell>Total</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(summary.outstanding)}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {formatMoney(summary.collected)}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </section>

                        <div className="mt-6 grid gap-6 sm:grid-cols-2">
                            <section className="space-y-3">
                                <h2 className="text-xs font-semibold tracking-wide uppercase">
                                    Portfolio at risk
                                </h2>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Bucket</TableHead>
                                            <TableHead className="text-right">
                                                Amount
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Share
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                At risk
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(risk.par.amount)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatPercent(risk.par.ratio)}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                PAR 30
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(risk.par30.amount)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatPercent(
                                                    risk.par30.ratio,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                PAR 60
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(risk.par60.amount)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatPercent(
                                                    risk.par60.ratio,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                        <TableRow>
                                            <TableCell className="font-medium">
                                                PAR 90
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(risk.par90.amount)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatPercent(
                                                    risk.par90.ratio,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </section>

                            <section className="space-y-3">
                                <h2 className="text-xs font-semibold tracking-wide uppercase">
                                    Aging
                                </h2>

                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Bucket</TableHead>
                                            <TableHead className="text-right">
                                                Outstanding
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Share
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {agingRows.map((row) => (
                                            <TableRow key={row.key}>
                                                <TableCell className="font-medium">
                                                    {row.label}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatMoney(
                                                        aging[row.key],
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {formatPercent(
                                                        share(aging[row.key]),
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>

                                    <TableFooter>
                                        <TableRow>
                                            <TableCell>Total</TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(agingTotal)}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {agingTotal > 0
                                                    ? '100.00%'
                                                    : '0.00%'}
                                            </TableCell>
                                        </TableRow>
                                    </TableFooter>
                                </Table>
                            </section>
                        </div>

                        <section className="mt-6 space-y-3">
                            <h2 className="text-xs font-semibold tracking-wide uppercase">
                                Collections
                            </h2>

                            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4">
                                <Metric
                                    label="Due today"
                                    value={formatMoney(collections.due_today)}
                                />
                                <Metric
                                    label="Due this week"
                                    value={formatMoney(
                                        collections.due_this_week,
                                    )}
                                />
                                <Metric
                                    label="Overdue"
                                    value={formatMoney(collections.overdue)}
                                />
                                <Metric
                                    label="As of"
                                    value={formatDateOnly(collections.as_of)}
                                />
                            </dl>
                        </section>

                        <footer className="mt-6 border-t pt-4 text-xs text-muted-foreground">
                            Figures cover {summary.loans} loan
                            {summary.loans === 1 ? '' : 's'} on file and are
                            rounded to two decimals.
                        </footer>
                    </article>
                </div>
            </div>
        </>
    );
}

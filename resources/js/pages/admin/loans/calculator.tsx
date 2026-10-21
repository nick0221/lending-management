import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calculator, Loader2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
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
import { formatDateOnly, formatMoney, formatPercent } from '@/lib/format';
import { dashboard as adminDashboard } from '@/routes/admin';
import {
    calculator as loanCalculator,
    index as loansIndex,
} from '@/routes/admin/loans';
import type { LoanProductOption, LoanSchedulePreview } from '@/types';

type CalculatorFilters = {
    loan_product_id: string;
    principal: string;
    term_months: string;
};

export default function LoanCalculator({
    loanProducts,
    filters,
    preview,
}: {
    loanProducts: LoanProductOption[];
    filters: CalculatorFilters;
    preview: LoanSchedulePreview | null;
}) {
    const { data, setData, get, processing, errors } =
        useForm<CalculatorFilters>(filters);

    const selectedProduct = loanProducts.find(
        (option) => String(option.value) === data.loan_product_id,
    );

    const hasFilters =
        data.loan_product_id !== '' ||
        data.principal !== '' ||
        data.term_months !== '';

    const handleProductChange = (value: string): void => {
        const product = loanProducts.find(
            (option) => String(option.value) === value,
        );

        if (!product) {
            setData({ ...data, loan_product_id: value });

            return;
        }

        const currentTerm = Number(data.term_months);
        const suggestedTerm = Math.min(
            Math.max(12, product.min_term_months),
            product.max_term_months,
        );

        setData({
            ...data,
            loan_product_id: value,
            term_months:
                data.term_months !== '' &&
                currentTerm >= product.min_term_months &&
                currentTerm <= product.max_term_months
                    ? data.term_months
                    : String(suggestedTerm),
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        get(loanCalculator.url(), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Loan calculator" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Loan calculator"
                    description="Quote a full schedule before creating a loan."
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={loansIndex()}>
                                <ArrowLeft /> Back to loans
                            </Link>
                        </Button>
                    }
                />

                <SectionCard
                    title="Terms"
                    description="Choose an active product and enter the amount and term."
                    contentClassName="p-4"
                >
                    <form
                        onSubmit={submit}
                        className="grid gap-4 md:grid-cols-2 lg:grid-cols-4 lg:items-end"
                    >
                        <div className="grid gap-2 lg:col-span-2">
                            <Label htmlFor="calculator-product">
                                Loan product
                            </Label>

                            <Select
                                value={data.loan_product_id}
                                onValueChange={handleProductChange}
                            >
                                <SelectTrigger id="calculator-product">
                                    <SelectValue placeholder="Choose a product" />
                                </SelectTrigger>

                                <SelectContent>
                                    {loanProducts.map((product) => (
                                        <SelectItem
                                            key={product.value}
                                            value={String(product.value)}
                                        >
                                            {product.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <InputError message={errors.loan_product_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="calculator-principal">
                                Principal
                            </Label>

                            <Input
                                id="calculator-principal"
                                type="number"
                                min={selectedProduct?.min_amount}
                                max={selectedProduct?.max_amount}
                                step="0.01"
                                value={data.principal}
                                onChange={(event) =>
                                    setData('principal', event.target.value)
                                }
                                placeholder="0.00"
                            />

                            <InputError message={errors.principal} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="calculator-term">
                                Term (months)
                            </Label>

                            <Input
                                id="calculator-term"
                                type="number"
                                min={selectedProduct?.min_term_months}
                                max={selectedProduct?.max_term_months}
                                step="1"
                                value={data.term_months}
                                onChange={(event) =>
                                    setData('term_months', event.target.value)
                                }
                                placeholder="12"
                            />

                            <InputError message={errors.term_months} />
                        </div>

                        <div className="flex flex-wrap items-center gap-2 lg:col-span-4">
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <Loader2 className="animate-spin" />
                                ) : (
                                    <Calculator />
                                )}
                                Calculate
                            </Button>

                            {hasFilters && (
                                <Button variant="ghost" asChild>
                                    <Link href={loanCalculator()}>Clear</Link>
                                </Button>
                            )}

                            {selectedProduct && (
                                <p className="text-xs text-muted-foreground">
                                    {formatPercent(
                                        selectedProduct.interest_rate,
                                    )}{' '}
                                    {selectedProduct.interest_method_label},{' '}
                                    {selectedProduct.repayment_frequency_label.toLowerCase()}
                                    , {formatMoney(selectedProduct.min_amount)}–
                                    {formatMoney(selectedProduct.max_amount)},{' '}
                                    {selectedProduct.min_term_months}–
                                    {selectedProduct.max_term_months} months.
                                </p>
                            )}
                        </div>
                    </form>
                </SectionCard>

                {preview ? (
                    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                        <SectionCard
                            title="Indicative schedule"
                            description={`${preview.periods} ${
                                preview.periods === 1 ? 'payment' : 'payments'
                            } starting from the next repayment period.`}
                            contentClassName="p-0"
                        >
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>#</TableHead>
                                            <TableHead>Due</TableHead>
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
                                                Total
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Balance
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>

                                    <TableBody>
                                        {preview.installments.map(
                                            (installment) => (
                                                <TableRow
                                                    key={installment.sequence}
                                                >
                                                    <TableCell className="text-muted-foreground">
                                                        {installment.sequence}
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground">
                                                        {formatDateOnly(
                                                            installment.due_date,
                                                        )}
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
                                                            installment.total,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {formatMoney(
                                                            installment.balance,
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>

                                    <TableFooter>
                                        <TableRow>
                                            <TableCell colSpan={2}>
                                                Totals
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    preview.totals.principal,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    preview.totals.interest,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {formatMoney(
                                                    preview.totals.fee,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-medium tabular-nums">
                                                {formatMoney(
                                                    preview.totals.total,
                                                )}
                                            </TableCell>
                                            <TableCell />
                                        </TableRow>
                                    </TableFooter>
                                </Table>
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Summary"
                            description="Whole-term totals."
                            contentClassName="p-4"
                        >
                            <dl className="space-y-2">
                                <SummaryRow
                                    label="Payments"
                                    value={String(preview.periods)}
                                />
                                <SummaryRow
                                    label="Principal"
                                    value={formatMoney(
                                        preview.totals.principal,
                                    )}
                                />
                                <SummaryRow
                                    label="Interest"
                                    value={formatMoney(preview.totals.interest)}
                                />
                                <SummaryRow
                                    label="Processing fee"
                                    value={formatMoney(preview.totals.fee)}
                                />
                                <SummaryRow
                                    label="Total repayable"
                                    value={formatMoney(preview.totals.total)}
                                    strong
                                />
                            </dl>

                            <p className="mt-4 text-xs text-muted-foreground">
                                Indicative only. The final schedule is generated
                                when the loan is approved.
                            </p>
                        </SectionCard>
                    </div>
                ) : (
                    <SectionCard
                        title="Indicative schedule"
                        description="Enter the terms above to see a quote."
                    >
                        <p className="p-6 text-sm text-muted-foreground">
                            Choose a product and enter a principal and term to
                            see the full schedule.
                        </p>
                    </SectionCard>
                )}
            </div>
        </>
    );
}

LoanCalculator.layout = {
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
            title: 'Calculator',
            href: loanCalculator(),
        },
    ],
};

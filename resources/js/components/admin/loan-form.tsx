import { Form, Link, useHttp } from '@inertiajs/react';
import { CircleHelp, Loader2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
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
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDateOnly, formatMoney, formatPercent } from '@/lib/format';
import {
    index as loansIndex,
    preview as previewLoan,
} from '@/routes/admin/loans';
import type {
    BorrowerOption,
    LoanProductOption,
    LoanSchedulePreview,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

type Props = {
    action: RouteFormDefinition<'post'>;
    borrowers: BorrowerOption[];
    loanProducts: LoanProductOption[];
    submitLabel: string;
};

type PreviewForm = {
    loan_product_id: string;
    principal: string;
    term_months: string;
};

export function LoanForm({
    action,
    borrowers,
    loanProducts,
    submitLabel,
}: Props) {
    const [borrowerId, setBorrowerId] = useState<string>('');
    const [loanProductId, setLoanProductId] = useState<string>('');
    const [principal, setPrincipal] = useState<string>('');
    const [termMonths, setTermMonths] = useState<string>('');
    const [preview, setPreview] = useState<LoanSchedulePreview | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [previewFailed, setPreviewFailed] = useState(false);

    const previewRequest = useHttp<PreviewForm, LoanSchedulePreview>({
        loan_product_id: '',
        principal: '',
        term_months: '',
    });
    const previewRequestRef = useRef(previewRequest);
    const previewSequence = useRef(0);

    useEffect(() => {
        previewRequestRef.current = previewRequest;
    });

    const selectedProduct = loanProducts.find(
        (option) => String(option.value) === loanProductId,
    );
    const selectedBorrower = borrowers.find(
        (option) => String(option.value) === borrowerId,
    );

    const principalValue = Number(principal);
    const termValue = Number(termMonths);

    const canPreview = Boolean(
        selectedProduct &&
        principal.trim() !== '' &&
        Number.isFinite(principalValue) &&
        principalValue >= Number(selectedProduct.min_amount) &&
        principalValue <= Number(selectedProduct.max_amount) &&
        Number.isInteger(termValue) &&
        termValue >= selectedProduct.min_term_months &&
        termValue <= selectedProduct.max_term_months,
    );

    const handleProductChange = (value: string): void => {
        setLoanProductId(value);

        const product = loanProducts.find(
            (option) => String(option.value) === value,
        );

        if (!product) {
            return;
        }

        const suggestedTerm = Math.min(
            Math.max(12, product.min_term_months),
            product.max_term_months,
        );

        setTermMonths((current) => {
            const parsed = Number(current);

            return current !== '' &&
                parsed >= product.min_term_months &&
                parsed <= product.max_term_months
                ? current
                : String(suggestedTerm);
        });
    };

    useEffect(() => {
        if (!canPreview) {
            previewSequence.current += 1;
            setPreview(null);
            setPreviewFailed(false);
            setPreviewing(false);

            return;
        }

        const sequence = ++previewSequence.current;

        const timeout = setTimeout(() => {
            setPreviewing(true);

            const request = previewRequestRef.current;
            request.setData({
                loan_product_id: loanProductId,
                principal,
                term_months: termMonths,
            });
            void request
                .post(previewLoan().url, {
                    onSuccess: (data) => {
                        if (sequence !== previewSequence.current) {
                            return;
                        }

                        setPreview(data);
                        setPreviewFailed(false);
                    },
                    onError: () => {
                        if (sequence !== previewSequence.current) {
                            return;
                        }

                        setPreview(null);
                        setPreviewFailed(true);
                    },
                    onHttpException: () => {
                        if (sequence !== previewSequence.current) {
                            return;
                        }

                        setPreview(null);
                        setPreviewFailed(true);
                    },
                    onNetworkError: () => {
                        if (sequence !== previewSequence.current) {
                            return;
                        }

                        setPreview(null);
                        setPreviewFailed(true);
                    },
                    onFinish: () => {
                        if (sequence !== previewSequence.current) {
                            return;
                        }

                        setPreviewing(false);
                    },
                })
                .catch(() => {
                    // Failures are already reflected in the preview state.
                });
        }, 350);

        return () => clearTimeout(timeout);
    }, [canPreview, loanProductId, principal, termMonths]);

    return (
        <Form {...action}>
            {({ processing, errors }) => (
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-6">
                        <SectionCard
                            title="Borrower and product"
                            description="Who is borrowing and under which product terms."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="borrower_id">Borrower</Label>

                                <Select
                                    value={borrowerId}
                                    onValueChange={setBorrowerId}
                                >
                                    <SelectTrigger
                                        id="borrower_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a borrower" />
                                    </SelectTrigger>

                                    <SelectContent>
                                        {borrowers.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <input
                                    type="hidden"
                                    name="borrower_id"
                                    value={borrowerId}
                                />

                                <InputError message={errors.borrower_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="loan_product_id">
                                    Loan product
                                </Label>

                                <Select
                                    value={loanProductId}
                                    onValueChange={handleProductChange}
                                >
                                    <SelectTrigger
                                        id="loan_product_id"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a loan product" />
                                    </SelectTrigger>

                                    <SelectContent>
                                        {loanProducts.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={String(option.value)}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <input
                                    type="hidden"
                                    name="loan_product_id"
                                    value={loanProductId}
                                />

                                <InputError message={errors.loan_product_id} />
                            </div>

                            {selectedProduct && (
                                <ProductTerms product={selectedProduct} />
                            )}
                        </SectionCard>

                        <SectionCard
                            title="Loan terms"
                            description="The amount and repayment term. The product limits are enforced."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="principal">Principal</Label>

                                    <Input
                                        id="principal"
                                        name="principal"
                                        type="number"
                                        inputMode="decimal"
                                        step="0.01"
                                        min={
                                            selectedProduct
                                                ? Number(
                                                      selectedProduct.min_amount,
                                                  )
                                                : 0
                                        }
                                        max={
                                            selectedProduct
                                                ? Number(
                                                      selectedProduct.max_amount,
                                                  )
                                                : undefined
                                        }
                                        value={principal}
                                        onChange={(event) =>
                                            setPrincipal(event.target.value)
                                        }
                                        required
                                    />

                                    {selectedProduct && (
                                        <p className="text-xs text-muted-foreground">
                                            Between{' '}
                                            {formatMoney(
                                                selectedProduct.min_amount,
                                            )}{' '}
                                            and{' '}
                                            {formatMoney(
                                                selectedProduct.max_amount,
                                            )}{' '}
                                            for this product.
                                        </p>
                                    )}

                                    <InputError message={errors.principal} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="term_months">
                                        Term (months)
                                    </Label>

                                    <Input
                                        id="term_months"
                                        name="term_months"
                                        type="number"
                                        step="1"
                                        min={
                                            selectedProduct?.min_term_months ??
                                            1
                                        }
                                        max={selectedProduct?.max_term_months}
                                        value={termMonths}
                                        onChange={(event) =>
                                            setTermMonths(event.target.value)
                                        }
                                        required
                                    />

                                    {selectedProduct && (
                                        <p className="text-xs text-muted-foreground">
                                            {selectedProduct.min_term_months}–
                                            {selectedProduct.max_term_months}{' '}
                                            months for this product.
                                        </p>
                                    )}

                                    <InputError message={errors.term_months} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>

                                <textarea
                                    id="notes"
                                    name="notes"
                                    className={textareaClassName}
                                    placeholder="Optional internal notes"
                                />

                                <InputError message={errors.notes} />
                            </div>
                        </SectionCard>

                        <Button asChild variant="ghost">
                            <Link href={loansIndex()}>Cancel</Link>
                        </Button>
                    </div>

                    <SectionCard
                        title="Indicative schedule"
                        description="The first three payments, based on today's date."
                        className="lg:sticky lg:top-6"
                        contentClassName="space-y-4 p-4"
                    >
                        <dl className="space-y-2 text-sm">
                            <SummaryRow
                                label="Borrower"
                                value={
                                    selectedBorrower?.label ?? 'Not selected'
                                }
                            />
                            <SummaryRow
                                label="Product"
                                value={selectedProduct?.label ?? 'Not selected'}
                            />
                            <SummaryRow
                                label="Principal"
                                value={
                                    principal.trim() === ''
                                        ? '—'
                                        : formatMoney(principalValue)
                                }
                            />
                            <SummaryRow
                                label="Term"
                                value={
                                    termMonths.trim() === ''
                                        ? '—'
                                        : `${termValue} months`
                                }
                            />
                        </dl>

                        <div className="border-t pt-4">
                            {!selectedProduct ? (
                                <p className="text-sm text-muted-foreground">
                                    Choose a loan product to see an indicative
                                    schedule.
                                </p>
                            ) : !canPreview ? (
                                <p className="text-sm text-muted-foreground">
                                    Enter a principal between{' '}
                                    {formatMoney(selectedProduct.min_amount)}{' '}
                                    and{' '}
                                    {formatMoney(selectedProduct.max_amount)},
                                    and a term between{' '}
                                    {selectedProduct.min_term_months} and{' '}
                                    {selectedProduct.max_term_months} months.
                                </p>
                            ) : previewFailed ? (
                                <p className="text-sm text-destructive">
                                    Could not calculate a preview. Check the
                                    terms and try again.
                                </p>
                            ) : preview ? (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-medium">
                                            First {preview.installments.length}{' '}
                                            of {preview.periods} payments
                                        </p>

                                        {previewing && (
                                            <Loader2 className="size-4 animate-spin text-muted-foreground" />
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        {preview.installments.map(
                                            (installment) => (
                                                <div
                                                    key={installment.sequence}
                                                    className="rounded-lg border bg-muted/30 p-3 text-sm"
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="font-medium">
                                                            #
                                                            {
                                                                installment.sequence
                                                            }{' '}
                                                            ·{' '}
                                                            {formatDateOnly(
                                                                installment.due_date,
                                                            )}
                                                        </span>

                                                        <span className="font-semibold tabular-nums">
                                                            {formatMoney(
                                                                installment.total,
                                                            )}
                                                        </span>
                                                    </div>

                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Principal{' '}
                                                        {formatMoney(
                                                            installment.principal_due,
                                                        )}{' '}
                                                        · Interest{' '}
                                                        {formatMoney(
                                                            installment.interest_due,
                                                        )}
                                                        {installment.fee_due >
                                                            0 && (
                                                            <>
                                                                {' '}
                                                                · Fee{' '}
                                                                {formatMoney(
                                                                    installment.fee_due,
                                                                )}
                                                            </>
                                                        )}
                                                    </p>
                                                </div>
                                            ),
                                        )}
                                    </div>

                                    <dl className="space-y-2 border-t pt-4 text-sm">
                                        <SummaryRow
                                            label="Interest (total)"
                                            value={formatMoney(
                                                preview.totals.interest,
                                            )}
                                        />
                                        <SummaryRow
                                            label="Processing fee"
                                            value={formatMoney(
                                                preview.totals.fee,
                                            )}
                                        />
                                        <SummaryRow
                                            label="Total repayable"
                                            value={formatMoney(
                                                preview.totals.total,
                                            )}
                                            strong
                                        />
                                    </dl>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Calculating…
                                </div>
                            )}
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Indicative only. The final schedule is generated
                            when the loan is approved.
                        </p>

                        <div className="border-t pt-4">
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                                data-test="loan-submit-button"
                            >
                                {processing ? 'Creating…' : submitLabel}
                            </Button>
                        </div>
                    </SectionCard>
                </div>
            )}
        </Form>
    );
}

function ProductTerms({ product }: { product: LoanProductOption }) {
    return (
        <dl className="grid gap-3 rounded-lg border bg-muted/30 p-4 text-sm sm:grid-cols-2">
            <div className="flex items-center justify-between gap-2">
                <dt className="flex items-center gap-1.5 text-muted-foreground">
                    Interest
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="text-muted-foreground transition-colors hover:text-foreground"
                                aria-label="How interest is calculated"
                            >
                                <CircleHelp className="size-3.5" />
                            </button>
                        </TooltipTrigger>

                        <TooltipContent className="max-w-xs text-left text-xs leading-relaxed">
                            {product.interest_method === 'flat' ? (
                                <span>
                                    Flat interest charges the same interest on
                                    the original principal every period. For
                                    example, 10,000 at 12% for 12 months costs
                                    1,200 in interest.
                                </span>
                            ) : (
                                <span>
                                    Reducing balance charges interest on the
                                    balance still owed, so the interest portion
                                    falls as the loan is repaid. The same rate
                                    costs less than a flat loan.
                                </span>
                            )}
                        </TooltipContent>
                    </Tooltip>
                </dt>

                <dd className="font-medium">
                    {formatPercent(product.interest_rate)}{' '}
                    {product.interest_method_label}
                </dd>
            </div>

            <div className="flex items-center justify-between gap-2">
                <dt className="text-muted-foreground">Repayment</dt>
                <dd className="font-medium">
                    {product.repayment_frequency_label}
                </dd>
            </div>

            <div className="flex items-center justify-between gap-2">
                <dt className="text-muted-foreground">Amount</dt>
                <dd className="font-medium">
                    {formatMoney(product.min_amount)} –{' '}
                    {formatMoney(product.max_amount)}
                </dd>
            </div>

            <div className="flex items-center justify-between gap-2">
                <dt className="text-muted-foreground">Term</dt>
                <dd className="font-medium">
                    {product.min_term_months}–{product.max_term_months} months
                </dd>
            </div>
        </dl>
    );
}

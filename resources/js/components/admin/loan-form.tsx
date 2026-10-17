import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import type { BorrowerOption, LoanProductOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

type Props = {
    action: RouteFormDefinition<'post'>;
    borrowers: BorrowerOption[];
    loanProducts: LoanProductOption[];
    submitLabel: string;
};

export function LoanForm({
    action,
    borrowers,
    loanProducts,
    submitLabel,
}: Props) {
    const [borrowerId, setBorrowerId] = useState<string>('');
    const [loanProductId, setLoanProductId] = useState<string>('');

    const selectedProduct = loanProducts.find(
        (option) => String(option.value) === loanProductId,
    );

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="borrower_id">Borrower</Label>

                        <Select value={borrowerId} onValueChange={setBorrowerId}>
                            <SelectTrigger id="borrower_id" className="w-full">
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
                        <Label htmlFor="loan_product_id">Loan product</Label>

                        <Select
                            value={loanProductId}
                            onValueChange={setLoanProductId}
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
                        <dl className="grid gap-3 rounded-xl border border-sidebar-border/70 p-4 text-sm sm:grid-cols-2 dark:border-sidebar-border">
                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Interest
                                </dt>
                                <dd className="font-medium">
                                    {Number(
                                        selectedProduct.interest_rate,
                                    ).toFixed(2)}
                                    % {selectedProduct.interest_method_label}
                                </dd>
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">
                                    Repayment
                                </dt>
                                <dd className="font-medium">
                                    {selectedProduct.repayment_frequency_label}
                                </dd>
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">Amount</dt>
                                <dd className="font-medium">
                                    {Number(
                                        selectedProduct.min_amount,
                                    ).toFixed(2)}{' '}
                                    –{' '}
                                    {Number(
                                        selectedProduct.max_amount,
                                    ).toFixed(2)}
                                </dd>
                            </div>

                            <div className="flex items-center justify-between gap-2">
                                <dt className="text-muted-foreground">Term</dt>
                                <dd className="font-medium">
                                    {selectedProduct.min_term_months}–
                                    {selectedProduct.max_term_months} months
                                </dd>
                            </div>
                        </dl>
                    )}

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="principal">Principal</Label>

                            <Input
                                id="principal"
                                name="principal"
                                type="number"
                                step="0.01"
                                min={
                                    selectedProduct
                                        ? Number(selectedProduct.min_amount)
                                        : 0
                                }
                                max={
                                    selectedProduct
                                        ? Number(selectedProduct.max_amount)
                                        : undefined
                                }
                                required
                            />

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
                                min={selectedProduct?.min_term_months ?? 1}
                                max={selectedProduct?.max_term_months}
                                required
                            />

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

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="loan-submit-button"
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

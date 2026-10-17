import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    InterestMethod,
    InterestMethodOption,
    LoanProduct,
    RepaymentFrequency,
    RepaymentFrequencyOption,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

type Props = {
    action: RouteFormDefinition<'post'>;
    interestMethods: InterestMethodOption[];
    frequencies: RepaymentFrequencyOption[];
    submitLabel: string;
    loanProduct?: LoanProduct;
};

export function LoanProductForm({
    action,
    interestMethods,
    frequencies,
    submitLabel,
    loanProduct,
}: Props) {
    const [interestMethod, setInterestMethod] = useState<InterestMethod>(
        loanProduct?.interest_method ?? interestMethods[0]?.value ?? 'flat',
    );
    const [frequency, setFrequency] = useState<RepaymentFrequency>(
        loanProduct?.repayment_frequency ??
            frequencies[0]?.value ??
            'monthly',
    );
    const [isActive, setIsActive] = useState<boolean>(
        loanProduct?.is_active ?? true,
    );

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Product name</Label>

                        <Input
                            id="name"
                            name="name"
                            defaultValue={loanProduct?.name}
                            required
                            placeholder="e.g. Personal Loan"
                        />

                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="description">Description</Label>

                        <textarea
                            id="description"
                            name="description"
                            defaultValue={loanProduct?.description ?? ''}
                            className={textareaClassName}
                            placeholder="Short summary shown to staff"
                        />

                        <InputError message={errors.description} />
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="interest_method">
                                Interest method
                            </Label>

                            <Select
                                value={interestMethod}
                                onValueChange={(value) =>
                                    setInterestMethod(value as InterestMethod)
                                }
                            >
                                <SelectTrigger
                                    id="interest_method"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select a method" />
                                </SelectTrigger>

                                <SelectContent>
                                    {interestMethods.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <input
                                type="hidden"
                                name="interest_method"
                                value={interestMethod}
                            />

                            <InputError message={errors.interest_method} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="interest_rate">
                                Interest rate (% per annum)
                            </Label>

                            <Input
                                id="interest_rate"
                                name="interest_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                defaultValue={loanProduct?.interest_rate ?? ''}
                                required
                            />

                            <InputError message={errors.interest_rate} />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="repayment_frequency">
                                Repayment frequency
                            </Label>

                            <Select
                                value={frequency}
                                onValueChange={(value) =>
                                    setFrequency(value as RepaymentFrequency)
                                }
                            >
                                <SelectTrigger
                                    id="repayment_frequency"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Select a frequency" />
                                </SelectTrigger>

                                <SelectContent>
                                    {frequencies.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <input
                                type="hidden"
                                name="repayment_frequency"
                                value={frequency}
                            />

                            <InputError
                                message={errors.repayment_frequency}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="processing_fee_rate">
                                Processing fee (%)
                            </Label>

                            <Input
                                id="processing_fee_rate"
                                name="processing_fee_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                defaultValue={
                                    loanProduct?.processing_fee_rate ?? 0
                                }
                                required
                            />

                            <InputError
                                message={errors.processing_fee_rate}
                            />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="penalty_rate">
                                Late penalty (% per month)
                            </Label>

                            <Input
                                id="penalty_rate"
                                name="penalty_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                defaultValue={loanProduct?.penalty_rate ?? 0}
                                required
                            />

                            <InputError message={errors.penalty_rate} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="grace_period_days">
                                Grace period (days)
                            </Label>

                            <Input
                                id="grace_period_days"
                                name="grace_period_days"
                                type="number"
                                step="1"
                                min="0"
                                max="365"
                                defaultValue={
                                    loanProduct?.grace_period_days ?? 0
                                }
                                required
                            />

                            <InputError message={errors.grace_period_days} />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="min_amount">
                                Minimum amount
                            </Label>

                            <Input
                                id="min_amount"
                                name="min_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={loanProduct?.min_amount ?? ''}
                                required
                            />

                            <InputError message={errors.min_amount} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="max_amount">
                                Maximum amount
                            </Label>

                            <Input
                                id="max_amount"
                                name="max_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                defaultValue={loanProduct?.max_amount ?? ''}
                                required
                            />

                            <InputError message={errors.max_amount} />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="min_term_months">
                                Minimum term (months)
                            </Label>

                            <Input
                                id="min_term_months"
                                name="min_term_months"
                                type="number"
                                step="1"
                                min="1"
                                defaultValue={
                                    loanProduct?.min_term_months ?? ''
                                }
                                required
                            />

                            <InputError message={errors.min_term_months} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="max_term_months">
                                Maximum term (months)
                            </Label>

                            <Input
                                id="max_term_months"
                                name="max_term_months"
                                type="number"
                                step="1"
                                min="1"
                                defaultValue={
                                    loanProduct?.max_term_months ?? ''
                                }
                                required
                            />

                            <InputError message={errors.max_term_months} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="is_active"
                            checked={isActive}
                            onCheckedChange={(checked) =>
                                setIsActive(checked === true)
                            }
                        />

                        <Label htmlFor="is_active">Active product</Label>

                        <input
                            type="hidden"
                            name="is_active"
                            value={isActive ? '1' : '0'}
                        />

                        <InputError message={errors.is_active} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="loan-product-submit-button"
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

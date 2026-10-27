import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormSummary } from '@/components/admin/form-summary';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { SectionCard } from '@/components/section-card';
import { Badge } from '@/components/ui/badge';
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
import { formatMoney, formatPercent } from '@/lib/format';
import { index as loanProductsIndex } from '@/routes/admin/loan-products';
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
    defaults?: {
        penalty_rate: string;
        grace_period_days: string;
    };
};

export function LoanProductForm({
    action,
    interestMethods,
    frequencies,
    submitLabel,
    loanProduct,
    defaults,
}: Props) {
    const [name, setName] = useState(loanProduct?.name ?? '');
    const [interestMethod, setInterestMethod] = useState<InterestMethod>(
        loanProduct?.interest_method ?? interestMethods[0]?.value ?? 'flat',
    );
    const [interestRate, setInterestRate] = useState(
        String(loanProduct?.interest_rate ?? ''),
    );
    const [frequency, setFrequency] = useState<RepaymentFrequency>(
        loanProduct?.repayment_frequency ?? frequencies[0]?.value ?? 'monthly',
    );
    const selectedInterestMethod = interestMethods.find(
        (option) => option.value === interestMethod,
    );
    const selectedFrequency = frequencies.find(
        (option) => option.value === frequency,
    );
    const [processingFeeRate, setProcessingFeeRate] = useState(
        String(loanProduct?.processing_fee_rate ?? 0),
    );
    const [penaltyRate, setPenaltyRate] = useState(
        String(loanProduct?.penalty_rate ?? defaults?.penalty_rate ?? 0),
    );
    const [gracePeriodDays, setGracePeriodDays] = useState(
        String(
            loanProduct?.grace_period_days ?? defaults?.grace_period_days ?? 0,
        ),
    );
    const [minAmount, setMinAmount] = useState(
        String(loanProduct?.min_amount ?? ''),
    );
    const [maxAmount, setMaxAmount] = useState(
        String(loanProduct?.max_amount ?? ''),
    );
    const [minTermMonths, setMinTermMonths] = useState(
        String(loanProduct?.min_term_months ?? ''),
    );
    const [maxTermMonths, setMaxTermMonths] = useState(
        String(loanProduct?.max_term_months ?? ''),
    );
    const [isActive, setIsActive] = useState<boolean>(
        loanProduct?.is_active ?? true,
    );

    const interestMethodLabel =
        interestMethods.find((option) => option.value === interestMethod)
            ?.label ?? interestMethod;
    const frequencyLabel =
        frequencies.find((option) => option.value === frequency)?.label ??
        frequency;
    const amountRange =
        minAmount.trim() !== '' && maxAmount.trim() !== ''
            ? `${formatMoney(minAmount)} – ${formatMoney(maxAmount)}`
            : '—';
    const termRange =
        minTermMonths.trim() !== '' && maxTermMonths.trim() !== ''
            ? `${minTermMonths} – ${maxTermMonths} months`
            : '—';

    return (
        <Form
            {...action}
            className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="space-y-6">
                        <SectionCard
                            title="Product"
                            description="The name and availability staff see when originating a loan."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Product name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
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
                                    defaultValue={
                                        loanProduct?.description ?? ''
                                    }
                                    className={textareaClassName}
                                    placeholder="Short summary shown to staff"
                                />

                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={isActive}
                                    onCheckedChange={(checked) =>
                                        setIsActive(checked === true)
                                    }
                                />

                                <div className="grid gap-0.5">
                                    <Label htmlFor="is_active">
                                        Active product
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Inactive products stay on file but
                                        cannot be used for new loans.
                                    </p>
                                </div>

                                <input
                                    type="hidden"
                                    name="is_active"
                                    value={isActive ? '1' : '0'}
                                />

                                <InputError message={errors.is_active} />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Interest and fees"
                            description="How the product earns interest and charges its processing fee."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="interest_method">
                                        Interest method
                                    </Label>

                                    <Select
                                        value={interestMethod}
                                        onValueChange={(value) =>
                                            setInterestMethod(
                                                value as InterestMethod,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="interest_method"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a method">
                                                {selectedInterestMethod?.label}
                                            </SelectValue>
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

                                    <InputError
                                        message={errors.interest_method}
                                    />
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
                                        value={interestRate}
                                        onChange={(event) =>
                                            setInterestRate(event.target.value)
                                        }
                                        required
                                    />

                                    <InputError
                                        message={errors.interest_rate}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
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
                                    value={processingFeeRate}
                                    onChange={(event) =>
                                        setProcessingFeeRate(event.target.value)
                                    }
                                    required
                                />

                                <InputError
                                    message={errors.processing_fee_rate}
                                />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Repayment and penalties"
                            description="How often installments fall due and what happens when they are late."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="repayment_frequency">
                                        Repayment frequency
                                    </Label>

                                    <Select
                                        value={frequency}
                                        onValueChange={(value) =>
                                            setFrequency(
                                                value as RepaymentFrequency,
                                            )
                                        }
                                    >
                                        <SelectTrigger
                                            id="repayment_frequency"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a frequency">
                                                {selectedFrequency?.label}
                                            </SelectValue>
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
                                        value={penaltyRate}
                                        onChange={(event) =>
                                            setPenaltyRate(event.target.value)
                                        }
                                        required
                                    />

                                    <InputError message={errors.penalty_rate} />
                                </div>
                            </div>

                            <div className="grid gap-2 sm:max-w-xs">
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
                                    value={gracePeriodDays}
                                    onChange={(event) =>
                                        setGracePeriodDays(event.target.value)
                                    }
                                    required
                                />

                                <InputError
                                    message={errors.grace_period_days}
                                />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Limits"
                            description="Staff can only originate loans within these bounds."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
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
                                        value={minAmount}
                                        onChange={(event) =>
                                            setMinAmount(event.target.value)
                                        }
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
                                        value={maxAmount}
                                        onChange={(event) =>
                                            setMaxAmount(event.target.value)
                                        }
                                        required
                                    />

                                    <InputError message={errors.max_amount} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
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
                                        value={minTermMonths}
                                        onChange={(event) =>
                                            setMinTermMonths(event.target.value)
                                        }
                                        required
                                    />

                                    <InputError
                                        message={errors.min_term_months}
                                    />
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
                                        value={maxTermMonths}
                                        onChange={(event) =>
                                            setMaxTermMonths(event.target.value)
                                        }
                                        required
                                    />

                                    <InputError
                                        message={errors.max_term_months}
                                    />
                                </div>
                            </div>
                        </SectionCard>
                    </div>

                    <FormSummary
                        title="Product summary"
                        description="The terms staff will see."
                        footer={
                            <>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                    data-test="loan-product-submit-button"
                                >
                                    {submitLabel}
                                </Button>

                                <Button
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link href={loanProductsIndex()}>
                                        Cancel
                                    </Link>
                                </Button>
                            </>
                        }
                    >
                        <SummaryRow
                            label="Name"
                            value={name.trim() || 'New product'}
                        />
                        <SummaryRow
                            label="Interest"
                            value={
                                interestRate.trim() === ''
                                    ? '—'
                                    : `${formatPercent(interestRate)} · ${interestMethodLabel}`
                            }
                        />
                        <SummaryRow label="Repayment" value={frequencyLabel} />
                        <SummaryRow label="Amount" value={amountRange} />
                        <SummaryRow label="Term" value={termRange} />
                        <SummaryRow
                            label="Status"
                            value={
                                <Badge
                                    variant={isActive ? 'default' : 'outline'}
                                >
                                    {isActive ? 'Active' : 'Inactive'}
                                </Badge>
                            }
                        />
                    </FormSummary>
                </>
            )}
        </Form>
    );
}

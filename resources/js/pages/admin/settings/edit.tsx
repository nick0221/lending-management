import { Form, Head, Link } from '@inertiajs/react';
import { Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import { FormSummary } from '@/components/admin/form-summary';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as editSettings, update } from '@/routes/admin/settings';
import type { AppSettings } from '@/types';

export default function EditSettings({ settings }: { settings: AppSettings }) {
    const [organisationName, setOrganisationName] = useState(
        settings.organisation_name,
    );
    const [currencySymbol, setCurrencySymbol] = useState(
        settings.currency_symbol,
    );
    const [defaultPenaltyRate, setDefaultPenaltyRate] = useState(
        settings.default_penalty_rate,
    );
    const [defaultGraceDays, setDefaultGraceDays] = useState(
        settings.default_grace_period_days,
    );
    const [moneyRateLimit, setMoneyRateLimit] = useState(
        settings.money_rate_limit,
    );
    const [reportsRateLimit, setReportsRateLimit] = useState(
        settings.reports_rate_limit,
    );
    const [remindersRateLimit, setRemindersRateLimit] = useState(
        settings.reminders_rate_limit,
    );

    return (
        <>
            <Head title="Settings" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Settings"
                    description="Organisation identity, new-product defaults and endpoint limits."
                />

                <Form
                    {...update.form()}
                    className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-6">
                                <SectionCard
                                    title="Organisation"
                                    description="Used on receipts, reports and the navigation."
                                    contentClassName="p-4"
                                >
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="organisation_name">
                                                Organisation name
                                            </Label>

                                            <Input
                                                id="organisation_name"
                                                name="organisation_name"
                                                value={organisationName}
                                                onChange={(event) =>
                                                    setOrganisationName(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                maxLength={120}
                                            />

                                            <InputError
                                                message={
                                                    errors.organisation_name
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="currency_symbol">
                                                Currency symbol
                                            </Label>

                                            <Input
                                                id="currency_symbol"
                                                name="currency_symbol"
                                                value={currencySymbol}
                                                onChange={(event) =>
                                                    setCurrencySymbol(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                                maxLength={8}
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                Shown before every amount.
                                            </p>

                                            <InputError
                                                message={errors.currency_symbol}
                                            />
                                        </div>
                                    </div>
                                </SectionCard>

                                <SectionCard
                                    title="Loan defaults"
                                    description="Applied when a new loan product is created. Existing products keep their own values."
                                    contentClassName="p-4"
                                >
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="default_penalty_rate">
                                                Late penalty (% per month)
                                            </Label>

                                            <Input
                                                id="default_penalty_rate"
                                                name="default_penalty_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                value={defaultPenaltyRate}
                                                onChange={(event) =>
                                                    setDefaultPenaltyRate(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                Monthly rate applied to overdue
                                                balances.
                                            </p>

                                            <InputError
                                                message={
                                                    errors.default_penalty_rate
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="default_grace_period_days">
                                                Grace period (days)
                                            </Label>

                                            <Input
                                                id="default_grace_period_days"
                                                name="default_grace_period_days"
                                                type="number"
                                                step="1"
                                                min="0"
                                                max="365"
                                                value={defaultGraceDays}
                                                onChange={(event) =>
                                                    setDefaultGraceDays(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                Days after the due date before
                                                penalties begin to accrue.
                                            </p>

                                            <InputError
                                                message={
                                                    errors.default_grace_period_days
                                                }
                                            />
                                        </div>
                                    </div>
                                </SectionCard>

                                <SectionCard
                                    title="Endpoint limits"
                                    description="Requests per minute, per user. Changes take effect on the next request."
                                    contentClassName="p-4"
                                >
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label htmlFor="money_rate_limit">
                                                Money endpoints
                                            </Label>

                                            <Input
                                                id="money_rate_limit"
                                                name="money_rate_limit"
                                                type="number"
                                                step="1"
                                                min="1"
                                                max="1000"
                                                value={moneyRateLimit}
                                                onChange={(event) =>
                                                    setMoneyRateLimit(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                Payments, receipts and
                                                statements.
                                            </p>

                                            <InputError
                                                message={
                                                    errors.money_rate_limit
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="reports_rate_limit">
                                                Report downloads
                                            </Label>

                                            <Input
                                                id="reports_rate_limit"
                                                name="reports_rate_limit"
                                                type="number"
                                                step="1"
                                                min="1"
                                                max="1000"
                                                value={reportsRateLimit}
                                                onChange={(event) =>
                                                    setReportsRateLimit(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                CSV report downloads.
                                            </p>

                                            <InputError
                                                message={
                                                    errors.reports_rate_limit
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="reminders_rate_limit">
                                                Bulk reminders
                                            </Label>

                                            <Input
                                                id="reminders_rate_limit"
                                                name="reminders_rate_limit"
                                                type="number"
                                                step="1"
                                                min="1"
                                                max="1000"
                                                value={remindersRateLimit}
                                                onChange={(event) =>
                                                    setRemindersRateLimit(
                                                        event.target.value,
                                                    )
                                                }
                                                required
                                            />

                                            <p className="text-xs text-muted-foreground">
                                                Overdue reminder batches.
                                            </p>

                                            <InputError
                                                message={
                                                    errors.reminders_rate_limit
                                                }
                                            />
                                        </div>
                                    </div>
                                </SectionCard>
                            </div>

                            <FormSummary
                                title="Summary"
                                description="A quick read of the saved values."
                                footer={
                                    <>
                                        <Button
                                            type="submit"
                                            className="w-full"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Loader2 className="animate-spin" />
                                            ) : (
                                                <Save />
                                            )}
                                            Save settings
                                        </Button>

                                        <Button
                                            variant="secondary"
                                            className="w-full"
                                            asChild
                                        >
                                            <Link href={adminDashboard()}>
                                                Cancel
                                            </Link>
                                        </Button>
                                    </>
                                }
                            >
                                <SummaryRow
                                    label="Organisation"
                                    value={organisationName || '—'}
                                />
                                <SummaryRow
                                    label="Currency"
                                    value={currencySymbol || '—'}
                                />
                                <SummaryRow
                                    label="Default penalty"
                                    value={`${defaultPenaltyRate || '0'}% / month`}
                                />
                                <SummaryRow
                                    label="Grace period"
                                    value={`${defaultGraceDays || '0'} days`}
                                />
                                <SummaryRow
                                    label="Money limit"
                                    value={`${moneyRateLimit || '0'} / min`}
                                />
                                <SummaryRow
                                    label="Report limit"
                                    value={`${reportsRateLimit || '0'} / min`}
                                />
                                <SummaryRow
                                    label="Reminder limit"
                                    value={`${remindersRateLimit || '0'} / min`}
                                />
                            </FormSummary>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

EditSettings.layout = {
    breadcrumbs: [
        {
            title: 'Administration',
            href: adminDashboard(),
        },
        {
            title: 'Settings',
            href: editSettings(),
        },
    ],
};

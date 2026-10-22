import { Form, Head, Link } from '@inertiajs/react';
import { Loader2, Save } from 'lucide-react';
import { useState } from 'react';
import { FormSummary } from '@/components/admin/form-summary';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { SectionCard } from '@/components/section-card';
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
    const [sms, setSms] = useState({
        driver: settings.sms_driver,
        from: settings.sms_from,
        url: settings.sms_http_url,
        method: settings.sms_http_method,
        format: settings.sms_http_format,
        tokenHeader: settings.sms_http_token_header,
        tokenPrefix: settings.sms_http_token_prefix,
        headers: settings.sms_http_headers,
        toField: settings.sms_http_to_field,
        messageField: settings.sms_http_message_field,
        fromField: settings.sms_http_from_field,
        timeout: settings.sms_http_timeout,
        connectTimeout: settings.sms_http_connect_timeout,
    });
    const [smsToken, setSmsToken] = useState('');
    const [clearSmsToken, setClearSmsToken] = useState(false);

    const updateSms = (key: keyof typeof sms, value: string) => {
        setSms((current) => ({ ...current, [key]: value }));
    };

    return (
        <>
            <Head title="Settings" />

            <div className="mx-auto w-full max-w-6xl space-y-6 p-4">
                <PageHeader
                    title="Settings"
                    description="Organisation identity, new-product defaults, notification gateway and endpoint limits."
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
                                    title="SMS gateway"
                                    description="Used for due-date reminders, overdue notices and receipts. Choose Log only to write messages to the application log without sending them."
                                    contentClassName="p-4"
                                >
                                    <div className="grid gap-5">
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="sms_driver">
                                                    Driver
                                                </Label>

                                                <Select
                                                    value={sms.driver}
                                                    onValueChange={(value) =>
                                                        updateSms(
                                                            'driver',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="sms_driver"
                                                        className="w-full"
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>

                                                    <SelectContent>
                                                        <SelectItem value="log">
                                                            Log only
                                                        </SelectItem>

                                                        <SelectItem value="http">
                                                            HTTP gateway
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>

                                                <input
                                                    type="hidden"
                                                    name="sms_driver"
                                                    value={sms.driver}
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    HTTP sends through your
                                                    gateway. Log records the
                                                    message without delivering
                                                    it.
                                                </p>

                                                <InputError
                                                    message={errors.sms_driver}
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="sms_from">
                                                    Sender ID
                                                </Label>

                                                <Input
                                                    id="sms_from"
                                                    name="sms_from"
                                                    value={sms.from}
                                                    onChange={(event) =>
                                                        updateSms(
                                                            'from',
                                                            event.target.value,
                                                        )
                                                    }
                                                    maxLength={64}
                                                    placeholder="LENDER"
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    Optional sender name or
                                                    number expected by the
                                                    gateway.
                                                </p>

                                                <InputError
                                                    message={errors.sms_from}
                                                />
                                            </div>
                                        </div>

                                        <div
                                            className={
                                                sms.driver === 'http'
                                                    ? 'grid gap-5'
                                                    : 'hidden'
                                            }
                                        >
                                            <div className="grid gap-2">
                                                <Label htmlFor="sms_http_url">
                                                    Gateway URL
                                                </Label>

                                                <Input
                                                    id="sms_http_url"
                                                    name="sms_http_url"
                                                    type="text"
                                                    inputMode="url"
                                                    value={sms.url}
                                                    onChange={(event) =>
                                                        updateSms(
                                                            'url',
                                                            event.target.value,
                                                        )
                                                    }
                                                    required={
                                                        sms.driver === 'http'
                                                    }
                                                    maxLength={2048}
                                                    placeholder="https://sms.example.com/api/send"
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    The endpoint that accepts
                                                    the HTTP request.
                                                </p>

                                                <InputError
                                                    message={
                                                        errors.sms_http_url
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_method">
                                                        HTTP method
                                                    </Label>

                                                    <Select
                                                        value={sms.method}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateSms(
                                                                'method',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="sms_http_method"
                                                            className="w-full"
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>

                                                        <SelectContent>
                                                            <SelectItem value="POST">
                                                                POST
                                                            </SelectItem>

                                                            <SelectItem value="PUT">
                                                                PUT
                                                            </SelectItem>

                                                            <SelectItem value="PATCH">
                                                                PATCH
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>

                                                    <input
                                                        type="hidden"
                                                        name="sms_http_method"
                                                        value={sms.method}
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_method
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_format">
                                                        Payload format
                                                    </Label>

                                                    <Select
                                                        value={sms.format}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            updateSms(
                                                                'format',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id="sms_http_format"
                                                            className="w-full"
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>

                                                        <SelectContent>
                                                            <SelectItem value="json">
                                                                JSON body
                                                            </SelectItem>

                                                            <SelectItem value="form">
                                                                Form fields
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>

                                                    <input
                                                        type="hidden"
                                                        name="sms_http_format"
                                                        value={sms.format}
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_format
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_token">
                                                        API token
                                                    </Label>

                                                    <Input
                                                        id="sms_http_token"
                                                        name="sms_http_token"
                                                        type="password"
                                                        value={smsToken}
                                                        onChange={(event) =>
                                                            setSmsToken(
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        disabled={clearSmsToken}
                                                        autoComplete="new-password"
                                                        placeholder={
                                                            settings.sms_http_token_configured
                                                                ? 'Stored - leave blank to keep'
                                                                : 'No token stored'
                                                        }
                                                    />

                                                    <p className="text-xs text-muted-foreground">
                                                        {settings.sms_http_token_configured
                                                            ? 'A token is stored. Enter a new value to replace it.'
                                                            : 'Optional. The token is encrypted before it is stored.'}
                                                    </p>

                                                    <div className="flex items-center gap-2">
                                                        <Checkbox
                                                            id="sms_http_clear_token"
                                                            checked={
                                                                clearSmsToken
                                                            }
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                setClearSmsToken(
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                        />

                                                        <Label
                                                            htmlFor="sms_http_clear_token"
                                                            className="text-sm font-normal"
                                                        >
                                                            Remove stored token
                                                        </Label>

                                                        <input
                                                            type="hidden"
                                                            name="sms_http_clear_token"
                                                            value={
                                                                clearSmsToken
                                                                    ? '1'
                                                                    : '0'
                                                            }
                                                        />
                                                    </div>

                                                    <InputError
                                                        message={
                                                            errors.sms_http_token
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_token_header">
                                                        Token header
                                                    </Label>

                                                    <Input
                                                        id="sms_http_token_header"
                                                        name="sms_http_token_header"
                                                        value={sms.tokenHeader}
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'tokenHeader',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                        maxLength={100}
                                                        placeholder="Authorization"
                                                    />

                                                    <p className="text-xs text-muted-foreground">
                                                        Header that carries the
                                                        token above.
                                                    </p>

                                                    <InputError
                                                        message={
                                                            errors.sms_http_token_header
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="sms_http_token_prefix">
                                                    Token prefix
                                                </Label>

                                                <Input
                                                    id="sms_http_token_prefix"
                                                    name="sms_http_token_prefix"
                                                    value={sms.tokenPrefix}
                                                    onChange={(event) =>
                                                        updateSms(
                                                            'tokenPrefix',
                                                            event.target.value,
                                                        )
                                                    }
                                                    maxLength={100}
                                                    placeholder="Bearer "
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    Added before the token, for
                                                    example <code>Bearer </code>
                                                    . Leave blank if the gateway
                                                    expects only the token.
                                                </p>

                                                <InputError
                                                    message={
                                                        errors.sms_http_token_prefix
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="sms_http_headers">
                                                    Custom headers
                                                </Label>

                                                <textarea
                                                    id="sms_http_headers"
                                                    name="sms_http_headers"
                                                    value={sms.headers}
                                                    onChange={(event) =>
                                                        updateSms(
                                                            'headers',
                                                            event.target.value,
                                                        )
                                                    }
                                                    rows={4}
                                                    className="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 font-mono text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    placeholder={
                                                        '{"X-Tenant": "lending"}'
                                                    }
                                                />

                                                <p className="text-xs text-muted-foreground">
                                                    Optional JSON object of
                                                    extra header names and
                                                    values.
                                                </p>

                                                <InputError
                                                    message={
                                                        errors.sms_http_headers
                                                    }
                                                />
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-3">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_to_field">
                                                        Recipient field
                                                    </Label>

                                                    <Input
                                                        id="sms_http_to_field"
                                                        name="sms_http_to_field"
                                                        value={sms.toField}
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'toField',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                        maxLength={50}
                                                        placeholder="to"
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_to_field
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_message_field">
                                                        Message field
                                                    </Label>

                                                    <Input
                                                        id="sms_http_message_field"
                                                        name="sms_http_message_field"
                                                        value={sms.messageField}
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'messageField',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                        maxLength={50}
                                                        placeholder="message"
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_message_field
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_from_field">
                                                        Sender field
                                                    </Label>

                                                    <Input
                                                        id="sms_http_from_field"
                                                        name="sms_http_from_field"
                                                        value={sms.fromField}
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'fromField',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                        maxLength={50}
                                                        placeholder="from"
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_from_field
                                                        }
                                                    />
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_timeout">
                                                        Request timeout
                                                        (seconds)
                                                    </Label>

                                                    <Input
                                                        id="sms_http_timeout"
                                                        name="sms_http_timeout"
                                                        type="number"
                                                        step="1"
                                                        min="1"
                                                        max="60"
                                                        value={sms.timeout}
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'timeout',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_timeout
                                                        }
                                                    />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="sms_http_connect_timeout">
                                                        Connect timeout
                                                        (seconds)
                                                    </Label>

                                                    <Input
                                                        id="sms_http_connect_timeout"
                                                        name="sms_http_connect_timeout"
                                                        type="number"
                                                        step="1"
                                                        min="1"
                                                        max="30"
                                                        value={
                                                            sms.connectTimeout
                                                        }
                                                        onChange={(event) =>
                                                            updateSms(
                                                                'connectTimeout',
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        required
                                                    />

                                                    <InputError
                                                        message={
                                                            errors.sms_http_connect_timeout
                                                        }
                                                    />
                                                </div>
                                            </div>
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
                                <SummaryRow
                                    label="SMS driver"
                                    value={
                                        sms.driver === 'http'
                                            ? 'HTTP gateway'
                                            : 'Log only'
                                    }
                                />
                                <SummaryRow
                                    label="SMS endpoint"
                                    value={
                                        sms.driver === 'http'
                                            ? sms.url || 'Not set'
                                            : 'Not used'
                                    }
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

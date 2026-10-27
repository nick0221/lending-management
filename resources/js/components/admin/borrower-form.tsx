import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormSummary } from '@/components/admin/form-summary';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { KycBadge } from '@/components/kyc-badge';
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
import { Switch } from '@/components/ui/switch';
import { index as borrowersIndex } from '@/routes/admin/borrowers';
import type { Borrower, KycStatus, KycStatusOption, UserOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

const NO_USER = '__none__';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] md:text-sm';

type Props = {
    action: RouteFormDefinition<'post'>;
    kycStatuses: KycStatusOption[];
    users: UserOption[];
    submitLabel: string;
    borrower?: Borrower;
};

type NotificationPreference = {
    key:
        | 'notify_due_soon'
        | 'notify_overdue'
        | 'notify_receipts'
        | 'notify_statements';
    label: string;
    description: string;
};

const notificationPreferences: NotificationPreference[] = [
    {
        key: 'notify_due_soon',
        label: 'Due-date reminders',
        description: 'A reminder a few days before an installment is due.',
    },
    {
        key: 'notify_overdue',
        label: 'Overdue notices',
        description: 'A notice while installments remain past due.',
    },
    {
        key: 'notify_receipts',
        label: 'Payment receipts',
        description: 'A receipt whenever a payment is recorded.',
    },
    {
        key: 'notify_statements',
        label: 'Statements',
        description: 'Allow staff to email the loan statement on request.',
    },
];

export function BorrowerForm({
    action,
    kycStatuses,
    users,
    submitLabel,
    borrower,
}: Props) {
    const [name, setName] = useState(borrower?.name ?? '');
    const [phone, setPhone] = useState(borrower?.phone ?? '');
    const [email, setEmail] = useState(borrower?.email ?? '');
    const [kycStatus, setKycStatus] = useState<KycStatus>(
        borrower?.kyc_status ?? kycStatuses[0]?.value ?? 'pending',
    );
    const [userId, setUserId] = useState<string>(
        borrower?.user_id ? String(borrower.user_id) : NO_USER,
    );
    const [preferences, setPreferences] = useState({
        notify_due_soon: borrower?.notify_due_soon ?? true,
        notify_overdue: borrower?.notify_overdue ?? true,
        notify_receipts: borrower?.notify_receipts ?? true,
        notify_statements: borrower?.notify_statements ?? true,
    });
    const [notifySms, setNotifySms] = useState(borrower?.notify_sms ?? false);

    const selectedUser = users.find(
        (option) => String(option.value) === userId,
    );
    const selectedKycStatus = kycStatuses.find(
        (option) => option.value === kycStatus,
    );
    const enabledNotifications = notificationPreferences.filter(
        (preference) => preferences[preference.key],
    ).length;

    return (
        <Form
            {...action}
            className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="space-y-6">
                        <SectionCard
                            title="Identity"
                            description="The borrower's legal name and identifying details."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="national_id">
                                        National ID
                                    </Label>

                                    <Input
                                        id="national_id"
                                        name="national_id"
                                        defaultValue={
                                            borrower?.national_id ?? ''
                                        }
                                        placeholder="Government issued ID"
                                    />

                                    <InputError message={errors.national_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="date_of_birth">
                                        Date of birth
                                    </Label>

                                    <Input
                                        id="date_of_birth"
                                        name="date_of_birth"
                                        type="date"
                                        defaultValue={
                                            borrower?.date_of_birth ?? ''
                                        }
                                    />

                                    <InputError
                                        message={errors.date_of_birth}
                                    />
                                </div>
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Contact"
                            description="How to reach the borrower."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>

                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        value={phone}
                                        onChange={(event) =>
                                            setPhone(event.target.value)
                                        }
                                        required
                                        autoComplete="tel"
                                        placeholder="Phone number"
                                    />

                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>

                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value={email}
                                        onChange={(event) =>
                                            setEmail(event.target.value)
                                        }
                                        autoComplete="email"
                                        placeholder="Email address"
                                    />

                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>

                                <textarea
                                    id="address"
                                    name="address"
                                    defaultValue={borrower?.address ?? ''}
                                    className={textareaClassName}
                                    placeholder="Residential address"
                                />

                                <InputError message={errors.address} />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="KYC and account"
                            description="Verification state and optional portal access."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="kyc_status">
                                        KYC status
                                    </Label>

                                    <Select
                                        value={kycStatus}
                                        onValueChange={(value) =>
                                            setKycStatus(value as KycStatus)
                                        }
                                    >
                                        <SelectTrigger
                                            id="kyc_status"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Select a status">
                                                {selectedKycStatus?.label}
                                            </SelectValue>
                                        </SelectTrigger>

                                        <SelectContent>
                                            {kycStatuses.map((option) => (
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
                                        name="kyc_status"
                                        value={kycStatus}
                                    />

                                    <InputError message={errors.kyc_status} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="user_id">
                                        Linked user account
                                    </Label>

                                    <Select
                                        value={userId}
                                        onValueChange={setUserId}
                                    >
                                        <SelectTrigger
                                            id="user_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="No linked account">
                                                {userId === NO_USER
                                                    ? 'No linked account'
                                                    : selectedUser?.label}
                                            </SelectValue>
                                        </SelectTrigger>

                                        <SelectContent>
                                            <SelectItem value={NO_USER}>
                                                No linked account
                                            </SelectItem>

                                            {users.map((option) => (
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
                                        name="user_id"
                                        value={userId === NO_USER ? '' : userId}
                                    />

                                    <InputError message={errors.user_id} />
                                </div>
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Notifications"
                            description="Choose the events and delivery channels. Email needs an address; SMS needs a phone number."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="flex items-start justify-between gap-4 rounded-md border bg-muted/30 p-3">
                                <div className="grid min-w-0 flex-1 gap-1">
                                    <Label htmlFor="notify_sms">
                                        SMS notifications
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Text due-date reminders, overdue notices
                                        and receipts. Statements stay by email.
                                    </p>
                                </div>

                                <Switch
                                    id="notify_sms"
                                    checked={notifySms}
                                    onCheckedChange={setNotifySms}
                                />

                                <input
                                    type="hidden"
                                    name="notify_sms"
                                    value={notifySms ? '1' : '0'}
                                />
                            </div>

                            {notificationPreferences.map((preference) => (
                                <div
                                    key={preference.key}
                                    className="flex items-start justify-between gap-4"
                                >
                                    <div className="grid min-w-0 flex-1 gap-1">
                                        <Label htmlFor={preference.key}>
                                            {preference.label}
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            {preference.description}
                                        </p>
                                    </div>

                                    <Switch
                                        id={preference.key}
                                        checked={preferences[preference.key]}
                                        onCheckedChange={(checked) =>
                                            setPreferences({
                                                ...preferences,
                                                [preference.key]: checked,
                                            })
                                        }
                                    />

                                    <input
                                        type="hidden"
                                        name={preference.key}
                                        value={
                                            preferences[preference.key]
                                                ? '1'
                                                : '0'
                                        }
                                    />
                                </div>
                            ))}

                            <InputError
                                message={
                                    errors.notify_due_soon ??
                                    errors.notify_overdue ??
                                    errors.notify_receipts ??
                                    errors.notify_statements ??
                                    errors.notify_sms
                                }
                            />
                        </SectionCard>

                        <SectionCard
                            title="Notes"
                            description="Internal notes, not shown to the borrower."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="notes">Notes</Label>

                                <textarea
                                    id="notes"
                                    name="notes"
                                    defaultValue={borrower?.notes ?? ''}
                                    className={textareaClassName}
                                    placeholder="Internal notes about this borrower"
                                />

                                <InputError message={errors.notes} />
                            </div>
                        </SectionCard>
                    </div>

                    <FormSummary
                        title="Borrower summary"
                        description="The essentials of this record."
                        footer={
                            <>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                    data-test="borrower-submit-button"
                                >
                                    {submitLabel}
                                </Button>

                                <Button
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link href={borrowersIndex()}>Cancel</Link>
                                </Button>
                            </>
                        }
                    >
                        <SummaryRow
                            label="Name"
                            value={name.trim() || 'New borrower'}
                        />
                        <SummaryRow label="Phone" value={phone.trim() || '—'} />
                        <SummaryRow label="Email" value={email.trim() || '—'} />
                        <SummaryRow
                            label="KYC"
                            value={<KycBadge status={kycStatus} />}
                        />
                        <SummaryRow
                            label="Linked account"
                            value={selectedUser?.label ?? 'None'}
                        />
                        <SummaryRow
                            label="Notifications"
                            value={
                                enabledNotifications === 0
                                    ? 'All off'
                                    : `${enabledNotifications} of ${notificationPreferences.length} on`
                            }
                        />
                        <SummaryRow
                            label="SMS"
                            value={notifySms ? 'On' : 'Off'}
                        />
                    </FormSummary>
                </>
            )}
        </Form>
    );
}

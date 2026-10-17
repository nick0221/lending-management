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

export function BorrowerForm({
    action,
    kycStatuses,
    users,
    submitLabel,
    borrower,
}: Props) {
    const [kycStatus, setKycStatus] = useState<KycStatus>(
        borrower?.kyc_status ?? kycStatuses[0]?.value ?? 'pending',
    );
    const [userId, setUserId] = useState<string>(
        borrower?.user_id ? String(borrower.user_id) : NO_USER,
    );

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Full name</Label>

                        <Input
                            id="name"
                            name="name"
                            defaultValue={borrower?.name}
                            required
                            autoComplete="name"
                            placeholder="Full name"
                        />

                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="national_id">National ID</Label>

                            <Input
                                id="national_id"
                                name="national_id"
                                defaultValue={borrower?.national_id ?? ''}
                                placeholder="Government issued ID"
                            />

                            <InputError message={errors.national_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="date_of_birth">Date of birth</Label>

                            <Input
                                id="date_of_birth"
                                name="date_of_birth"
                                type="date"
                                defaultValue={borrower?.date_of_birth ?? ''}
                            />

                            <InputError message={errors.date_of_birth} />
                        </div>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="phone">Phone</Label>

                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                defaultValue={borrower?.phone}
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
                                defaultValue={borrower?.email ?? ''}
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

                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="kyc_status">KYC status</Label>

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
                                    <SelectValue placeholder="Select a status" />
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

                            <Select value={userId} onValueChange={setUserId}>
                                <SelectTrigger id="user_id" className="w-full">
                                    <SelectValue placeholder="No linked account" />
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

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="borrower-submit-button"
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

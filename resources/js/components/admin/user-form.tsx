import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormSummary } from '@/components/admin/form-summary';
import { SummaryRow } from '@/components/admin/summary-row';
import InputError from '@/components/input-error';
import { RoleBadge } from '@/components/role-badge';
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
import { index as usersIndex } from '@/routes/admin/users';
import type { AdminUser, Role, RoleOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type Props = {
    action: RouteFormDefinition<'post'>;
    roles: RoleOption[];
    submitLabel: string;
    user?: AdminUser;
    passwordRequired?: boolean;
};

export function UserForm({
    action,
    roles,
    submitLabel,
    user,
    passwordRequired = false,
}: Props) {
    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [role, setRole] = useState<Role>(
        user?.role ?? roles[0]?.value ?? 'user',
    );

    return (
        <Form
            {...action}
            className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]"
        >
            {({ processing, errors }) => (
                <>
                    <div className="space-y-6">
                        <SectionCard
                            title="Account"
                            description="The person who will sign in."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

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

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    required
                                    autoComplete="email"
                                    placeholder="Email address"
                                />

                                <InputError message={errors.email} />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Access"
                            description="The role controls which areas and actions are available."
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="role">Role</Label>

                                <Select
                                    value={role}
                                    onValueChange={(value) =>
                                        setRole(value as Role)
                                    }
                                >
                                    <SelectTrigger id="role" className="w-full">
                                        <SelectValue placeholder="Select a role" />
                                    </SelectTrigger>

                                    <SelectContent>
                                        {roles.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <input type="hidden" name="role" value={role} />

                                <InputError message={errors.role} />
                            </div>
                        </SectionCard>

                        <SectionCard
                            title="Password"
                            description={
                                passwordRequired
                                    ? 'Set the password for this account.'
                                    : 'Leave both fields blank to keep the current password.'
                            }
                            contentClassName="space-y-4 p-4"
                        >
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>

                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        required={passwordRequired}
                                        placeholder="Password"
                                    />

                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>

                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        required={passwordRequired}
                                        placeholder="Confirm password"
                                    />

                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>
                            </div>
                        </SectionCard>
                    </div>

                    <FormSummary
                        title="Account summary"
                        description="The access this account will have."
                        footer={
                            <>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full"
                                    data-test="user-submit-button"
                                >
                                    {submitLabel}
                                </Button>

                                <Button
                                    variant="outline"
                                    className="w-full"
                                    asChild
                                >
                                    <Link href={usersIndex()}>Cancel</Link>
                                </Button>
                            </>
                        }
                    >
                        <SummaryRow
                            label="Name"
                            value={name.trim() || 'New user'}
                        />
                        <SummaryRow label="Email" value={email.trim() || '—'} />
                        <SummaryRow
                            label="Role"
                            value={<RoleBadge role={role} />}
                        />
                        <SummaryRow
                            label="Password"
                            value={
                                passwordRequired
                                    ? 'Will be set'
                                    : 'Unchanged unless filled'
                            }
                        />
                    </FormSummary>
                </>
            )}
        </Form>
    );
}

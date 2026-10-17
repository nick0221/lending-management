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
    const [role, setRole] = useState<Role>(user?.role ?? roles[0]?.value ?? 'user');

    return (
        <Form {...action} className="space-y-6">
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            name="name"
                            defaultValue={user?.name}
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
                            defaultValue={user?.email}
                            required
                            autoComplete="email"
                            placeholder="Email address"
                        />

                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role">Role</Label>

                        <Select
                            value={role}
                            onValueChange={(value) => setRole(value as Role)}
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

                    <div className="grid gap-2">
                        <Label htmlFor="password">
                            Password
                            {!passwordRequired && (
                                <span className="text-muted-foreground">
                                    (leave blank to keep the current password)
                                </span>
                            )}
                        </Label>

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
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            data-test="user-submit-button"
                        >
                            {submitLabel}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

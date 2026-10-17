export type Role = 'super_admin' | 'admin' | 'user';

export type User = {
    id: number;
    name: string;
    email: string;
    role: Role;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type RoleOption = {
    value: Role;
    label: string;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
    email_verified_at: string | null;
    created_at: string | null;
};

export type AdminUserListItem = AdminUser & {
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

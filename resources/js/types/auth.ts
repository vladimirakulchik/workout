export type UserRole = 'super_admin' | 'trainer' | 'coach' | 'player';

export type User = {
    id: number;
    first_name: string;
    last_name: string;
    name: string;
    email: string;
    role: UserRole;
    photo_url: string;
    impersonating: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

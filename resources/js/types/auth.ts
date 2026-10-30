export type User = {
    name: string;
    email: string | null;
    avatar?: string;
};

export type Auth = {
    user: User | null;
};

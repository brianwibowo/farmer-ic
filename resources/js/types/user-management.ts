export type ManagedUser = {
    id: string;
    name: string;
    email: string;
    role: string | null;
    team_ids: number[];
    teams: { id: number; name: string; slug: string }[];
    assigned_farm_id: string | null;
    assigned_farm: {
        id: string;
        name: string;
        team_id: number;
        team_name: string | null;
    } | null;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can?: { update: boolean; delete: boolean };
};

export type UserManagementScope = {
    global: boolean;
    multiTenant: boolean;
    team: { id: number; name: string } | null;
};

export type UserOptions = {
    roles: { value: string; label: string }[];
    teams: { id: number; name: string }[];
    farms: { id: string; name: string; team_id: number }[];
};

export type UserFilters = {
    search?: string;
    role?: string;
    team_id?: number | string;
    farm_id?: string;
    per_page?: number;
};

export type UserPage = {
    data: ManagedUser[];
    total: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    next_page_url: string | null;
    prev_page_url: string | null;
};

export const roleLabel = (role: string | null): string =>
    role ? role.charAt(0).toUpperCase() + role.slice(1) : 'Role belum diatur';

export function userDate(value: string | null): string {
    return value
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
              new Date(value),
          )
        : 'Belum tersedia';
}

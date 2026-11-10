import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { AccountProfile } from '@/types/auth';
import type { Team } from '@/types/teams';
import type { UserManagementScope } from '@/types/user-management';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            account?: AccountProfile | null;
            sidebarOpen: boolean;
            ownerNavigation?: {
                farms: boolean;
                activities: boolean;
                sops: boolean;
                users: boolean;
                finance: boolean;
            };
            navigation?: {
                admin: boolean;
                petugas?: boolean;
                notifications: boolean;
                farms: boolean;
                logbook: boolean;
                users: boolean;
                teamSwitcher: boolean;
            };
            currentTeam: Team | null;
            teams: Team[];
            canCreateTeam: boolean;
            userManagementScope?: UserManagementScope;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

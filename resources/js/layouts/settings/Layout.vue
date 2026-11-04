<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { KeyRound, User, Users } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as teams } from '@/routes/teams';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: editProfile(),
        icon: User,
    },
    {
        title: 'Keamanan',
        href: editSecurity(),
        icon: KeyRound,
    },
    {
        title: 'Tim',
        href: teams(),
        icon: Users,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
const page = usePage();
</script>

<template>
    <div
        :class="
            page.props.navigation?.admin
                ? 'admin-page-content'
                : 'mx-auto w-full max-w-screen-2xl p-4 md:p-6 lg:p-8'
        "
    >
        <div class="flex flex-col gap-8 lg:flex-row">
            <aside class="w-full shrink-0 lg:w-56">
                <nav
                    class="flex flex-row gap-1 overflow-x-auto lg:flex-col lg:overflow-visible"
                    aria-label="Pengaturan"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-auto justify-start gap-2.5 px-3 lg:w-full',
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-primary/10 font-semibold text-primary'
                                : 'text-muted-foreground',
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0"
                            />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <div class="min-w-0 flex-1">
                <div class="max-w-2xl space-y-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

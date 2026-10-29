<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LandPlot, LayoutGrid, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

defineProps<{ adminTheme?: boolean }>();

const page = usePage();

const dashboardUrl = computed(() =>
    page.props.navigation?.admin ? admin.home().url : '/',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: page.props.navigation?.admin ? 'Dashboard' : 'Beranda',
        href: dashboardUrl.value,
        icon: LayoutGrid,
    },
    ...(page.props.navigation?.farms
        ? [
              {
                  title: 'Farm Management',
                  href: '/admin/farm-management',
                  icon: LandPlot,
              },
          ]
        : []),
    ...(page.props.navigation?.users
        ? [
              {
                  title: 'User Management',
                  href: '/admin/user-management',
                  icon: Users,
              },
          ]
        : []),
]);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar
        :class="adminTheme ? 'admin-dashboard-theme' : undefined"
        collapsible="icon"
        variant="inset"
    >
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu v-if="page.props.navigation?.teamSwitcher">
                <SidebarMenuItem>
                    <TeamSwitcher :admin-theme="adminTheme" />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser :admin-theme="adminTheme" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

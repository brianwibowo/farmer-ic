<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ClipboardList,
    FolderGit2,
    LandPlot,
    LayoutGrid,
    ListChecks,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Sprout,
    Users,
    Wheat,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import BrandLogo from '@/components/BrandLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import TeamSwitcher from '@/components/TeamSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

const props = defineProps<{ adminTheme?: boolean }>();

const page = usePage();
const { isMobile, state, openMobile, toggleSidebar, setOpenMobile } =
    useSidebar();
const { isCurrentOrParentUrl } = useCurrentUrl();
const isCollapsed = computed(
    () => !isMobile.value && state.value === 'collapsed',
);
const toggleLabel = computed(() =>
    isMobile.value
        ? 'Tutup navigasi'
        : isCollapsed.value
          ? 'Perluas navigasi'
          : 'Perkecil navigasi',
);

const dashboardUrl = computed(() =>
    page.props.navigation?.admin ? admin.home().url : '/',
);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: page.props.navigation?.admin ? 'Dashboard' : 'Beranda',
        href: page.props.navigation?.admin
            ? admin.dashboard().url
            : dashboardUrl.value,
        icon: LayoutGrid,
        exact: true,
    },
    ...(page.props.navigation?.farms
        ? [
              {
                  title: props.adminTheme
                      ? 'Manajemen Kebun'
                      : 'Farm Management',
                  href: '/admin/farm-management',
                  icon: LandPlot,
                  isActive:
                      isCurrentOrParentUrl('/admin/farm-management') ||
                      page.component.startsWith('farmer/blocks/'),
              },
          ]
        : []),
    ...(page.props.navigation?.admin && page.props.navigation?.logbook
        ? [{ title: 'Logbook', href: '/admin/logbook', icon: BookOpen }]
        : []),
    ...(page.props.navigation?.admin
        ? [
              {
                  title: 'Transaksi Penanaman',
                  href: '/admin/transaksi-penanaman',
                  icon: Sprout,
                  isActive: isCurrentOrParentUrl(
                      '/admin/transaksi-penanaman',
                  ),
              },
              {
                  title: 'Transaksi Panen',
                  href: '/admin/transaksi-panen',
                  icon: Wheat,
                  isActive: isCurrentOrParentUrl('/admin/transaksi-panen'),
              },
              {
                  title: 'SOP',
                  href: '/admin/sops',
                  icon: ClipboardList,
              },
              {
                  title: 'Tugas',
                  href: '/admin/tasks',
                  icon: ListChecks,
                  isActive: isCurrentOrParentUrl('/admin/tasks'),
              },
          ]
        : []),
    ...(page.props.navigation?.users
        ? [
              {
                  title: props.adminTheme
                      ? 'Manajemen Pengguna'
                      : 'User Management',
                  href: '/admin/user-management',
                  icon: Users,
              },
          ]
        : []),
]);

const settingsNavItems = computed<NavItem[]>(() => [
    {
        title: 'Pengaturan',
        href: '/settings/profile',
        icon: Settings,
        isActive: isCurrentOrParentUrl('/settings'),
    },
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
        :class="
            adminTheme
                ? [
                      'admin-dashboard-theme',
                      'admin-sidebar',
                      { 'admin-sidebar-collapsed': isCollapsed },
                  ]
                : undefined
        "
        collapsible="icon"
        :variant="adminTheme ? 'sidebar' : 'inset'"
    >
        <SidebarHeader :class="adminTheme ? 'admin-sidebar-header' : undefined">
            <div v-if="adminTheme" class="admin-sidebar-brand-row">
                <Link
                    :href="dashboardUrl"
                    class="admin-sidebar-brand"
                    aria-label="Hijauan — Beranda"
                    @success="setOpenMobile(false)"
                >
                    <AppLogoIcon
                        v-if="isCollapsed"
                        class="admin-sidebar-mark"
                    />
                    <BrandLogo
                        v-else
                        mark-class="size-10"
                        wordmark-class="text-xl"
                    />
                </Link>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="admin-sidebar-toggle"
                    :aria-label="toggleLabel"
                    :title="toggleLabel"
                    :aria-expanded="isMobile ? openMobile : !isCollapsed"
                    aria-controls="admin-sidebar-navigation"
                    data-test="admin-sidebar-toggle"
                    @click="toggleSidebar"
                >
                    <X v-if="isMobile" aria-hidden="true" />
                    <PanelLeftOpen v-else-if="isCollapsed" aria-hidden="true" />
                    <PanelLeftClose v-else aria-hidden="true" />
                </Button>
            </div>
            <SidebarMenu v-else>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboardUrl">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <SidebarMenu
                v-if="page.props.navigation?.teamSwitcher"
                :class="adminTheme ? 'admin-sidebar-team' : undefined"
            >
                <SidebarMenuItem>
                    <p
                        v-if="adminTheme && !isCollapsed"
                        class="admin-sidebar-team-label"
                    >
                        Tim aktif
                    </p>
                    <TeamSwitcher :admin-theme="adminTheme" />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent
            :id="adminTheme ? 'admin-sidebar-navigation' : undefined"
            :class="adminTheme ? 'admin-sidebar-content' : undefined"
        >
            <NavMain
                :items="mainNavItems"
                :label="adminTheme ? 'Menu utama' : 'Platform'"
            />
            <NavMain
                v-if="adminTheme"
                :items="settingsNavItems"
                label="Lainnya"
                aria-label="Navigasi pengaturan"
            />
        </SidebarContent>

        <SidebarFooter :class="adminTheme ? 'admin-sidebar-footer' : undefined">
            <NavFooter v-if="!adminTheme" :items="footerNavItems" />
            <NavUser :admin-theme="adminTheme" />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

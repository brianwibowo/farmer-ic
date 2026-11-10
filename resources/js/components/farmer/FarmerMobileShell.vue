<script setup lang="ts">
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';

type Props = {
    activeKey?: FarmerNavKey;
    showNavigation?: boolean;
    showAppearanceToggle?: boolean;
    responsiveDesktop?: boolean;
    dashboardTheme?: boolean;
    showHeader?: boolean;
    flushContent?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
    showNavigation: true,
    showAppearanceToggle: false,
    responsiveDesktop: false,
    dashboardTheme: false,
    showHeader: true,
    flushContent: false,
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

function select(key: FarmerNavKey): void {
    emit('select', key);
}
</script>

<template>
    <div
        :class="[
            {
                'owner-responsive-shell':
                    props.responsiveDesktop && !props.dashboardTheme,
            },
            props.dashboardTheme
                ? 'admin-dashboard-theme petugas-dashboard-theme bg-background font-sans text-sm'
                : 'bg-card',
        ]"
        class="flex min-h-dvh w-full justify-center px-6 pt-6 text-foreground max-[480px]:px-0 max-[480px]:pt-0"
    >
        <section
            class="farmer-shell-frame relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] text-card-foreground max-[480px]:h-dvh max-[480px]:max-w-none max-[480px]:rounded-none"
            :class="
                props.dashboardTheme
                    ? 'petugas-mobile-frame bg-background'
                    : 'bg-card'
            "
        >
            <div
                v-if="props.showHeader && props.activeKey !== 'ai'"
                class="farmer-shell-header shrink-0 bg-card px-4 pt-4 pb-2"
                :class="{ 'petugas-mobile-header': props.dashboardTheme }"
            >
                <DashboardHeader
                    :show-appearance-toggle="props.showAppearanceToggle"
                    :dashboard-theme="props.dashboardTheme"
                >
                    <template
                        v-if="props.responsiveDesktop && props.showNavigation"
                        #navigation
                    >
                        <div class="farmer-desktop-navigation">
                            <slot name="desktop-navigation" />
                        </div>
                    </template>
                </DashboardHeader>
            </div>

            <main
                class="farmer-shell-main min-h-0 flex-1 overscroll-y-contain"
                :class="
                    props.activeKey === 'ai'
                        ? 'overflow-hidden pb-0'
                        : props.flushContent
                          ? 'overflow-y-auto pb-0'
                          : 'scroll-pb-8 overflow-y-auto pb-8'
                "
            >
                <slot />
            </main>

            <div v-if="props.showNavigation" class="farmer-shell-navigation">
                <slot name="navigation">
                    <FarmerBottomNav
                        :active-key="props.activeKey"
                        :dashboard-theme="props.dashboardTheme"
                        @select="select"
                    />
                </slot>
            </div>
            <Toaster
                :class="
                    props.dashboardTheme ? 'admin-dashboard-theme' : undefined
                "
            />
        </section>
    </div>
</template>

<style scoped>
.farmer-shell-navigation {
    display: contents;
}
.farmer-desktop-navigation {
    display: none;
}
/* Only OwnerLayout opts into tablet/desktop expansion. */
@media (min-width: 640px) {
    .owner-responsive-shell {
        height: 100dvh;
        min-height: 0;
        padding: 0;
        background: var(--background);
    }
    .owner-responsive-shell .farmer-shell-frame {
        height: 100dvh;
        max-width: 2240px;
        border-radius: 0;
        background: var(--background);
    }
    .owner-responsive-shell .farmer-shell-header {
        border-bottom: 1px solid var(--border);
        padding: 8px 24px;
    }
    .owner-responsive-shell .farmer-shell-main {
        padding-bottom: 0;
        scrollbar-width: thin;
        -ms-overflow-style: auto;
    }
    .owner-responsive-shell .farmer-shell-main::-webkit-scrollbar {
        display: block;
        width: 10px;
    }
    .owner-responsive-shell .farmer-shell-main::-webkit-scrollbar-thumb {
        border-radius: 12px;
        background: var(--border);
    }
}
@media (min-width: 1024px) {
    .owner-responsive-shell .farmer-shell-header {
        padding-right: 32px;
        padding-left: 32px;
    }
    .owner-responsive-shell .farmer-desktop-navigation {
        display: flex;
        min-width: 0;
        flex: 1;
        justify-content: center;
    }
    .owner-responsive-shell .farmer-shell-navigation {
        display: none;
    }
}

main {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

main::-webkit-scrollbar {
    display: none;
}

[data-farmer-shell] {
    color-scheme: light;
    --background: #f9faf9;
    --foreground: #070807;
    --surface: #abe7b2;
    --surface-foreground: #070807;
    --card: #ffffff;
    --card-foreground: #070807;
    --popover: #ffffff;
    --popover-foreground: #070807;
    --primary: #68bb6c;
    --primary-foreground: #ffffff;
    --secondary: #1c5e21;
    --secondary-foreground: #ffffff;
    --accent: #93a99f;
    --accent-foreground: #070807;
    --muted: #ebf3ed;
    --muted-foreground: #2a1a0e;
    --text-muted: #2a1a0e;
    --border: #d9d9d9;
    --input: #d9d9d9;
    --ring: #68bb6c;
    --success: #4a943e;
    --success-foreground: #ffffff;
    --warning: #edc92c;
    --warning-foreground: #070807;
    --error: #bc0202;
    --error-foreground: #ffffff;
    --destructive: #bc0202;
    --destructive-foreground: #ffffff;
    --info: #d9d9d9;
    --info-foreground: #070807;
    --disable: #e5e7ea;
    --disable-foreground: #8c9096;
}
</style>

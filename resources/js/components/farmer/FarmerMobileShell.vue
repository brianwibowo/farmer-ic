<script setup lang="ts">
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import { Toaster } from '@/components/ui/sonner';
import { usePage } from '@inertiajs/vue3';
import OwnerTenantContext from '@/components/owner/OwnerTenantContext.vue';

type Props = {
    activeKey?: FarmerNavKey;
    showNavigation?: boolean;
    landscapeDashboard?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
    showNavigation: true,
    landscapeDashboard: false,
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const page = usePage();

function select(key: FarmerNavKey): void {
    emit('select', key);
}
</script>

<template>
    <div
        :class="{ 'owner-dashboard-shell': props.landscapeDashboard }"
        class="flex min-h-dvh w-full justify-center bg-card px-6 pt-6 text-foreground max-[480px]:px-0 max-[480px]:pt-0"
    >
        <section
            class="farmer-shell-frame relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] bg-card text-card-foreground max-[480px]:h-dvh max-[480px]:max-w-none max-[480px]:rounded-none"
        >
            <div
                v-if="props.activeKey !== 'ai'"
                class="farmer-shell-header shrink-0 bg-card px-4 pt-4 pb-2"
            >
                <DashboardHeader />
                <OwnerTenantContext
                    class="farmer-shell-tenant"
                    v-if="
                        props.showNavigation &&
                        page.props.navigation?.teamSwitcher
                    "
                />
            </div>

            <main
                class="min-h-0 flex-1 overscroll-y-contain"
                :class="
                    props.activeKey === 'ai'
                        ? 'overflow-hidden pb-0'
                        : 'scroll-pb-8 overflow-y-auto pb-8'
                "
            >
                <slot />
            </main>

            <slot v-if="props.showNavigation" name="navigation">
                <FarmerBottomNav
                    :active-key="props.activeKey"
                    @select="select"
                />
            </slot>
            <Toaster />
        </section>
    </div>
</template>

<style scoped>
@media (orientation: landscape) and (min-width: 600px) and (max-height: 600px) {
    .owner-dashboard-shell {
        padding: 0;
    }
    .owner-dashboard-shell .farmer-shell-frame {
        height: 100dvh;
        max-width: 1040px;
        border-radius: 0;
    }
    .owner-dashboard-shell .farmer-shell-header {
        display: grid;
        grid-template-columns: minmax(140px, 0.8fr) minmax(0, 1.2fr);
        align-items: center;
        gap: 24px;
        padding-top: 8px;
        padding-bottom: 8px;
    }
    .owner-dashboard-shell .farmer-shell-tenant {
        margin-top: 0;
        border-top: 0;
        padding-top: 0;
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

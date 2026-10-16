<script setup lang="ts">
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';
import { router } from '@inertiajs/vue3';

type Props = {
    activeKey?: FarmerNavKey;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'home',
});

const emit = defineEmits<{
    select: [key: FarmerNavKey];
}>();

const selectedKey = ref<FarmerNavKey>(props.activeKey);

const upcomingPages: Partial<Record<FarmerNavKey, string>> = {

};
watch(
    () => props.activeKey,
    (key) => {
        selectedKey.value = key;
    },
);

function select(key: FarmerNavKey): void {
    if (key === 'ai') {
        router.visit('/bypass');
        return;
    }
    if (key === 'home' && window.location.pathname !== '/') {
        router.visit('/');
        return;
    }
    if (key === 'finance' && window.location.pathname !== '/finance') {
        router.visit('/finance');
        return;
    }

    if (key === 'activity' && window.location.pathname !== '/aktivitas') {
        router.visit('/aktivitas');
        return;
    }

    if (upcomingPages[key]) {
        toast.info(`Halaman ${upcomingPages[key]} sedang disiapkan.`);
        return;
    }

    selectedKey.value = key;

    if (key === "garden" && window.location.pathname !== "/kebun") {
        void router.visit("/kebun");
    }

    if (key === "finance" && window.location.pathname !== "/finance") {
        void router.visit("/finance");
    }

    emit("select", key);
}
</script>

<template>
    <div
        class="flex min-h-dvh w-full justify-center bg-card px-6 pt-6 text-foreground max-[480px]:px-0 max-[480px]:pt-0"
    >
        <section
            class="relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] bg-card text-card-foreground max-[480px]:h-dvh max-[480px]:max-w-none max-[480px]:rounded-none"
        >
            <main
                class="min-h-0 flex-1 scroll-pb-8 overflow-y-auto overscroll-y-contain pb-8"
            >
                <slot />
            </main>

            <FarmerBottomNav :active-key="selectedKey" @select="select" />
            <Toaster />
        </section>
    </div>
</template>

<style scoped>
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

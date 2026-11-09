<!-- Historical source: 73f2555d91d0fb2d6acb7bc70813921edd479ea4 / resources/js/components/farmer/FarmerMobileShell.vue -->
<script setup lang="ts">
import DashboardHeader from '@/reference/components/farmer/DashboardHeader.vue';
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/reference/components/farmer/FarmerBottomNav.vue';
import { ref, watch } from 'vue';

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

watch(
    () => props.activeKey,
    (key) => {
        selectedKey.value = key;
    },
);
function select(key: FarmerNavKey): void {
    selectedKey.value = key;
    emit('select', key);
}
</script>

<template>
    <div
        class="flex min-h-dvh w-full justify-center bg-card px-6 pt-6 text-foreground max-[480px]:px-0 max-[480px]:pt-0"
    >
        <section
            data-reference-shell
            data-farmer-shell
            class="relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[440px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] bg-card text-card-foreground max-[480px]:h-dvh max-[480px]:max-w-none max-[480px]:rounded-none"
        >
            <aside
                class="shrink-0 border-b border-border bg-muted px-4 py-2 text-xs leading-5"
                aria-label="Referensi historis hanya-baca"
            >
                <strong>Referensi historis · hanya-baca</strong>
                <p>
                    29 Oktober 2026 · Snapshot 73f2555 · Data contoh historis,
                    bukan data operasional.
                </p>
                <div class="flex items-center justify-between gap-2">
                    <a href="/" class="font-semibold text-secondary underline"
                        >Kembali ke aplikasi</a
                    >
                    <a
                        href="/referensi/laporan"
                        class="text-secondary underline"
                        >Laporan</a
                    >
                </div>
            </aside>
            <div
                v-if="props.activeKey !== 'ai'"
                class="shrink-0 bg-card px-4 pt-4 pb-2"
            >
                <DashboardHeader />
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

            <FarmerBottomNav :active-key="selectedKey" @select="select" />
        </section>
    </div>
</template>

<style scoped>
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

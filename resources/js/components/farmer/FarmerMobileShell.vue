<script setup lang="ts">
import FarmerBottomNav, {
    type FarmerNavKey,
} from '@/components/farmer/FarmerBottomNav.vue';
import { ref, watch } from 'vue';

type Props = {
    activeKey?: FarmerNavKey;
};

const props = withDefaults(defineProps<Props>(), {
    activeKey: 'ai',
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
        class="flex min-h-dvh w-full justify-center bg-black px-6 pt-6 text-foreground"
    >
        <section
            class="relative flex h-[min(874px,calc(100dvh-24px))] w-full max-w-[402px] flex-col overflow-hidden rounded-t-[24px] rounded-b-[20px] bg-card"
        >
            <main class="min-h-0 flex-1 overflow-y-auto pb-20">
                <slot />
            </main>

            <FarmerBottomNav :active-key="selectedKey" @select="select" />
        </section>
    </div>
</template>

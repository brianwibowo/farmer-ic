<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { activityTypeOptions, type ActivityType } from '@/data/activityLogs';

const selectedType = defineModel<'' | 'all' | ActivityType>({ default: '' });
const props = defineProps<{ stretch?: boolean }>();

const isFilterOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const selectedTypeLabel = computed(() => {
    if (selectedType.value === '' || selectedType.value === 'all') {
        return 'Aktivitas';
    }

    return (
        activityTypeOptions.find((opt) => opt.value === selectedType.value)
            ?.shortLabel ?? 'Aktivitas'
    );
});

function selectTypeOption(value: 'all' | ActivityType): void {
    selectedType.value = value;
    isFilterOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (
        isFilterOpen.value &&
        dropdownRef.value &&
        target &&
        !dropdownRef.value.contains(target)
    ) {
        isFilterOpen.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', handleDocumentClick));

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
});
</script>

<template>
    <div
        ref="dropdownRef"
        :class="[
            'relative',
            props.stretch ? 'min-w-0 w-full' : 'w-[132px] shrink-0',
        ]"
    >
        <button
            type="button"
            aria-label="Filter jenis aktivitas"
            :aria-expanded="isFilterOpen"
            aria-haspopup="listbox"
            class="flex h-[38px] w-full items-center justify-between gap-1 rounded-lg border border-primary bg-card px-3 text-[14px] text-foreground outline-none focus:ring-2 focus:ring-primary/30"
            @click="isFilterOpen = !isFilterOpen"
        >
            <span class="truncate">{{ selectedTypeLabel }}</span>
            <ChevronDown
                :size="16"
                :stroke-width="1.8"
                :class="[
                    'shrink-0 text-primary transition-transform duration-150',
                    isFilterOpen ? 'rotate-180' : '',
                ]"
                aria-hidden="true"
            />
        </button>

        <ul
            v-if="isFilterOpen"
            role="listbox"
            aria-label="Pilihan jenis aktivitas"
            class="absolute inset-x-0 top-[calc(100%+6px)] z-20 w-full rounded-xl border border-border bg-card p-1 shadow-md"
        >
            <li>
                <button
                    type="button"
                    role="option"
                    :aria-selected="
                        selectedType === '' || selectedType === 'all'
                    "
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                        selectedType === '' || selectedType === 'all'
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="selectTypeOption('all')"
                >
                    Semua
                </button>
            </li>
            <li v-for="option in activityTypeOptions" :key="option.value">
                <button
                    type="button"
                    role="option"
                    :aria-selected="selectedType === option.value"
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                        selectedType === option.value
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="selectTypeOption(option.value)"
                >
                    {{ option.shortLabel }}
                </button>
            </li>
        </ul>
    </div>
</template>

<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { Kebun } from '@/data/kebun';

const selectedKebun = defineModel<string>({ default: '' });
const props = defineProps<{ kebunList: Kebun[] }>();

const isListOpen = ref(false);
const listRef = ref<HTMLElement | null>(null);

const sortedKebun = computed(() =>
    [...props.kebunList].sort((a, b) => a.name.localeCompare(b.name, 'id')),
);

const selectedKebunItem = computed(() =>
    props.kebunList.find((item) => item.id === selectedKebun.value),
);

// The full name ("Kebun Sumber Makmur") does not fit the compact trigger, so
// only the distinctive part is shown there. The dropdown list keeps full names.
function shortKebunName(name: string): string {
    return name.replace(/^Kebun\s+/i, '');
}

const selectedLabel = computed(() =>
    selectedKebunItem.value
        ? shortKebunName(selectedKebunItem.value.name)
        : 'Kebun',
);

function selectKebun(id: string): void {
    selectedKebun.value = id;
    isListOpen.value = false;
}

function handleDocumentClick(event: MouseEvent): void {
    const target = event.target as Node | null;
    if (
        isListOpen.value &&
        listRef.value &&
        target &&
        !listRef.value.contains(target)
    ) {
        isListOpen.value = false;
    }
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') isListOpen.value = false;
}

onMounted(() => {
    document.addEventListener('pointerdown', handleDocumentClick);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleDocumentClick);
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div ref="listRef" class="relative w-full min-w-0">
        <button
            type="button"
            aria-label="Filter kebun"
            :aria-expanded="isListOpen"
            aria-haspopup="listbox"
            :title="selectedKebunItem?.name"
            class="flex h-[38px] w-full items-center justify-between gap-1 rounded-lg border border-primary bg-card px-3 text-[14px] text-foreground outline-none focus:ring-2 focus:ring-primary/30"
            @click="isListOpen = !isListOpen"
        >
            <span class="truncate">{{ selectedLabel }}</span>
            <ChevronDown
                :size="16"
                :stroke-width="1.8"
                :class="[
                    'shrink-0 text-primary transition-transform duration-150',
                    isListOpen ? 'rotate-180' : '',
                ]"
                aria-hidden="true"
            />
        </button>

        <ul
            v-if="isListOpen"
            role="listbox"
            aria-label="Pilihan kebun"
            class="absolute top-full right-0 z-30 mt-1.5 max-h-[228px] w-[208px] overflow-y-auto rounded-xl border border-border bg-card p-1 shadow-md"
        >
            <li>
                <button
                    type="button"
                    role="option"
                    :aria-selected="selectedKebun === ''"
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                        selectedKebun === ''
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="selectKebun('')"
                >
                    Semua
                </button>
            </li>
            <li v-for="kebun in sortedKebun" :key="kebun.id">
                <button
                    type="button"
                    role="option"
                    :aria-selected="selectedKebun === kebun.id"
                    :class="[
                        'flex w-full items-center rounded-lg px-3 py-2 text-left text-[13px] leading-4 transition-colors',
                        selectedKebun === kebun.id
                            ? 'bg-muted font-semibold text-secondary'
                            : 'text-foreground hover:bg-muted/70',
                    ]"
                    @click="selectKebun(kebun.id)"
                >
                    {{ kebun.name }}
                </button>
            </li>
        </ul>
    </div>
</template>

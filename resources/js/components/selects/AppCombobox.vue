<script setup lang="ts">
import { computed, onMounted, ref, useTemplateRef, watch } from 'vue';
import { Check, LoaderCircle, Search, X } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxViewport,
} from 'reka-ui';

defineOptions({ inheritAttrs: false });
export type ComboboxOption = {
    value: string;
    label: string;
    disabled?: boolean;
};
const props = withDefaults(
    defineProps<{
        modelValue: string;
        options: ComboboxOption[];
        label: string;
        placeholder?: string;
        disabled?: boolean;
        loading?: boolean;
        emptyMessage?: string;
    }>(),
    { emptyMessage: 'Belum ada pilihan.' },
);
const emit = defineEmits<{
    'update:modelValue': [value: string];
    draft: [unselected: boolean];
}>();
const container = useTemplateRef<HTMLElement>('container');
const open = ref(false);
const admin = ref(false);
const typing = ref(false);
const filtering = ref(false);
const selected = computed(() =>
    props.options.find((option) => option.value === props.modelValue),
);
const query = ref(selected.value?.label ?? '');
const results = computed(() =>
    filtering.value
        ? props.options.filter((option) =>
              option.label
                  .toLocaleLowerCase('id-ID')
                  .includes(query.value.trim().toLocaleLowerCase('id-ID')),
          )
        : props.options,
);

onMounted(() => {
    admin.value = !!container.value?.closest('.admin-dashboard-theme');
});
watch(selected, (option) => {
    if (!typing.value) query.value = option?.label ?? '';
});
watch(
    () => props.disabled || props.loading,
    (disabled) => {
        if (disabled) {
            open.value = false;
            typing.value = false;
            query.value = selected.value?.label ?? '';
        }
    },
);
function input(): void {
    typing.value = true;
    filtering.value = true;
    emit('draft', query.value.trim() !== '');
    emit('update:modelValue', '');
}
function choose(value: unknown): void {
    if (typeof value !== 'string') return;
    typing.value = false;
    filtering.value = false;
    query.value =
        props.options.find((option) => option.value === value)?.label ?? '';
    emit('draft', false);
    emit('update:modelValue', value);
}
function clear(): void {
    choose('');
    open.value = false;
}
</script>

<template>
    <div ref="container" class="min-w-0">
        <ComboboxRoot
            v-model:open="open"
            :model-value="modelValue"
            :disabled="disabled || loading"
            ignore-filter
            open-on-focus
            open-on-click
            :reset-search-term-on-blur="false"
            :reset-search-term-on-select="false"
            :reset-model-value-on-clear="false"
            class="relative min-w-0"
            @update:model-value="choose"
            @update:open="filtering = false"
        >
            <ComboboxAnchor class="relative">
                <Search
                    aria-hidden="true"
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <ComboboxInput
                    v-bind="$attrs"
                    v-model="query"
                    :placeholder="
                        placeholder ??
                        `Cari ${label.toLocaleLowerCase('id-ID')}`
                    "
                    :aria-busy="loading || undefined"
                    class="app-combobox h-10 w-full rounded-xl border border-border bg-card pr-11 pl-9 text-sm text-foreground outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-60 [@media(pointer:coarse)]:min-h-11!"
                    @input="input"
                />
                <LoaderCircle
                    v-if="loading"
                    aria-hidden="true"
                    class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin motion-reduce:animate-none"
                />
                <button
                    v-else-if="query || modelValue"
                    type="button"
                    :disabled="disabled"
                    :aria-label="`Kosongkan ${label}`"
                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-xl text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    @click="clear"
                >
                    <X aria-hidden="true" class="size-4" />
                </button>
            </ComboboxAnchor>
            <ComboboxPortal>
                <ComboboxContent
                    position="popper"
                    :side-offset="8"
                    :collision-padding="8"
                    :class="{ 'admin-dashboard-theme': admin }"
                    class="z-50 w-(--reka-combobox-trigger-width) max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground"
                    :aria-label="`Pilihan ${label.toLocaleLowerCase('id-ID')}`"
                >
                    <ComboboxViewport
                        class="max-h-[min(18rem,var(--reka-combobox-content-available-height))] overflow-y-auto overscroll-contain p-1"
                    >
                        <ComboboxItem
                            v-for="option in results"
                            :key="option.value"
                            :value="option.value"
                            :disabled="option.disabled"
                            class="relative flex min-h-11 cursor-pointer items-center rounded-lg py-3 pr-9 pl-3 text-sm break-words outline-none select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator class="absolute right-3"
                                ><Check aria-hidden="true" class="size-4"
                            /></ComboboxItemIndicator>
                        </ComboboxItem>
                        <p
                            v-if="!results.length"
                            role="status"
                            class="px-3 py-3 text-sm text-muted-foreground"
                        >
                            {{
                                options.length
                                    ? 'Tidak ada hasil. Coba nama lain.'
                                    : emptyMessage
                            }}
                        </p>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </div>
</template>

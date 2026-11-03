<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useTemplateRef,
    watch,
} from 'vue';
import { LoaderCircle, Search, X } from '@lucide/vue';
import FarmField from '@/components/farms/FarmField.vue';
import type { RegionReferenceOption } from '@/types/farms';

const props = defineProps<{
    id: string;
    label: string;
    modelValue: string;
    options: RegionReferenceOption[];
    loading: boolean;
    failure: string;
    disabled: boolean;
    hint: string;
    error?: string;
}>();
const emit = defineEmits<{
    'update:modelValue': [code: string];
    draft: [unselected: boolean];
    retry: [];
}>();
const selectedName = computed(
    () =>
        props.options.find((option) => option.code === props.modelValue)
            ?.name ?? '',
);
const query = ref(selectedName.value);
const typing = ref(false);
const filtering = ref(false);
const open = ref(false);
const active = ref(-1);
const control = useTemplateRef<HTMLElement>('control');
const placement = ref<'top' | 'bottom'>('bottom');
const maximumHeight = ref(288);
function positionList(): void {
    if (!open.value || !control.value) return;
    const bounds = control.value.getBoundingClientRect();
    const viewport = window.visualViewport;
    const top = viewport?.offsetTop ?? 0;
    const bottom = top + (viewport?.height ?? window.innerHeight);
    const below = Math.max(0, bottom - bounds.bottom - 12);
    const above = Math.max(0, bounds.top - top - 12);
    const desired = Math.min(288, Math.max(44, results.value.length * 44 + 8));
    placement.value = below < desired && above > below ? 'top' : 'bottom';
    maximumHeight.value = Math.floor(
        Math.min(288, placement.value === 'top' ? above : below),
    );
}
const unavailable = computed(
    () => props.disabled || props.loading || !!props.failure,
);
const results = computed(() =>
    filtering.value
        ? props.options.filter((option) =>
              option.name
                  .toLocaleLowerCase('id-ID')
                  .includes(query.value.trim().toLocaleLowerCase('id-ID')),
          )
        : props.options,
);
watch([open, () => results.value.length], async () => {
    await nextTick();
    positionList();
});
onMounted(() => {
    window.addEventListener('resize', positionList);
    window.addEventListener('scroll', positionList, true);
    window.visualViewport?.addEventListener('resize', positionList);
    window.visualViewport?.addEventListener('scroll', positionList);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', positionList);
    window.removeEventListener('scroll', positionList, true);
    window.visualViewport?.removeEventListener('resize', positionList);
    window.visualViewport?.removeEventListener('scroll', positionList);
});
watch(selectedName, (value) => {
    if (!typing.value) query.value = value;
});
watch(active, async (index) => {
    if (index < 0) return;
    await nextTick();
    document
        .getElementById(`${props.id}-option-${index}`)
        ?.scrollIntoView({ block: 'nearest' });
});
function input(): void {
    typing.value = true;
    filtering.value = true;
    active.value = -1;
    open.value = true;
    emit('draft', query.value.trim() !== '');
    emit('update:modelValue', '');
}
function openList(): void {
    open.value = true;
    filtering.value = false;
    active.value = -1;
}
function choose(option: RegionReferenceOption): void {
    typing.value = false;
    query.value = option.name;
    open.value = false;
    active.value = -1;
    emit('draft', false);
    emit('update:modelValue', option.code);
}
function clear(): void {
    typing.value = false;
    query.value = '';
    open.value = false;
    emit('draft', false);
    emit('update:modelValue', '');
}
function keydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') open.value = false;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        open.value = true;
        active.value =
            event.key === 'ArrowDown'
                ? Math.min(active.value + 1, results.value.length - 1)
                : Math.max(0, active.value - 1);
    } else if (
        event.key === 'Enter' &&
        open.value &&
        results.value[active.value]
    ) {
        event.preventDefault();
        choose(results.value[active.value]);
    }
}
</script>

<template>
    <FarmField
        :id="id"
        :label="label"
        :hint="hint"
        :error="error"
        v-slot="field"
    >
        <div
            ref="control"
            class="relative"
            @focusout="
                (event) => {
                    if (
                        !(event.currentTarget as HTMLElement).contains(
                            event.relatedTarget as Node,
                        )
                    )
                        open = false;
                }
            "
        >
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    :id="field.id"
                    v-model="query"
                    class="admin-field pr-11! pl-9!"
                    autocomplete="off"
                    maxlength="100"
                    :placeholder="
                        disabled
                            ? hint
                            : `Cari ${label.toLocaleLowerCase('id-ID')}`
                    "
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="open && !unavailable"
                    :aria-controls="`${id}-results`"
                    :aria-activedescendant="
                        results[active] && open && !unavailable
                            ? `${id}-option-${active}`
                            : undefined
                    "
                    :aria-invalid="field.invalid"
                    :aria-describedby="
                        [field.description, failure ? `${id}-failure` : null]
                            .filter(Boolean)
                            .join(' ') || undefined
                    "
                    :disabled="unavailable"
                    :aria-busy="loading"
                    @input="input"
                    @focus="openList"
                    @click="openList"
                    @keydown="keydown"
                />
                <LoaderCircle
                    v-if="loading"
                    class="absolute top-3 right-3 size-4 animate-spin text-muted-foreground"
                    aria-hidden="true"
                />
                <button
                    v-else-if="query || modelValue"
                    type="button"
                    class="absolute top-0 right-0 flex h-full w-11 items-center justify-center rounded-xl text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    :aria-label="`Kosongkan ${label}`"
                    :disabled="unavailable"
                    @click="clear"
                >
                    <X class="size-4" />
                </button>
            </div>
            <ul
                v-if="open && !unavailable"
                :id="`${id}-results`"
                role="listbox"
                :aria-label="`Pilihan ${label.toLocaleLowerCase('id-ID')}`"
                class="absolute inset-x-0 z-50 overflow-y-auto rounded-xl border border-border bg-popover p-1 text-popover-foreground"
                :class="
                    placement === 'top' ? 'bottom-full mb-2' : 'top-full mt-2'
                "
                :style="{ maxHeight: maximumHeight + 'px' }"
            >
                <li
                    v-for="(option, index) in results"
                    :key="option.code"
                    role="presentation"
                >
                    <button
                        :id="`${id}-option-${index}`"
                        type="button"
                        role="option"
                        tabindex="-1"
                        :aria-selected="modelValue === option.code"
                        @mousedown.prevent
                        @click="choose(option)"
                        @mousemove="active = index"
                        class="w-full cursor-pointer rounded-lg px-3 py-3 text-left text-sm"
                        :class="
                            active === index
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-accent'
                        "
                    >
                        {{ option.name }}
                    </button>
                </li>
                <li
                    v-if="!results.length"
                    role="presentation"
                    class="px-3 py-3 text-sm text-muted-foreground"
                >
                    {{
                        options.length
                            ? 'Tidak ada hasil. Coba nama lain.'
                            : 'Belum ada pilihan wilayah untuk induk ini.'
                    }}
                </li>
            </ul>
        </div>
        <p v-if="loading" role="status" class="text-xs text-muted-foreground">
            Memuat pilihan {{ label.toLocaleLowerCase('id-ID') }}…
        </p>
        <p
            v-if="failure"
            :id="`${id}-failure`"
            role="alert"
            class="text-xs leading-5 text-error"
        >
            {{ failure }}
            <button
                type="button"
                class="rounded underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring"
                :disabled="disabled || loading"
                @click="emit('retry')"
            >
                Coba lagi
            </button>
        </p>
    </FarmField>
</template>

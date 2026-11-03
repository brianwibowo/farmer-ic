<script setup lang="ts" generic="T extends SelectValue">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    useAttrs,
    useId,
    useTemplateRef,
    watch,
} from 'vue';
import { LoaderCircle } from '@lucide/vue';
import {
    Select,
    SelectContent,
    SelectTrigger,
    SelectValue as SelectedLabel,
} from '@/components/ui/select';
import { selectOptionValue, type SelectValue } from './selectValue';

defineOptions({ inheritAttrs: false });
const props = withDefaults(
    defineProps<{
        modelValue?: T;
        modelModifiers?: { number?: boolean; trim?: boolean };
        placeholder?: string;
        name?: string;
        required?: boolean;
        disabled?: boolean;
        loading?: boolean;
    }>(),
    { placeholder: 'Pilih opsi' },
);
const emit = defineEmits<{
    'update:modelValue': [value: T];
    change: [value: T];
}>();
const trigger = useTemplateRef<InstanceType<typeof SelectTrigger>>('trigger');
const native = useTemplateRef<HTMLSelectElement>('native');
const open = ref(false);
const admin = ref(false);
const attrs = useAttrs();
const validationId = `select-validation-${useId()}`;
const invalid = ref(false);
const describedBy = computed(
    () =>
        [attrs['aria-describedby'], invalid.value ? validationId : undefined]
            .filter(Boolean)
            .join(' ') || undefined,
);
const selected = computed(() => selectOptionValue(props.modelValue));
const nativeValue = computed(() =>
    props.modelValue == null ? '' : String(props.modelValue),
);
const initialValue = props.modelValue;
let form: HTMLFormElement | null = null;

function focus(): void {
    (trigger.value?.$el as HTMLElement | undefined)?.focus();
}
function rejectEmpty(): void {
    invalid.value = true;
    focus();
}
async function update(value: unknown): Promise<void> {
    if (!value || typeof value !== 'object' || !('value' in value)) return;
    let decoded = value.value as SelectValue;
    if (typeof decoded === 'string') {
        if (props.modelModifiers?.trim) decoded = decoded.trim();
        if (
            props.modelModifiers?.number &&
            decoded !== '' &&
            !Number.isNaN(Number(decoded))
        )
            decoded = Number(decoded);
    }
    emit('update:modelValue', decoded as T);
    await nextTick();
    emit('change', decoded as T);
}
function reset(): void {
    void nextTick(() => {
        emit('update:modelValue', initialValue as T);
    });
}
onMounted(() => {
    admin.value = !!(trigger.value?.$el as HTMLElement | undefined)?.closest(
        '.admin-dashboard-theme',
    );
    form = native.value?.form ?? null;
    form?.addEventListener('reset', reset);
});
onBeforeUnmount(() => form?.removeEventListener('reset', reset));
watch(
    () => props.disabled || props.loading,
    (disabled) => {
        if (disabled) open.value = false;
    },
);
watch(
    () => props.modelValue,
    () => {
        invalid.value = false;
    },
);
defineExpose({ focus });
</script>

<template>
    <Select
        v-model:open="open"
        :model-value="selected"
        by="key"
        :required="required"
        :disabled="disabled || loading"
        @update:model-value="update"
    >
        <SelectTrigger
            ref="trigger"
            v-bind="$attrs"
            :aria-invalid="invalid || attrs['aria-invalid'] || undefined"
            :aria-describedby="describedBy"
            :aria-required="required || undefined"
            :aria-busy="loading || undefined"
            class="app-select flex! h-10 min-h-10 w-full min-w-0 rounded-xl bg-card text-foreground shadow-none [@media(pointer:coarse)]:min-h-11!"
        >
            <SelectedLabel
                :placeholder="placeholder"
                class="min-w-0 flex-1 truncate text-left"
            />
            <LoaderCircle
                v-if="loading"
                aria-hidden="true"
                class="size-4 animate-spin motion-reduce:animate-none"
            />
        </SelectTrigger>
        <SelectContent
            :class="{ 'admin-dashboard-theme': admin }"
            class="app-select-content w-(--reka-select-trigger-width) max-w-[calc(100vw-1rem)] rounded-xl border-border shadow-none motion-reduce:animate-none"
            :collision-padding="8"
            :side-offset="4"
        >
            <slot />
        </SelectContent>
    </Select>
    <!-- Preserve native required validation and FormData without encoded values. -->
    <select
        ref="native"
        :name="name"
        :value="nativeValue"
        :required="required"
        :disabled="disabled || loading"
        tabindex="-1"
        aria-hidden="true"
        class="pointer-events-none absolute size-px opacity-0"
        @invalid.prevent="rejectEmpty"
    >
        <option :value="nativeValue">{{ nativeValue }}</option>
    </select>
    <p
        v-if="invalid"
        :id="validationId"
        role="alert"
        class="mt-1 text-xs text-error"
    >
        Pilih salah satu opsi.
    </p>
</template>

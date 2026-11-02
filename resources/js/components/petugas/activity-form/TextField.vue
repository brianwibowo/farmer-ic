<script setup lang="ts">
type InputMode =
    | 'decimal'
    | 'email'
    | 'none'
    | 'numeric'
    | 'search'
    | 'tel'
    | 'text'
    | 'url';

withDefaults(
    defineProps<{
        modelValue?: string;
        label: string;
        optional?: boolean;
        placeholder?: string;
        inputmode?: InputMode;
    }>(),
    { modelValue: '', optional: false, placeholder: '', inputmode: 'text' },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <label class="block space-y-1.5">
        <span class="petugas-label"
            >{{ label }} <span v-if="optional">(opsional)</span
            ><em v-else>*</em></span
        >
        <input
            :value="modelValue"
            :inputmode="inputmode"
            type="text"
            class="petugas-field"
            :placeholder="placeholder"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLInputElement).value,
                )
            "
        />
    </label>
</template>

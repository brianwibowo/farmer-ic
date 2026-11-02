<script setup lang="ts">
withDefaults(
    defineProps<{
        modelValue?: string;
        label: string;
        optional?: boolean;
        required?: boolean;
    }>(),
    { modelValue: '', optional: false, required: false },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <label class="block space-y-1.5">
        <span class="petugas-label"
            >{{ label }} <span v-if="optional">(opsional)</span
            ><em v-else-if="required">*</em></span
        >
        <textarea
            :value="modelValue"
            rows="3"
            class="petugas-field resize-y"
            @input="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLTextAreaElement).value,
                )
            "
        />
    </label>
</template>

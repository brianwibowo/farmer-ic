<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
withDefaults(
    defineProps<{
        modelValue?: string;
        label: string;
        options: Array<[string, string]>;
        optional?: boolean;
    }>(),
    { modelValue: '', optional: false },
);

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <label class="block space-y-1.5">
        <span class="petugas-label"
            >{{ label }} <span v-if="optional">(opsional)</span
            ><em v-else>*</em></span
        >
        <AppSelect
            :model-value="modelValue"
            class="petugas-field"
            @update:model-value="$emit('update:modelValue', $event)"
        >
            <AppSelectOption value="" :disabled="!optional"
                >Pilih opsi</AppSelectOption
            >
            <AppSelectOption
                v-for="option in options"
                :key="option[0]"
                :value="option[0]"
            >
                {{ option[1] }}
            </AppSelectOption>
        </AppSelect>
    </label>
</template>

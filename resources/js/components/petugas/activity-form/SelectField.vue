<script setup lang="ts">
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
        <select
            :value="modelValue"
            class="petugas-field"
            @change="
                $emit(
                    'update:modelValue',
                    ($event.target as HTMLSelectElement).value,
                )
            "
        >
            <option value="" :disabled="!optional">Pilih opsi</option>
            <option
                v-for="option in options"
                :key="option[0]"
                :value="option[0]"
            >
                {{ option[1] }}
            </option>
        </select>
    </label>
</template>

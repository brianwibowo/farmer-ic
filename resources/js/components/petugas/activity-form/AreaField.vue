<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
withDefaults(
    defineProps<{
        value?: string;
        unit?: string;
        optional?: boolean;
    }>(),
    { value: '', unit: 'm2', optional: false },
);

defineEmits<{
    'update:value': [value: string];
    'update:unit': [value: string];
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-3">
        <label class="space-y-1.5">
            <span class="petugas-label"
                >Luas area <em v-if="!optional">*</em></span
            >
            <input
                :value="value"
                inputmode="decimal"
                type="text"
                class="petugas-field"
                placeholder="0"
                @input="
                    $emit(
                        'update:value',
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
        </label>
        <label class="space-y-1.5">
            <span class="petugas-label">Satuan</span>
            <AppSelect
                :model-value="unit"
                class="petugas-field"
                @update:model-value="$emit('update:unit', $event)"
            >
                <AppSelectOption value="m2">m²</AppSelectOption>
                <AppSelectOption value="ha">Ha</AppSelectOption>
            </AppSelect>
        </label>
    </div>
</template>

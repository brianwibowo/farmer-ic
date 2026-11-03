<script setup lang="ts">
import FarmField from '@/components/farms/FarmField.vue';
import AppCombobox from '@/components/selects/AppCombobox.vue';
import type { RegionReferenceOption } from '@/types/farms';

defineProps<{
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
</script>

<template>
    <FarmField
        :id="id"
        :label="label"
        :hint="hint"
        :error="error"
        v-slot="field"
    >
        <AppCombobox
            :id="field.id"
            :model-value="modelValue"
            :options="
                options.map((option) => ({
                    value: option.code,
                    label: option.name,
                }))
            "
            :label="label"
            :loading="loading"
            :disabled="disabled || !!failure"
            :placeholder="
                disabled ? hint : `Cari ${label.toLocaleLowerCase('id-ID')}`
            "
            :aria-invalid="field.invalid"
            :aria-describedby="
                [field.description, failure ? `${id}-failure` : null]
                    .filter(Boolean)
                    .join(' ') || undefined
            "
            class="admin-field pr-11! pl-9!"
            empty-message="Belum ada pilihan wilayah untuk induk ini."
            @update:model-value="emit('update:modelValue', $event)"
            @draft="emit('draft', $event)"
        />
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

<script setup lang="ts">
import { computed } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { useFarmCycles } from '@/composables/useFarmCycles';

defineProps<{ id: string; error?: string; disabled?: boolean }>();
const selectedId = defineModel<string | null>({ required: true });
const create = defineModel<boolean>('create', { required: true });
const { cycles, canCreate } = useFarmCycles();
const selection = computed({
    get: () => (create.value ? 'new' : (selectedId.value ?? '')),
    set: (value: string) => {
        create.value = value === 'new';
        selectedId.value = value && value !== 'new' ? value : null;
    },
});
const selected = computed(() =>
    cycles.value.find((c) => c.id === selectedId.value),
);
const number = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
</script>

<template>
    <div class="space-y-2">
        <label :for="id" class="block text-sm font-medium"
            >Siklus lahan <span class="text-error">*</span></label
        >
        <AppSelect
            :id="id"
            v-model="selection"
            class="admin-field min-h-11 w-full"
            placeholder="Pilih siklus lahan"
            required
            :disabled="disabled"
            :aria-invalid="!!error"
            :aria-describedby="`${id}-hint${error ? ` ${id}-error` : ''}`"
        >
            <AppSelectOption
                v-for="cycle in cycles"
                :key="cycle.id"
                :value="cycle.id"
            >
                Siklus {{ cycle.cycle_number }} · {{ cycle.batch_count }} batch
            </AppSelectOption>
            <AppSelectOption v-if="canCreate" value="new"
                >+ Buat siklus baru</AppSelectOption
            >
        </AppSelect>
        <p :id="`${id}-hint`" class="text-xs leading-5 text-muted-foreground">
            <template v-if="create"
                >Siklus baru dibuat saat subblok disimpan. Nomornya diatur
                otomatis.</template
            >
            <template v-else-if="selected"
                >Panen aktual siklus ini:
                {{
                    number.format(Number(selected.harvested_ton))
                }}
                ton.</template
            >
            <template v-else>Belum ada siklus yang dapat dipilih.</template>
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="text-xs text-error"
        >
            {{ error }}
        </p>
    </div>
</template>

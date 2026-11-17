<script setup lang="ts">
import InertiaPagination from '@/components/InertiaPagination.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { LogbookPage } from '@/types/logbook';
defineProps<{
    page: Omit<LogbookPage<unknown>, 'data'>;
    loading?: boolean;
    label: string;
}>();
const size = defineModel<number>('size', { required: true });
defineEmits<{ change: [] }>();
</script>
<template>
    <footer
        class="flex flex-wrap items-center justify-between gap-3 border-t border-border p-4 md:px-5"
    >
        <p class="text-xs text-muted-foreground" role="status">
            {{
                page.total
                    ? 'Menampilkan ' +
                      page.from +
                      '–' +
                      page.to +
                      ' dari ' +
                      page.total
                    : '0'
            }}
            {{ label }}
        </p>
        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
            <div class="w-36">
                <AppSelect
                    v-model="size"
                    class="admin-field"
                    :disabled="loading"
                    :aria-label="'Jumlah ' + label + ' per halaman'"
                    @change="$emit('change')"
                    ><AppSelectOption :value="15">15 / halaman</AppSelectOption
                    ><AppSelectOption :value="30">30 / halaman</AppSelectOption
                    ><AppSelectOption :value="50"
                        >50 / halaman</AppSelectOption
                    ></AppSelect
                >
            </div>
            <InertiaPagination
                :paginator="page"
                :label="'Pagination ' + label"
                class="ml-auto justify-end"
                :disabled="loading"
            />
        </div>
    </footer>
</template>

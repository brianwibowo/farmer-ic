<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
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
        <div class="flex items-center gap-2">
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
            <nav
                class="flex items-center gap-1"
                :aria-label="'Pagination ' + label"
            >
                <Button
                    v-if="page.prev_page_url && !loading"
                    as-child
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Halaman sebelumnya"
                    ><Link :href="page.prev_page_url" preserve-scroll
                        ><ChevronLeft /></Link></Button
                ><Button
                    v-else
                    variant="ghost"
                    size="icon-sm"
                    disabled
                    aria-label="Halaman sebelumnya"
                    ><ChevronLeft
                /></Button>
                <span
                    aria-current="page"
                    class="px-1 text-xs font-semibold tabular-nums"
                    >{{ page.current_page }} / {{ page.last_page }}</span
                >
                <Button
                    v-if="page.next_page_url && !loading"
                    as-child
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Halaman berikutnya"
                    ><Link :href="page.next_page_url" preserve-scroll
                        ><ChevronRight /></Link></Button
                ><Button
                    v-else
                    variant="ghost"
                    size="icon-sm"
                    disabled
                    aria-label="Halaman berikutnya"
                    ><ChevronRight
                /></Button>
            </nav>
        </div>
    </footer>
</template>

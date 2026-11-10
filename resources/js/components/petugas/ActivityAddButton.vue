<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, ChevronRight, Sprout } from '@lucide/vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog';
defineProps<{
    categories: {
        id: string;
        code: string;
        name: string;
        group?: string | null;
    }[];
}>();
const open = ref(false);
</script>

<template>
    <Dialog v-if="categories.length" v-model:open="open">
        <DialogTrigger as-child>
            <button
                type="button"
                aria-label="Tambah laporan aktivitas mandiri"
                class="absolute right-4 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-20 flex size-14 items-center justify-center rounded-full bg-secondary text-secondary-foreground shadow-lg transition-colors hover:bg-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <Plus :size="26" aria-hidden="true" />
            </button>
        </DialogTrigger>
        <DialogContent class="max-h-[80dvh] overflow-y-auto rounded-2xl">
            <DialogHeader
                ><DialogTitle>Tambah aktivitas mandiri</DialogTitle
                ><DialogDescription
                    >Catat pekerjaan di luar to-do SOP. Pilih jenis pekerjaan
                    yang dilakukan.</DialogDescription
                ></DialogHeader
            >
            <div class="space-y-2">
                <template
                    v-for="(category, index) in categories"
                    :key="category.id"
                >
                    <h3
                        v-if="
                            category.group &&
                            category.group !== categories[index - 1]?.group
                        "
                        class="pt-3 pb-1 text-xs font-semibold text-muted-foreground"
                    >
                        {{ category.group }}
                    </h3>
                    <Link
                        :href="
                            category.code === 'planting'
                                ? '/petugas/penanaman'
                                : `/petugas/aktivitas/mandiri/${category.code}`
                        "
                        class="flex min-h-12 items-center gap-3 rounded-xl border border-border bg-card p-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                        @click="open = false"
                    >
                        <Sprout
                            class="size-5 shrink-0 text-primary"
                            aria-hidden="true"
                        /><span class="flex-1">{{ category.name }}</span
                        ><ChevronRight
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </Link>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>

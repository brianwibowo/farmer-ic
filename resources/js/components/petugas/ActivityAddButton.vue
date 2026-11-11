<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus, ChevronRight, Sprout } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PetugasActivityCategory } from '@/types/petugas-activity';

type CategoryEntry = {
    key: string;
    name: string | null;
    items: PetugasActivityCategory[];
};

const props = defineProps<{
    categories: PetugasActivityCategory[];
}>();
const open = ref(false);
const entries = computed<CategoryEntry[]>(() => {
    const result: CategoryEntry[] = [];

    for (const category of props.categories) {
        if (!category.group) {
            result.push({
                key: `category:${category.id}`,
                name: null,
                items: [category],
            });
            continue;
        }

        const previous = result.at(-1);
        if (previous?.name === category.group) {
            previous.items.push(category);

            continue;
        }

        result.push({
            key: `group:${result.length}:${category.group}`,
            name: category.group,
            items: [category],
        });
    }

    return result;
});
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
        <DialogContent
            class="flex max-h-[80dvh] flex-col gap-4 overflow-hidden rounded-2xl"
        >
            <DialogHeader class="shrink-0"
                ><DialogTitle>Tambah aktivitas mandiri</DialogTitle
                ><DialogDescription
                    >Catat pekerjaan di luar to-do SOP. Pilih jenis pekerjaan
                    yang dilakukan.</DialogDescription
                ></DialogHeader
            >
            <div
                class="-mx-6 -mb-6 min-h-0 overflow-y-auto px-6 pb-6"
                role="region"
                aria-label="Jenis aktivitas"
            >
                <div class="space-y-3">
                    <section
                        v-for="(entry, index) in entries"
                        :key="entry.key"
                        class="space-y-2"
                        :class="
                            index > 0 && !entry.name && entries[index - 1]?.name
                                ? 'border-t border-border pt-3'
                                : ''
                        "
                    >
                        <h3
                            v-if="entry.name"
                            class="sticky top-0 z-10 -mx-6 bg-background px-6 py-2 text-xs font-semibold text-muted-foreground"
                        >
                            {{ entry.name }}
                        </h3>
                        <div
                            :class="
                                entry.name
                                    ? 'space-y-2 border-l-2 border-primary/25 pl-3'
                                    : ''
                            "
                        >
                            <Link
                                v-for="category in entry.items"
                                :key="category.id"
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
                        </div>
                    </section>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

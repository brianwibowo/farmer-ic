<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Recycle } from '@lucide/vue';
import FarmCycleCard from './FarmCycleCard.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import type { FarmCyclesPageProps } from '@/types/farm-cycles';
import { paginationUrl } from '@/lib/pagination';

defineProps<FarmCyclesPageProps>();
const page = usePage();
</script>

<template>
    <div class="farm-cycles-page space-y-5">
        <header class="flex items-center gap-2">
            <Link
                :href="backUrl"
                class="group inline-flex size-11 shrink-0 items-center justify-center rounded-full text-primary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                aria-label="Kembali ke lahan"
            >
                <span
                    class="inline-flex size-9 items-center justify-center rounded-full bg-primary transition-colors group-hover:bg-primary/90"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                </span>
            </Link>
            <h1 class="text-lg! leading-7! font-medium!">Detail cycle</h1>
        </header>
        <div
            v-if="!cycles.total"
            class="rounded-[20px] bg-card px-5 py-10 text-center"
            role="status"
        >
            <Recycle
                class="mx-auto mb-3 size-8 text-primary"
                aria-hidden="true"
            />
            <p class="text-sm font-semibold">Belum ada siklus lahan</p>
        </div>
        <div
            v-else-if="!cycles.data.length"
            class="rounded-[20px] bg-card px-5 py-8 text-center"
            role="status"
        >
            <p class="text-sm font-medium">
                Tidak ada siklus pada halaman ini.
            </p>
            <Link
                :href="paginationUrl(page.url, 1)"
                class="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4"
                >Kembali ke halaman pertama</Link
            >
        </div>
        <ul
            v-else
            class="farm-cycles-grid grid gap-4"
            aria-label="Seluruh siklus lahan"
        >
            <li v-for="cycle in cycles.data" :key="cycle.id" class="min-w-0">
                <FarmCycleCard :cycle="cycle" />
            </li>
        </ul>
        <footer
            v-if="cycles.total"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-xs text-muted-foreground">
                {{ cycles.from ?? 0 }}–{{ cycles.to ?? 0 }} dari
                {{ cycles.total }} siklus
            </p>
            <InertiaPagination
                v-if="cycles.last_page > 1"
                :paginator="cycles"
                class="ml-auto"
                label="Pagination siklus lahan"
            />
        </footer>
    </div>
</template>

<style scoped>
.farm-cycles-page {
    container-type: inline-size;
}
@container (min-width: 40rem) {
    .farm-cycles-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@container (min-width: 64rem) {
    .farm-cycles-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
</style>

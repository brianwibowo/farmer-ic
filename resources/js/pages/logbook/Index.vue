<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    BookOpen,
    LandPlot,
    LoaderCircle,
    Search,
    ArrowUpRight,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import LogbookStats from '@/components/logbook/LogbookStats.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import {
    farmLocation,
    logbookDate,
    type LogbookFarm,
    type LogbookPage,
    type LogbookSummary,
} from '@/types/logbook';
defineOptions({ inheritAttrs: false });
type FarmRow = LogbookFarm & {
    log_count: number;
    latest_work_date: string | null;
};
const props = defineProps<{
    farms: LogbookPage<FarmRow>;
    summary: LogbookSummary;
    filters: { search?: string; per_page?: number };
    team: { id: number; name: string };
}>();
const filters = useForm({
    search: props.filters.search ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});
const hasSearch = computed(() => !!props.filters.search);
function apply(): void {
    filters.get('/admin/logbook', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function reset(): void {
    filters.search = '';
    apply();
}
</script>
<template>
    <Head title="Logbook" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <LogbookStats :summary="summary" directory />
        <section
            class="admin-card overflow-hidden rounded-xl bg-card"
            aria-labelledby="logbook-farms-title"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 p-4 md:p-5"
            >
                <div class="flex items-center gap-2">
                    <h2 id="logbook-farms-title" class="admin-section-title">
                        Logbook per kebun
                    </h2>
                    <span
                        class="rounded-full bg-accent px-2.5 py-1 text-xs font-bold text-accent-foreground"
                        >{{ farms.total }}</span
                    >
                </div>
                <p class="text-xs text-muted-foreground">
                    Ringkasan mencakup seluruh kebun yang dapat diakses
                </p>
            </header>
            <form
                class="flex flex-wrap items-start gap-2 px-4 pb-4 md:px-5"
                @submit.prevent="apply"
            >
                <div class="min-w-0 grow basis-52">
                    <label class="sr-only" for="logbook-farm-search"
                        >Cari kebun</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        /><input
                            id="logbook-farm-search"
                            v-model="filters.search"
                            type="search"
                            maxlength="150"
                            placeholder="Cari nama atau lokasi kebun…"
                            class="admin-field pl-10!"
                            :disabled="filters.processing"
                            :aria-invalid="!!filters.errors.search"
                            :aria-describedby="
                                filters.errors.search
                                    ? 'logbook-farm-search-error'
                                    : undefined
                            "
                        />
                    </div>
                    <p
                        v-if="filters.errors.search"
                        id="logbook-farm-search-error"
                        role="alert"
                        class="mt-1 text-xs text-error"
                    >
                        {{ filters.errors.search }}
                    </p>
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    :disabled="filters.processing"
                    ><LoaderCircle
                        v-if="filters.processing"
                        class="animate-spin motion-reduce:animate-none"
                    /><Search v-else />Cari</Button
                ><Button
                    v-if="hasSearch"
                    variant="ghost"
                    :disabled="filters.processing"
                    @click="reset"
                    >Reset</Button
                >
            </form>
            <div v-if="filters.processing" class="space-y-3 p-5" role="status">
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-16 animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat logbook kebun…
                </p>
            </div>
            <div v-else-if="!farms.data.length" class="px-5 py-16 text-center">
                <BookOpen class="mx-auto size-9 text-primary" />
                <h3 class="mt-4 font-semibold">
                    {{
                        hasSearch ? 'Kebun tidak ditemukan' : 'Belum ada kebun'
                    }}
                </h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        hasSearch
                            ? 'Coba nama atau lokasi kebun yang berbeda.'
                            : 'Kebun yang dapat Anda akses akan muncul di sini.'
                    }}
                </p>
                <Button
                    v-if="hasSearch"
                    class="mt-5"
                    variant="outline"
                    @click="reset"
                    >Reset pencarian</Button
                >
            </div>
            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel logbook per kebun"
                    tabindex="0"
                >
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">Kebun</th>
                                <th scope="col" class="px-5 py-3">Lokasi</th>
                                <th scope="col" class="px-5 py-3">
                                    Jumlah logbook
                                </th>
                                <th scope="col" class="px-5 py-3">
                                    Laporan terakhir
                                </th>
                                <th scope="col" class="px-5 py-3 text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="farm in farms.data"
                                :key="farm.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-5 py-5">
                                    <Link
                                        :href="'/admin/logbook/' + farm.id"
                                        class="flex items-center gap-3 font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                        ><span
                                            class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                                            ><LandPlot class="size-5" /></span
                                        >{{ farm.name }}</Link
                                    >
                                </td>
                                <td
                                    class="max-w-60 px-5 py-5 text-xs leading-6 text-muted-foreground"
                                >
                                    {{ farmLocation(farm) }}
                                </td>
                                <td
                                    class="px-5 py-5 text-lg font-bold tabular-nums"
                                >
                                    {{ farm.log_count }}
                                </td>
                                <td
                                    class="px-5 py-5 text-xs text-muted-foreground"
                                >
                                    {{ logbookDate(farm.latest_work_date) }}
                                </td>
                                <td class="px-5 py-5 text-right">
                                    <Button as-child variant="outline" size="sm"
                                        ><Link
                                            :href="'/admin/logbook/' + farm.id"
                                            :aria-label="
                                                'Buka logbook ' + farm.name
                                            "
                                            >Buka logbook<ArrowUpRight /></Link
                                    ></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    class="divide-y divide-border md:hidden"
                    aria-label="Logbook per kebun"
                >
                    <li
                        v-for="farm in farms.data"
                        :key="farm.id"
                        class="space-y-3 p-4"
                    >
                        <Link
                            :href="'/admin/logbook/' + farm.id"
                            class="flex items-start justify-between gap-3 font-semibold"
                            ><span>{{ farm.name }}</span
                            ><ArrowUpRight class="size-4 shrink-0 text-primary"
                        /></Link>
                        <p class="text-xs leading-5 text-muted-foreground">
                            {{ farmLocation(farm) }}
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="text-sm">
                                <strong class="text-lg tabular-nums">{{
                                    farm.log_count
                                }}</strong>
                                logbook
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ logbookDate(farm.latest_work_date) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </template>
            <LogbookPagination
                v-model:size="filters.per_page"
                :page="farms"
                label="kebun"
                :loading="filters.processing"
                @change="apply"
            />
        </section>
    </div>
</template>

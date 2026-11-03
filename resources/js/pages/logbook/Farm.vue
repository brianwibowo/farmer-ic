<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpRight,
    BookOpen,
    LoaderCircle,
    MapPin,
    Search,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import FarmField from '@/components/farms/FarmField.vue';
import LogbookStats from '@/components/logbook/LogbookStats.vue';
import LogbookPagination from '@/components/logbook/LogbookPagination.vue';
import {
    logbookDate,
    farmLocation,
    type LogbookFarm,
    type LogbookPage,
    type LogbookRow,
    type LogbookSummary,
} from '@/types/logbook';
defineOptions({ inheritAttrs: false });
const props = defineProps<{
    farm: LogbookFarm;
    logs: LogbookPage<LogbookRow>;
    summary: LogbookSummary;
    filters: {
        from_date?: string;
        to_date?: string;
        recorded_by?: string;
        per_page?: number;
    };
    recorders: { id: string; name: string }[];
}>();
const filters = useForm({
    from_date: props.filters.from_date ?? '',
    to_date: props.filters.to_date ?? '',
    recorded_by: props.filters.recorded_by ?? '',
    per_page: Number(props.filters.per_page ?? 15),
});
const hasFilters = computed(
    () =>
        !!(
            props.filters.from_date ||
            props.filters.to_date ||
            props.filters.recorded_by
        ),
);
function apply(): void {
    if (
        filters.from_date &&
        filters.to_date &&
        filters.to_date < filters.from_date
    ) {
        filters.setError(
            'to_date',
            'Tanggal akhir tidak boleh sebelum tanggal awal.',
        );
        return;
    }
    filters.clearErrors();
    filters.get('/admin/logbook/' + props.farm.id, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function reset(): void {
    filters.from_date = '';
    filters.to_date = '';
    filters.recorded_by = '';
    apply();
}
</script>
<template>
    <Head :title="'Logbook - ' + farm.name" />
    <div class="space-y-[18px]" :aria-busy="filters.processing">
        <Link
            href="/admin/logbook"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali ke logbook</Link
        >
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Logbook kebun
                </p>
                <h1 class="admin-page-title mt-1 text-primary">
                    {{ farm.name }}
                </h1>
                <p
                    class="mt-2 flex items-start gap-1.5 text-sm leading-6 text-muted-foreground"
                >
                    <MapPin class="mt-1 size-4 shrink-0" />{{
                        farmLocation(farm)
                    }}
                </p>
            </div>
        </header>
        <LogbookStats :summary="summary" />
        <section
            class="admin-card overflow-hidden rounded-xl bg-card"
            aria-labelledby="logbook-history-title"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 p-4 md:p-5"
            >
                <h2 id="logbook-history-title" class="admin-section-title">
                    Riwayat logbook
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ logs.total }} laporan sesuai filter · waktu dalam WIB
                </p>
            </header>
            <form
                class="grid items-start gap-3 px-4 pb-5 sm:grid-cols-2 md:px-5 xl:grid-cols-[1fr_1fr_1fr_auto]"
                @submit.prevent="apply"
            >
                <FarmField
                    id="logbook-from"
                    label="Dari tanggal"
                    :error="filters.errors.from_date"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="filters.from_date"
                        type="date"
                        class="admin-field"
                        :disabled="filters.processing"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="logbook-to"
                    label="Sampai tanggal"
                    :error="filters.errors.to_date"
                    v-slot="field"
                    ><input
                        :id="field.id"
                        v-model="filters.to_date"
                        type="date"
                        class="admin-field"
                        :disabled="filters.processing"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                /></FarmField>
                <FarmField
                    id="logbook-recorder"
                    label="Pelapor"
                    :error="filters.errors.recorded_by"
                    v-slot="field"
                    ><AppSelect
                        :id="field.id"
                        v-model="filters.recorded_by"
                        class="admin-field"
                        :disabled="filters.processing"
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                        ><AppSelectOption value=""
                            >Semua pelapor</AppSelectOption
                        ><AppSelectOption
                            v-for="person in recorders"
                            :key="person.id"
                            :value="person.id"
                            >{{ person.name }}</AppSelectOption
                        ></AppSelect
                    ></FarmField
                >
                <div class="flex flex-wrap items-center gap-2 pt-0 sm:pt-7">
                    <Button
                        type="submit"
                        variant="outline"
                        :disabled="filters.processing"
                        ><LoaderCircle
                            v-if="filters.processing"
                            class="animate-spin motion-reduce:animate-none"
                        /><Search v-else />Terapkan</Button
                    ><Button
                        v-if="hasFilters"
                        type="button"
                        variant="ghost"
                        :disabled="filters.processing"
                        @click="reset"
                        >Reset</Button
                    >
                </div>
            </form>
            <p
                v-if="hasFilters"
                class="px-4 pb-4 text-xs text-muted-foreground md:px-5"
            >
                {{
                    props.filters.from_date
                        ? 'Dari ' + logbookDate(props.filters.from_date)
                        : 'Semua tanggal awal'
                }}
                ·
                {{
                    props.filters.to_date
                        ? 'sampai ' + logbookDate(props.filters.to_date)
                        : 'tanpa batas tanggal akhir'
                }}{{
                    props.filters.recorded_by
                        ? ' · ' +
                          (recorders.find(
                              (person) => person.id === props.filters.recorded_by,
                          )?.name ?? 'Pelapor tidak tersedia')
                        : ''
                }}
            </p>
            <div v-if="filters.processing" class="space-y-3 p-5" role="status">
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-16 animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">
                    Memuat riwayat logbook…
                </p>
            </div>
            <div v-else-if="!logs.data.length" class="px-5 py-14 text-center">
                <BookOpen class="mx-auto size-9 text-primary" />
                <h3 class="mt-4 font-semibold">
                    {{
                        hasFilters
                            ? 'Logbook tidak ditemukan'
                            : 'Belum ada logbook'
                    }}
                </h3>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{
                        hasFilters
                            ? 'Coba rentang tanggal atau pelapor yang berbeda.'
                            : 'Laporan harian yang tersimpan akan ditampilkan di sini.'
                    }}
                </p>
                <Button
                    v-if="hasFilters"
                    variant="outline"
                    class="mt-5"
                    @click="reset"
                    >Reset filter</Button
                >
            </div>
            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel riwayat logbook"
                    tabindex="0"
                >
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">
                                    Tanggal kerja
                                </th>
                                <th scope="col" class="px-5 py-3">Pelapor</th>
                                <th scope="col" class="px-5 py-3">
                                    Waktu kerja
                                </th>
                                <th scope="col" class="px-5 py-3">Pekerja</th>
                                <th scope="col" class="px-5 py-3">Kegiatan</th>
                                <th scope="col" class="px-5 py-3">
                                    Kendala tercatat
                                </th>
                                <th scope="col" class="px-5 py-3">
                                    <span class="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="hover:bg-muted/30"
                            >
                                <td class="px-5 py-4">
                                    <Link
                                        :href="
                                            '/admin/logbook/' +
                                            farm.id +
                                            '/' +
                                            log.id
                                        "
                                        class="font-semibold whitespace-nowrap hover:text-primary"
                                        >{{ logbookDate(log.work_date) }}</Link
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    {{
                                        log.recorder_name ??
                                        'Pelapor tidak tersedia'
                                    }}
                                </td>
                                <td
                                    class="px-5 py-4 text-xs whitespace-nowrap text-muted-foreground"
                                >
                                    {{ log.start_time }}–{{ log.end_time }}
                                </td>
                                <td
                                    class="px-5 py-4 font-semibold tabular-nums"
                                >
                                    {{ log.worker_count }}
                                </td>
                                <td
                                    class="px-5 py-4 font-semibold tabular-nums"
                                >
                                    {{ log.activities_count }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1.5 text-xs font-semibold whitespace-nowrap"
                                        :class="
                                            log.obstacle_count
                                                ? 'bg-warning/10 text-warning'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                        >{{ log.obstacle_count }} kendala</span
                                    >
                                </td>
                                <td class="px-5 py-4">
                                    <Button as-child variant="ghost" size="icon"
                                        ><Link
                                            :href="
                                                '/admin/logbook/' +
                                                farm.id +
                                                '/' +
                                                log.id
                                            "
                                            :aria-label="
                                                'Detail logbook ' +
                                                logbookDate(log.work_date)
                                            "
                                            ><ArrowUpRight /></Link
                                    ></Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    class="divide-y divide-border md:hidden"
                    aria-label="Riwayat logbook"
                >
                    <li
                        v-for="log in logs.data"
                        :key="log.id"
                        class="space-y-3 p-4"
                    >
                        <Link
                            :href="'/admin/logbook/' + farm.id + '/' + log.id"
                            class="flex items-center justify-between gap-2 font-semibold"
                            ><span>{{ logbookDate(log.work_date) }}</span
                            ><ArrowUpRight class="size-4 text-primary"
                        /></Link>
                        <p class="text-xs text-muted-foreground">
                            {{
                                log.recorder_name ?? 'Pelapor tidak tersedia'
                            }}
                            · {{ log.start_time }}–{{ log.end_time }} WIB
                        </p>
                        <div class="flex flex-wrap gap-3 text-xs">
                            <span
                                ><strong>{{ log.worker_count }}</strong>
                                pekerja</span
                            ><span
                                ><strong>{{ log.activities_count }}</strong>
                                kegiatan</span
                            ><span
                                :class="
                                    log.obstacle_count
                                        ? 'font-semibold text-warning'
                                        : 'text-muted-foreground'
                                "
                                >{{ log.obstacle_count }} kendala tercatat</span
                            >
                        </div>
                    </li>
                </ul>
            </template>
            <LogbookPagination
                v-model:size="filters.per_page"
                :page="logs"
                label="logbook"
                :loading="filters.processing"
                @change="apply"
            />
        </section>
    </div>
</template>

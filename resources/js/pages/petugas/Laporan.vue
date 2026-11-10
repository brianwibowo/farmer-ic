<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, ChevronRight, Search } from '@lucide/vue';
import { reactive, ref } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';

defineOptions({ inheritAttrs: false });

type Report = {
    id: string;
    name: string;
    date: string;
    location: string;
    source: string;
    notes: string | null;
    photo: string | null;
};
const props = defineProps<{
    farm: { id: string; name: string } | null;
    reports: {
        data: Report[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    } | null;
    filters: {
        q?: string;
        type?: string;
        date?: string;
        source?: string;
        mine?: boolean;
    };
    types: { code: string; name: string }[];
}>();
const filter = reactive({
    q: props.filters.q ?? '',
    type: props.filters.type ?? '',
    date: props.filters.date ?? '',
    source: props.filters.source ?? '',
    mine: !!props.filters.mine,
});
const loading = ref(false);
function apply(): void {
    router.get(
        '/petugas/laporan',
        { ...filter, mine: filter.mine ? 1 : 0 },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
}
function reset(): void {
    Object.assign(filter, {
        q: '',
        type: '',
        date: '',
        source: '',
        mine: false,
    });
    apply();
}
</script>
<template>
    <Head title="Laporan" />
    <div class="space-y-4" :aria-busy="loading">
        <header>
            <h1 class="text-xl font-semibold">Laporan</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Riwayat pekerjaan ·
                {{ farm?.name ?? 'Belum ada penugasan kebun' }}
            </p>
        </header>
        <div
            class="flex items-center justify-between rounded-xl border border-primary/25 bg-primary/10 px-4 py-3"
        >
            <div>
                <p class="text-xl font-semibold">{{ reports?.total ?? 0 }}</p>
                <p class="text-xs text-muted-foreground">Aktivitas tercatat</p>
            </div>
            <Link
                v-if="farm"
                href="/petugas/laporan/harian"
                class="inline-flex min-h-11 items-center text-xs font-semibold text-secondary underline"
                >Info kerja harian</Link
            >
        </div>
        <form v-if="farm" class="space-y-2" @submit.prevent="apply">
            <label class="relative block"
                ><span class="sr-only">Cari laporan</span
                ><input
                    v-model="filter.q"
                    type="search"
                    placeholder="Cari aktivitas atau subblok"
                    class="min-h-11 w-full rounded-xl border border-primary bg-card px-3 pr-10 text-sm focus-visible:outline-2 focus-visible:outline-ring" /><Search
                    class="pointer-events-none absolute top-3 right-3 size-5 text-primary"
            /></label>
            <div class="grid grid-cols-2 gap-2">
                <label class="space-y-1 text-xs"
                    ><span>Tanggal kerja</span
                    ><input
                        v-model="filter.date"
                        type="date"
                        class="min-h-11 w-full min-w-0 rounded-xl border border-border bg-card px-2 text-sm [color-scheme:light] dark:[color-scheme:dark]" /></label
                ><label class="space-y-1 text-xs"
                    ><span>Kategori</span
                    ><AppSelect
                        v-model="filter.type"
                        aria-label="Kategori laporan"
                        class="min-h-11"
                        ><AppSelectOption value=""
                            >Semua kategori</AppSelectOption
                        ><AppSelectOption
                            v-for="type in types"
                            :key="type.code"
                            :value="type.code"
                            >{{ type.name }}</AppSelectOption
                        ></AppSelect
                    ></label
                >
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <AppSelect
                    v-model="filter.source"
                    aria-label="Asal aktivitas"
                    class="min-h-11 flex-1"
                    ><AppSelectOption value="">Semua sumber</AppSelectOption
                    ><AppSelectOption value="sop">Dari SOP</AppSelectOption
                    ><AppSelectOption value="mandiri"
                        >Mandiri</AppSelectOption
                    ></AppSelect
                ><label class="flex min-h-11 items-center gap-2 text-xs"
                    ><input v-model="filter.mine" type="checkbox" />Input
                    saya</label
                >
            </div>
            <div class="flex gap-2">
                <button
                    type="submit"
                    :disabled="loading"
                    class="min-h-11 flex-1 rounded-xl bg-secondary px-3 text-sm font-medium text-secondary-foreground disabled:opacity-50"
                >
                    {{ loading ? 'Memuat…' : 'Terapkan filter' }}</button
                ><button
                    type="button"
                    class="min-h-11 rounded-xl border border-border px-3 text-sm"
                    :disabled="loading"
                    @click="reset"
                >
                    Reset
                </button>
            </div>
        </form>
        <div
            v-if="!reports?.data.length"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <ClipboardList class="mx-auto size-9 text-primary" />
            <h2 class="mt-3 font-semibold">
                Belum ada laporan yang ditampilkan
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    farm
                        ? 'Coba ubah filter atau catat pekerjaan melalui halaman Aktivitas.'
                        : 'Hubungi Admin untuk penugasan kebun.'
                }}
            </p>
            <Link
                href="/petugas/aktivitas"
                class="mt-3 inline-flex min-h-11 items-center text-sm text-secondary underline"
                >Buka aktivitas</Link
            >
        </div>
        <ul v-else class="space-y-3" aria-label="Riwayat aktivitas">
            <li v-for="(report, index) in reports.data" :key="report.id">
                <h2
                    v-if="
                        index === 0 ||
                        reports.data[index - 1].date !== report.date
                    "
                    class="mb-2 mt-4 text-xs font-semibold text-muted-foreground"
                >
                    {{ report.date }}
                </h2>
                <Link
                    :href="`/petugas/laporan/${report.id}`"
                    class="petugas-card flex gap-3 p-3 hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-ring"
                    ><img
                        v-if="report.photo"
                        :src="report.photo"
                        alt="Dokumentasi aktivitas"
                        loading="lazy"
                        class="size-20 shrink-0 rounded-xl object-cover" /><span
                        v-else
                        class="flex size-20 shrink-0 items-center justify-center rounded-xl bg-primary/10"
                        ><ClipboardList class="size-7 text-primary"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <span
                            class="rounded-md bg-primary/10 px-2 py-1 text-[10px] font-semibold text-secondary"
                            >{{ report.source }}</span
                        >
                        <h3 class="mt-2 text-sm font-semibold">
                            {{ report.name }}
                        </h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ report.location }}
                        </p>
                        <p
                            v-if="report.notes"
                            class="mt-1 line-clamp-1 text-xs text-muted-foreground"
                        >
                            {{ report.notes }}
                        </p>
                    </div>
                    <ChevronRight
                        class="mt-2 size-4 shrink-0 text-muted-foreground"
                /></Link>
            </li>
        </ul>
        <nav
            v-if="reports && reports.last_page > 1"
            class="flex items-center justify-between text-sm"
            aria-label="Halaman laporan"
        >
            <Link
                v-if="reports.prev_page_url"
                :href="reports.prev_page_url"
                class="min-h-11 p-3 text-secondary"
                >← Sebelumnya</Link
            ><span>{{ reports.current_page }} / {{ reports.last_page }}</span
            ><Link
                v-if="reports.next_page_url"
                :href="reports.next_page_url"
                class="min-h-11 p-3 text-secondary"
                >Berikutnya →</Link
            >
        </nav>
    </div>
</template>

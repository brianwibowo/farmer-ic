<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Search, SlidersHorizontal } from '@lucide/vue';
import { computed, reactive, ref, useId, watch, onScopeDispose } from 'vue';
import NumberPagination from '@/components/NumberPagination.vue';
import PetugasListHeader from '@/components/petugas/PetugasListHeader.vue';
import TaskCard from '@/components/petugas/TaskCard.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import type { PetugasTaskPageProps } from '@/types/petugas-task';
import { sopActivityCategoryLabels } from '@/types/sop';

defineOptions({ inheritAttrs: false });
const props = defineProps<PetugasTaskPageProps>();
const isHistory = computed(() => props.filters.view === 'history');
const normalize = () => ({
    q: props.filters.q ?? '',
    status: props.filters.status,
    category: props.filters.category ?? '',
    block: props.filters.block_id ?? '',
    subblok: props.filters.subblok_id ?? '',
    perPage: props.filters.per_page ?? 10,
});
const filter = reactive(normalize());
const loading = ref(false);
const error = ref('');
let timer: ReturnType<typeof setTimeout> | undefined;
let cancelVisit: (() => void) | undefined;
let revision = 0;
let syncing = false;
let lastPage = 1;
function cancelPending() {
    revision++;
    if (timer) clearTimeout(timer);
    timer = undefined;
    const cancel = cancelVisit;
    cancelVisit = undefined;
    cancel?.();
}
function load(page = 1, replace = true) {
    if (!props.farm) return;
    lastPage = page;
    const current = revision;
    loading.value = true;
    error.value = '';
    const data: Record<string, string | number> = {
        view: props.filters.view ?? 'running',
        status: filter.status,
        per_page: filter.perPage,
    };
    if (filter.q.trim()) data.q = filter.q.trim();
    if (filter.category) data.category = filter.category;
    if (filter.block) data.block_id = filter.block;
    if (filter.subblok) data.subblok_id = filter.subblok;
    if (page > 1) data.page = page;
    const fail = (): false => {
        if (current === revision)
            error.value = 'Tugas belum berhasil dimuat. Silakan coba lagi.';
        return false;
    };
    router.get('/petugas/aktivitas', data, {
        preserveState: true,
        preserveScroll: true,
        replace,
        onCancelToken: (token) => {
            if (current === revision) cancelVisit = token.cancel;
            else token.cancel();
        },
        onError: fail,
        onHttpException: fail,
        onNetworkError: fail,
        onFinish: () => {
            if (current === revision) {
                loading.value = false;
                cancelVisit = undefined;
            }
        },
    });
}
watch(
    () =>
        [
            filter.q,
            filter.status,
            filter.category,
            filter.block,
            filter.subblok,
            filter.perPage,
        ] as const,
    (next, previous) => {
        if (syncing) return;
        cancelPending();
        if (!props.farm) return;
        loading.value = true;
        if (
            next[0] !== previous[0] &&
            next.slice(1).every((value, index) => value === previous[index + 1])
        )
            timer = setTimeout(() => load(), 300);
        else load();
    },
    { flush: 'sync' },
);
watch(
    () => props.filters,
    () => {
        const updated = normalize();
        if (JSON.stringify(filter) === JSON.stringify(updated)) return;
        cancelPending();
        syncing = true;
        Object.assign(filter, updated);
        syncing = false;
        loading.value = false;
        error.value = '';
    },
    { flush: 'sync' },
);
onScopeDispose(cancelPending);
function requestPage(page: number) {
    if (!loading.value) {
        cancelPending();
        load(page, false);
    }
}
function retry() {
    cancelPending();
    load(lastPage);
}
const options = [
    { key: 'active', label: 'Perlu tindakan' },
    { key: 'overdue', label: 'Terlambat' },
    { key: 'today', label: 'Hari ini' },
    { key: 'upcoming', label: 'Mendatang' },
    { key: 'completed', label: 'Ditutup' },
    { key: 'all', label: 'Semua' },
];
const blocks = computed(() => props.options.blocks);
const subblokOptions = computed(() =>
    props.options.subbloks
        .filter((item) => item.block_id === filter.block)
        .sort((a, b) =>
            (a.label ?? `Subblok ${a.number}`).localeCompare(
                b.label ?? `Subblok ${b.number}`,
                'id',
            ),
        ),
);
const categoryOptions = computed(() => props.options.categories);
function label(code: string): string {
    return (sopActivityCategoryLabels as Record<string, string>)[code] ?? code;
}
function changeBlock(): void {
    filter.subblok = '';
}
const advancedOpen = ref(false);
const advancedId = useId();
const advancedCount = computed(
    () =>
        [filter.category, filter.block, filter.subblok].filter(Boolean).length,
);
function reset(): void {
    syncing = true;
    Object.assign(filter, {
        q: '',
        status: isHistory.value ? 'all' : 'active',
        category: '',
        block: '',
        subblok: '',
    });
    syncing = false;
    cancelPending();
    load();
}
</script>

<template>
    <Head title="Tugas" />
    <div
        class="min-w-0 space-y-[var(--admin-content-gap)] text-foreground"
        :aria-busy="loading"
    >
        <PetugasListHeader
            title="Tugas"
            :show-incidents="!!farm"
            summary-label="Ringkasan tugas"
            :metrics="
                isHistory
                    ? []
                    : [
                          { label: 'Terlambat', value: summary.overdue },
                          { label: 'Hari ini', value: summary.today },
                          { label: 'Mendatang', value: summary.upcoming },
                      ]
            "
        />
        <nav aria-label="Tampilan tugas" class="flex gap-2 overflow-x-auto">
            <Link
                href="/petugas/aktivitas?view=running"
                preserve-scroll
                :aria-current="!isHistory ? 'page' : undefined"
                class="inline-flex min-h-11 shrink-0 items-center rounded-xl border px-4 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                :class="
                    !isHistory
                        ? 'border-secondary bg-secondary text-secondary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                "
                >Tugas berjalan</Link
            >
            <Link
                href="/petugas/aktivitas?view=history"
                preserve-scroll
                :aria-current="isHistory ? 'page' : undefined"
                class="inline-flex min-h-11 shrink-0 items-center rounded-xl border px-4 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                :class="
                    isHistory
                        ? 'border-secondary bg-secondary text-secondary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted'
                "
                >Riwayat subblok</Link
            >
        </nav>
        <p v-if="isHistory" class="text-sm text-muted-foreground">
            Tugas dari subblok selesai atau dibatalkan hanya dapat dilihat.
        </p>
        <section v-if="farm" class="space-y-2" aria-label="Filter tugas">
            <label class="relative block">
                <span class="sr-only">Cari tugas</span>
                <input
                    v-model="filter.q"
                    type="search"
                    maxlength="100"
                    placeholder="Cari tugas atau subblok"
                    class="petugas-field min-h-11 w-full rounded-xl border border-border px-3 pr-10 text-foreground"
                />
                <Search
                    class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-primary"
                    aria-hidden="true"
                />
            </label>
            <div
                class="grid gap-2"
                :class="
                    isHistory ? 'grid-cols-1' : 'grid-cols-[minmax(0,1fr)_auto]'
                "
            >
                <AppSelect
                    v-if="!isHistory"
                    v-model="filter.status"
                    aria-label="Status tugas"
                    class="min-h-11 min-w-0"
                    ><AppSelectOption
                        v-for="option in options"
                        :key="option.key"
                        :value="option.key"
                        >{{ option.label }}</AppSelectOption
                    ></AppSelect
                >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 rounded-xl bg-card text-xs"
                    :aria-expanded="advancedOpen"
                    :aria-controls="advancedId"
                    @click="advancedOpen = !advancedOpen"
                >
                    <SlidersHorizontal aria-hidden="true" />
                    Filter
                    <span v-if="advancedCount">({{ advancedCount }})</span>
                </Button>
            </div>
            <div
                v-show="advancedOpen"
                :id="advancedId"
                class="petugas-card grid gap-2 p-3"
            >
                <AppSelect
                    v-model="filter.category"
                    aria-label="Kategori tugas"
                    class="min-h-11"
                    ><AppSelectOption value="">Semua kategori</AppSelectOption
                    ><AppSelectOption
                        v-for="code in categoryOptions"
                        :key="code"
                        :value="code"
                        >{{ label(code) }}</AppSelectOption
                    ></AppSelect
                >
                <AppSelect
                    v-model="filter.block"
                    aria-label="Blok"
                    class="min-h-11"
                    @change="changeBlock"
                    ><AppSelectOption value="">Semua blok</AppSelectOption
                    ><AppSelectOption
                        v-for="block in blocks"
                        :key="block.id"
                        :value="block.id"
                        >{{ block.name }}</AppSelectOption
                    ></AppSelect
                >
                <AppSelect
                    v-model="filter.subblok"
                    aria-label="Subblok"
                    :disabled="!filter.block"
                    :placeholder="filter.block ? 'Semua subblok' : 'Pilih blok'"
                    class="min-h-11"
                    ><AppSelectOption v-if="filter.block" value=""
                        >Semua subblok</AppSelectOption
                    ><AppSelectOption
                        v-for="subblok in subblokOptions"
                        :key="subblok.id"
                        :value="subblok.id"
                        >{{
                            subblok.label ?? `Subblok ${subblok.number}`
                        }}</AppSelectOption
                    ></AppSelect
                >
            </div>
        </section>
        <p
            v-if="defaultSopMissing && farm"
            class="rounded-xl border border-border bg-muted/50 p-3 text-xs leading-5"
        >
            SOP default pada salah satu blok belum diatur. Hubungi Admin;
            aktivitas mandiri tetap dapat dicatat.
        </p>
        <div v-if="farm" class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">
                    {{ isHistory ? 'Riwayat tugas' : 'Daftar tugas' }}
                </h2>
                <p role="status" class="mt-1 text-xs text-muted-foreground">
                    {{ tasks?.total ?? 0 }} tugas ditemukan
                </p>
            </div>
            <button
                v-if="
                    filter.q ||
                    filter.category ||
                    filter.block ||
                    filter.subblok ||
                    filter.status !== (isHistory ? 'all' : 'active')
                "
                type="button"
                class="min-h-11 rounded-xl px-2 text-xs font-semibold text-primary underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="reset"
            >
                Reset filter
            </button>
        </div>
        <p v-if="loading" role="status" class="text-sm text-muted-foreground">
            Memuat tugas…
        </p>
        <div v-else-if="error" role="alert" class="petugas-card p-4 text-error">
            <p>{{ error }}</p>
            <button type="button" class="min-h-11 underline" @click="retry">
                Coba lagi
            </button>
        </div>
        <div
            v-else-if="!tasks?.data.length"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <ClipboardList class="mx-auto size-9 text-primary" />
            <h2 class="mt-3 font-semibold">
                {{
                    farm
                        ? 'Tidak ada tugas pada pilihan ini'
                        : 'Belum ada penugasan lahan'
                }}
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                {{
                    farm
                        ? 'Ubah filter untuk melihat tugas lain.'
                        : 'Hubungi Admin untuk mendapatkan penugasan.'
                }}
            </p>
        </div>
        <ul v-else class="space-y-3" aria-label="Daftar tugas">
            <li v-for="task in tasks.data" :key="task.id">
                <TaskCard :task="task" />
            </li>
        </ul>
        <footer
            v-if="farm && tasks && !error"
            class="petugas-card flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-xs text-muted-foreground"
        >
            <span role="status" aria-live="polite" aria-atomic="true">
                Menampilkan {{ tasks.from ?? 0 }}–{{ tasks.to ?? 0 }} dari
                {{ tasks.total }} tugas.
            </span>
            <div
                class="ml-auto flex w-full flex-wrap items-center justify-between gap-3"
            >
                <label for="task-per-page" class="flex items-center gap-2">
                    Per halaman
                    <AppSelect
                        id="task-per-page"
                        v-model.number="filter.perPage"
                        :disabled="loading"
                        class="admin-field w-auto!"
                        aria-label="Jumlah tugas per halaman"
                    >
                        <AppSelectOption
                            v-for="size in [10, 20, 50]"
                            :key="size"
                            :value="size"
                            >{{ size }}</AppSelectOption
                        >
                    </AppSelect>
                </label>
                <NumberPagination
                    class="ml-auto"
                    :current-page="tasks.current_page"
                    :total-pages="tasks.last_page"
                    :disabled="loading"
                    label="Pagination tugas"
                    @change="requestPage"
                />
            </div>
        </footer>
    </div>
</template>

<script setup lang="ts">
import { Head, useRemember } from '@inertiajs/vue3';
import {
    CalendarDays,
    CalendarClock,
    CircleAlert,
    ClipboardList,
    Search,
} from '@lucide/vue';
import { computed, reactive, unref, watch } from 'vue';
import TaskCard from '@/components/petugas/TaskCard.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { PetugasTask, PetugasTaskPageProps } from '@/types/petugas-task';
import { sopActivityCategoryLabels } from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<PetugasTaskPageProps>();
const filter = reactive(
    unref(
        useRemember<{
            q: string;
            status: PetugasTaskPageProps['filters']['status'];
            category: string;
            block: string;
            subblok: string;
        }>(
            {
                q: '',
                status:
                    props.filters.status === 'all' ||
                    props.filters.status === 'overdue' ||
                    props.filters.status === 'today'
                        ? 'active'
                        : props.filters.status,
                category: '',
                block: '',
                subblok: '',
            },
            'petugas-activities',
        ),
    ),
);
if (filter.status === 'overdue' || filter.status === 'today') {
    filter.status = 'active';
}
filter.subblok ??= '';
// Explicit navigation from the inbox overrides remembered local filters.
watch(
    () =>
        [
            props.filters.block_id,
            props.filters.status,
            props.filters.category,
        ] as const,
    ([block, status, category]) => {
        if (!block) return;
        Object.assign(filter, {
            q: '',
            status:
                status === 'today' || status === 'overdue' ? 'active' : status,
            category: category ?? '',
            block,
            subblok: '',
        });
    },
    { immediate: true },
);

const options = [
    { key: 'active', label: 'Perlu tindakan' },
    { key: 'upcoming', label: 'Mendatang' },
    { key: 'completed', label: 'Ditutup' },
    { key: 'all', label: 'Semua' },
];
const blocks = computed(() => [
    ...new Map(
        props.tasks.map((t) => [t.subblok.block.id, t.subblok.block]),
    ).values(),
]);
const subblokOptions = computed(() => {
    if (!filter.block) return [];

    return [
        ...new Map(
            props.tasks
                .filter((t) => t.subblok.block.id === filter.block)
                .map((t) => [t.subblok.id, t.subblok]),
        ).values(),
    ].sort((a, b) =>
        (a.label ?? `Subblok ${a.number}`).localeCompare(
            b.label ?? `Subblok ${b.number}`,
            'id',
        ),
    );
});
function matchesStatus(task: PetugasTask): boolean {
    if (filter.status === 'all') return true;
    if (filter.status === 'active') {
        return task.urgency === 'overdue' || task.urgency === 'today';
    }

    return task.urgency === filter.status;
}
const tasks = computed(() =>
    props.tasks.filter(
        (t) =>
            matchesStatus(t) &&
            (!filter.category || t.activity_category === filter.category) &&
            (!filter.block || t.subblok.block.id === filter.block) &&
            (!filter.subblok || t.subblok.id === filter.subblok) &&
            `${t.name} ${t.subblok.block.name} ${t.subblok.label}`
                .toLocaleLowerCase()
                .includes(filter.q.toLocaleLowerCase().trim()),
    ),
);
const categoryOptions = computed(() => [
    ...new Set(props.tasks.map((t) => t.activity_category)),
]);
function label(code: string): string {
    return (sopActivityCategoryLabels as Record<string, string>)[code] ?? code;
}
function changeBlock(): void {
    filter.subblok = '';
}
function reset(): void {
    Object.assign(filter, {
        q: '',
        status: 'active',
        category: '',
        block: '',
        subblok: '',
    });
}
</script>

<template>
    <Head title="Tugas" />
    <div class="min-w-0 space-y-4 pb-16 text-foreground">
        <header>
            <h1>Tugas</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{
                    farm
                        ? `Tugas dari SOP · ${farm.name}`
                        : 'Belum ada penugasan kebun'
                }}
            </p>
        </header>
        <section class="grid grid-cols-3 gap-2" aria-label="Ringkasan tugas">
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-warning/15 text-foreground"
                >
                    <CircleAlert class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">{{ summary.overdue }}</p>
                <p class="text-xs text-muted-foreground">Terlambat</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-info/10 text-info"
                >
                    <CalendarDays class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">{{ summary.today }}</p>
                <p class="text-xs text-muted-foreground">Hari ini</p>
            </div>
            <div
                class="petugas-card flex min-w-0 flex-col items-center gap-1 px-2 py-3 text-center"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <CalendarClock class="size-4" aria-hidden="true" />
                </span>
                <p class="text-lg font-semibold">{{ summary.upcoming }}</p>
                <p class="text-xs text-muted-foreground">Mendatang</p>
            </div>
        </section>
        <section class="space-y-2" aria-label="Filter tugas">
            <label class="relative block">
                <span class="sr-only">Cari tugas</span>
                <input
                    v-model="filter.q"
                    type="search"
                    placeholder="Cari tugas atau subblok"
                    class="petugas-field min-h-11 w-full rounded-xl border border-border px-3 pr-10 text-foreground"
                />
                <Search
                    class="pointer-events-none absolute top-1/2 right-3 size-5 -translate-y-1/2 text-primary"
                    aria-hidden="true"
                />
            </label>
            <div class="grid grid-cols-2 gap-2">
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
                <AppSelect
                    v-model="filter.status"
                    aria-label="Status tugas"
                    class="col-span-2 min-h-11 min-[360px]:col-span-1"
                    ><AppSelectOption
                        v-for="option in options"
                        :key="option.key"
                        :value="option.key"
                        >{{ option.label }}</AppSelectOption
                    ></AppSelect
                >
                <AppSelect
                    v-model="filter.category"
                    aria-label="Kategori tugas"
                    class="col-span-2 min-h-11 min-[360px]:col-span-1"
                    ><AppSelectOption value="">Semua kategori</AppSelectOption
                    ><AppSelectOption
                        v-for="code in categoryOptions"
                        :key="code"
                        :value="code"
                        >{{ label(code) }}</AppSelectOption
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
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Daftar tugas</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ tasks.length }} tugas ditampilkan
                </p>
            </div>
            <button
                v-if="
                    filter.q ||
                    filter.category ||
                    filter.block ||
                    filter.subblok ||
                    filter.status !== 'active'
                "
                type="button"
                class="min-h-11 rounded-xl px-2 text-xs font-semibold text-primary underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                @click="reset"
            >
                Reset filter
            </button>
        </div>
        <div
            v-if="!tasks.length"
            class="petugas-card petugas-empty bg-muted/40 px-5 py-10 text-center"
        >
            <ClipboardList class="mx-auto size-9 text-primary" />
            <h2 class="mt-3 font-semibold">
                {{
                    farm
                        ? 'Tidak ada tugas pada pilihan ini'
                        : 'Belum ada penugasan kebun'
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
            <li v-for="task in tasks" :key="task.id">
                <TaskCard :task="task" />
            </li>
        </ul>
    </div>
</template>

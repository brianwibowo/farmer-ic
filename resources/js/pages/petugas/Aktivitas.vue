<script setup lang="ts">
import { Head, Link, useRemember } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronRight,
    ClipboardList,
    MapPin,
    Search,
    Sprout,
} from '@lucide/vue';
import { computed, reactive, unref } from 'vue';
import ActivityAddButton from '@/components/petugas/ActivityAddButton.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import type { PetugasTaskPageProps } from '@/types/petugas-task';
import { sopActivitySubcategoryLabels } from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<
    PetugasTaskPageProps & {
        categories: { id: string; code: string; name: string }[];
    }
>();
const filter = reactive(
    unref(
        useRemember(
            {
                q: '',
                status:
                    props.filters.status === 'all'
                        ? 'active'
                        : props.filters.status,
                category: '',
                block: '',
            },
            'petugas-activities',
        ),
    ),
);
const options = [
    { key: 'active', label: 'Aktif' },
    { key: 'overdue', label: 'Terlambat' },
    { key: 'today', label: 'Hari ini' },
    { key: 'upcoming', label: 'Mendatang' },
    { key: 'completed', label: 'Ditutup' },
];
const blocks = computed(() => [
    ...new Map(
        props.tasks.map((t) => [t.subblok.block.id, t.subblok.block]),
    ).values(),
]);
const tasks = computed(() =>
    props.tasks.filter(
        (t) =>
            (filter.status === 'active'
                ? t.urgency !== 'completed'
                : t.urgency === filter.status) &&
            (!filter.category ||
                (t.activity_subcategory ?? t.activity_category) ===
                    filter.category) &&
            (!filter.block || t.subblok.block.id === filter.block) &&
            `${t.name} ${t.subblok.block.name} ${t.subblok.label}`
                .toLocaleLowerCase()
                .includes(filter.q.toLocaleLowerCase().trim()),
    ),
);
const categoryOptions = computed(() => [
    ...new Set(
        props.tasks.map((t) => t.activity_subcategory ?? t.activity_category),
    ),
]);
function label(code: string): string {
    return (
        (sopActivitySubcategoryLabels as Record<string, string>)[code] ??
        (code === 'harvest' ? 'Panen' : code)
    );
}
function reset(): void {
    Object.assign(filter, { q: '', status: 'active', category: '', block: '' });
}
</script>

<template>
    <Head title="Aktivitas" />
    <div class="min-w-0 space-y-4 pb-16 text-foreground">
        <header>
            <h1 class="text-xl leading-7 font-semibold">Aktivitas</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ farm?.name ?? 'Belum ada penugasan kebun' }}
            </p>
        </header>
        <section class="grid grid-cols-3 gap-2" aria-label="Ringkasan tugas">
            <div
                v-for="metric in [
                    {
                        key: 'overdue',
                        label: 'Terlambat',
                        count: summary.overdue,
                    },
                    { key: 'today', label: 'Hari ini', count: summary.today },
                    {
                        key: 'upcoming',
                        label: 'Mendatang',
                        count: summary.upcoming,
                    },
                ]"
                :key="metric.key"
                class="rounded-xl border px-2 py-3 text-center"
                :class="
                    metric.key === 'overdue'
                        ? 'border-warning/40 bg-warning/10'
                        : 'border-primary/25 bg-primary/10'
                "
            >
                <CalendarDays
                    class="mx-auto mb-2 size-5 text-secondary"
                    aria-hidden="true"
                />
                <p class="text-lg font-semibold">{{ metric.count }}</p>
                <p class="text-[11px] text-muted-foreground">
                    {{ metric.label }}
                </p>
            </div>
        </section>
        <div class="space-y-2">
            <label class="relative block"
                ><span class="sr-only">Cari tugas</span
                ><input
                    v-model="filter.q"
                    type="search"
                    placeholder="Cari aktivitas atau subblok"
                    class="min-h-11 w-full rounded-xl border border-primary bg-card px-3 pr-10 text-sm focus-visible:outline-2 focus-visible:outline-ring" /><Search
                    class="pointer-events-none absolute top-3 right-3 size-5 text-primary"
            /></label>
            <div class="grid grid-cols-2 gap-2">
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
                    ><AppSelectOption value="">Semua blok</AppSelectOption
                    ><AppSelectOption
                        v-for="block in blocks"
                        :key="block.id"
                        :value="block.id"
                        >{{ block.name }}</AppSelectOption
                    ></AppSelect
                >
            </div>
        </div>
        <div class="flex gap-2 overflow-x-auto" aria-label="Filter jadwal">
            <button
                v-for="option in options"
                :key="option.key"
                type="button"
                :aria-pressed="filter.status === option.key"
                class="min-h-11 shrink-0 rounded-xl border px-3 text-xs font-medium focus-visible:outline-2 focus-visible:outline-ring"
                :class="
                    filter.status === option.key
                        ? 'border-secondary bg-secondary text-secondary-foreground'
                        : 'border-border bg-card'
                "
                @click="filter.status = option.key"
            >
                {{ option.label }}
            </button>
        </div>
        <p
            v-if="defaultSopMissing && farm"
            class="rounded-xl bg-muted p-3 text-xs leading-5"
        >
            SOP default belum diatur. Hubungi Admin; aktivitas mandiri tetap
            dapat dicatat.
        </p>
        <div
            class="flex items-center justify-between text-xs text-muted-foreground"
        >
            <span>{{ tasks.length }} tugas</span
            ><button
                v-if="
                    filter.q ||
                    filter.category ||
                    filter.block ||
                    filter.status !== 'active'
                "
                class="min-h-11 text-secondary underline"
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
                        ? 'Ubah filter, atau gunakan tombol + untuk mencatat aktivitas mandiri.'
                        : 'Hubungi Admin untuk mendapatkan penugasan.'
                }}
            </p>
        </div>
        <ul v-else class="space-y-3" aria-label="To-do aktivitas">
            <li v-for="task in tasks" :key="task.id">
                <Link
                    :href="`/petugas/tasks/${task.id}`"
                    class="petugas-card flex gap-3 p-3 transition-colors hover:bg-muted/30 focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary"
                        ><Sprout class="size-6"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <span
                            class="text-[10px] font-semibold text-secondary"
                            >{{
                                label(
                                    task.activity_subcategory ??
                                        task.activity_category,
                                )
                            }}</span
                        >
                        <h2 class="mt-1 text-base leading-5 font-semibold">
                            {{ task.name }}
                        </h2>
                        <p
                            class="mt-2 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3 shrink-0" />{{
                                task.subblok.block.name
                            }}
                            · {{ task.subblok.label }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ task.scheduled_date ?? 'Belum dijadwalkan' }} ·
                            Target HST {{ task.offset_days }}
                        </p>
                        <span
                            class="mt-2 inline-block rounded-md px-2 py-1 text-[10px] font-medium"
                            :class="
                                task.urgency === 'overdue'
                                    ? 'bg-warning/15 text-foreground'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{
                                options.find((o) => o.key === task.urgency)
                                    ?.label ?? 'Aktif'
                            }}<template v-if="task.linked_activities_count">
                                ·
                                {{ task.linked_activities_count }}
                                laporan</template
                            ></span
                        >
                    </div>
                    <ChevronRight
                        class="mt-2 size-4 shrink-0 text-muted-foreground"
                    />
                </Link>
            </li>
        </ul>
        <ActivityAddButton :categories="categories" />
    </div>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarDays,
    ClipboardList,
    LoaderCircle,
    Search,
} from '@lucide/vue';
import { computed } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import TaskQuickActions from '@/components/tasks/TaskQuickActions.vue';
import { Button } from '@/components/ui/button';
import {
    adminTaskCategoryLabel,
    adminTaskCategoryOptions,
    adminTaskDate,
    adminTaskHst,
    adminTaskStatusClass,
    adminTaskStatusLabels,
    adminTaskStatusOptions,
    adminTaskSubcategoryLabel,
    adminTaskUrgencyClass,
    adminTaskUrgencyLabels,
    adminTaskViewOptions,
    type AdminTaskBlockOption,
    type AdminTaskFilters,
    type AdminTaskPage,
    type AdminTaskSubblokOption,
    type AdminTaskSummary,
    type AdminTaskView,
} from '@/types/tasks';

const props = defineProps<{
    tasks: AdminTaskPage;
    filters: AdminTaskFilters;
    summary: AdminTaskSummary;
    blocks: AdminTaskBlockOption[];
    subbloks: AdminTaskSubblokOption[];
}>();

const form = useForm({
    search: props.filters.search ?? '',
    block_id: props.filters.block_id ?? '',
    subblok_id: props.filters.subblok_id ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    view: (props.filters.view ?? 'actionable') as AdminTaskView,
    per_page: props.filters.per_page ?? 15,
});
const subblokOptions = computed(() =>
    form.block_id
        ? props.subbloks.filter((subblok) => subblok.block_id === form.block_id)
        : props.subbloks,
);
const showBlockFarm = computed(
    () => new Set(props.blocks.map((block) => block.farm_id)).size > 1,
);
const hasFilters = computed(
    () =>
        !!form.search ||
        !!form.block_id ||
        !!form.subblok_id ||
        !!form.status ||
        !!form.category ||
        form.view !== 'actionable',
);
const summaryCards = computed(() => [
    {
        label: 'Total tugas',
        value: props.summary.total,
        tone: 'text-foreground',
        view: 'all' as AdminTaskView,
    },
    {
        label: 'Terbuka',
        value: props.summary.open,
        tone: 'text-primary',
        view: 'actionable' as AdminTaskView,
    },
    {
        label: 'Terlambat',
        value: props.summary.overdue,
        tone: 'text-error',
        view: 'overdue' as AdminTaskView,
    },
    {
        label: 'Hari ini',
        value: props.summary.today,
        tone: 'text-warning',
        view: 'today' as AdminTaskView,
    },
    {
        label: 'Selesai / tidak dikerjakan',
        value: props.summary.completed,
        tone: 'text-success',
        view: 'completed' as AdminTaskView,
    },
]);
const emptyCopy = computed(() => {
    if (hasFilters.value) {
        return {
            title: 'Tugas tidak ditemukan',
            description: 'Coba kata kunci atau filter lain.',
        };
    }
    if (form.view === 'actionable') {
        return {
            title: 'Tidak ada tugas terbuka',
            description:
                'Semua tugas sudah selesai atau belum ada jadwal yang perlu ditindaklanjuti.',
        };
    }
    if (form.view === 'completed') {
        return {
            title: 'Belum ada tugas selesai',
            description:
                'Tugas yang selesai atau ditandai tidak dikerjakan akan muncul di sini.',
        };
    }

    return {
        title: 'Belum ada tugas',
        description: 'Tugas akan muncul setelah SOP diterapkan ke subblok.',
    };
});

function changeBlock(): void {
    if (
        form.subblok_id &&
        !subblokOptions.value.some(
            (subblok) => subblok.id === form.subblok_id,
        )
    ) {
        form.subblok_id = '';
    }
}

function selectView(view: AdminTaskView): void {
    if (form.processing) return;
    form.view = view;
    form.status = '';
    apply();
}

function changeStatus(): void {
    if (form.status) form.view = 'all';
}

function apply(): void {
    form.get('/admin/tasks', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function reset(): void {
    form.search = '';
    form.block_id = '';
    form.subblok_id = '';
    form.status = '';
    form.category = '';
    form.view = 'actionable';
    apply();
}
</script>

<template>
    <Head title="Tugas Operasional" />
    <div class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                    Operasional / Tugas
                </p>
                <h1 class="admin-page-title tracking-tight">Tugas operasional</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                    Pantau tugas yang dihasilkan dari SOP subblok. Lepaskan,
                    batalkan, atau jadwalkan ulang tugas yang belum dikerjakan.
                </p>
            </div>
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <button
                v-for="item in summaryCards"
                :key="item.label"
                type="button"
                class="rounded-xl border bg-card p-4 text-left transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-60"
                :class="
                    form.view === item.view
                        ? 'border-primary ring-1 ring-primary'
                        : 'border-border'
                "
                :aria-pressed="form.view === item.view"
                :disabled="form.processing"
                @click="selectView(item.view)"
            >
                <p class="text-xs text-muted-foreground">{{ item.label }}</p>
                <p class="admin-metric-value mt-2" :class="item.tone">
                    {{ item.value }}
                </p>
            </button>
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Tampilan tugas">
            <button
                v-for="[value, label] in adminTaskViewOptions"
                :key="value"
                type="button"
                :aria-pressed="form.view === value"
                class="min-h-11 shrink-0 rounded-xl border px-3 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-60"
                :class="
                    form.view === value
                        ? 'border-secondary bg-secondary text-secondary-foreground'
                        : 'border-border bg-card text-muted-foreground hover:bg-muted/40'
                "
                :disabled="form.processing"
                @click="selectView(value)"
            >
                {{ label }}
            </button>
        </div>

        <section class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="border-b border-border p-4 md:p-5">
                <div class="mb-4 flex items-center gap-2">
                    <h2 class="admin-section-title">Daftar tugas</h2>
                    <span class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground">
                        {{ tasks.total }}
                    </span>
                </div>
                <form class="grid items-start gap-3 sm:grid-cols-2 xl:grid-cols-6" @submit.prevent="apply">
                    <FarmField id="task-search" label="Cari" :error="form.errors.search" class="sm:col-span-2 xl:col-span-2" v-slot="field">
                        <div class="relative">
                            <Search class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground" aria-hidden="true" />
                            <input
                                :id="field.id"
                                v-model="form.search"
                                class="admin-field pl-9!"
                                type="search"
                                maxlength="100"
                                placeholder="Nama tugas, blok, atau subblok"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </div>
                    </FarmField>
                    <FarmField id="task-block" label="Blok" :error="form.errors.block_id" v-slot="field">
                        <select
                            :id="field.id"
                            v-model="form.block_id"
                            class="admin-field"
                            @change="changeBlock"
                        >
                            <option value="">Semua blok</option>
                            <option v-for="block in blocks" :key="block.id" :value="block.id">
                                {{ block.code }} · {{ block.name }}
                                <template v-if="showBlockFarm && block.farm_name">
                                    · {{ block.farm_name }}
                                </template>
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="task-subblok" label="Subblok" :error="form.errors.subblok_id" v-slot="field">
                        <select :id="field.id" v-model="form.subblok_id" class="admin-field">
                            <option value="">Semua subblok</option>
                            <option v-for="subblok in subblokOptions" :key="subblok.id" :value="subblok.id">
                                {{ subblok.label ?? `Subblok ${subblok.number}` }}
                                <template v-if="!form.block_id && subblok.block_name">
                                    · {{ subblok.block_name }}
                                </template>
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="task-status" label="Status" :error="form.errors.status" v-slot="field">
                        <select :id="field.id" v-model="form.status" class="admin-field" @change="changeStatus">
                            <option value="">Semua status</option>
                            <option v-for="[value, label] in adminTaskStatusOptions" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="task-category" label="Kategori" :error="form.errors.category" v-slot="field">
                        <select :id="field.id" v-model="form.category" class="admin-field">
                            <option value="">Semua kategori</option>
                            <option v-for="[value, label] in adminTaskCategoryOptions" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </FarmField>
                    <div class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-6">
                        <select v-model.number="form.per_page" class="admin-field w-auto!" aria-label="Jumlah per halaman">
                            <option :value="15">15 / halaman</option>
                            <option :value="30">30 / halaman</option>
                            <option :value="50">50 / halaman</option>
                        </select>
                        <Button type="submit" class="h-10 rounded-xl" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            <Search v-else />Terapkan
                        </Button>
                        <Button v-if="hasFilters" type="button" variant="ghost" :disabled="form.processing" @click="reset">
                            Reset filter
                        </Button>
                    </div>
                </form>
            </div>

            <div v-if="form.processing" class="space-y-4 p-6" role="status" aria-label="Memuat tugas">
                <div v-for="row in 3" :key="row" class="h-12 animate-pulse rounded-xl bg-muted" />
                <p class="text-sm text-muted-foreground">Memuat tugas.</p>
            </div>
            <div v-else-if="!tasks.data.length" class="px-5 py-16 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary">
                    <ClipboardList class="size-7" />
                </div>
                <h3 class="mt-4 font-semibold">{{ emptyCopy.title }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                    {{ emptyCopy.description }}
                </p>
                <Button v-if="hasFilters" variant="outline" class="mt-5 rounded-xl" @click="reset">Reset filter</Button>
            </div>

            <template v-else>
                <div class="hidden overflow-x-auto md:block" role="region" aria-label="Tabel tugas operasional" tabindex="0">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-muted/50 text-xs text-muted-foreground">
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">Tugas</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Lokasi</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Jadwal</th>
                                <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="task in tasks.data" :key="task.id" class="align-top hover:bg-muted/40">
                                <td class="px-5 py-4">
                                    <Link :href="`/admin/tasks/${task.id}`" class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring">
                                        {{ task.name }}
                                    </Link>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ adminTaskCategoryLabel(task) }} · {{ adminTaskSubcategoryLabel(task) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <p>{{ task.farm?.name ?? '-' }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ task.block?.code ?? '-' }} · {{ task.block?.name ?? 'Blok belum diisi' }}
                                        <template v-if="task.subblok?.label"> · {{ task.subblok.label }}</template>
                                    </p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <p>{{ adminTaskHst(task.offset_days) }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ adminTaskDate(task.effective_scheduled_date) }}
                                    </p>
                                    <p
                                        v-if="task.urgency === 'overdue'"
                                        class="mt-1 text-xs font-medium text-error"
                                    >
                                        {{
                                            task.overdue_days
                                                ? `Terlambat ${task.overdue_days} hari`
                                                : 'Terlambat'
                                        }}
                                    </p>
                                    <p
                                        v-else-if="task.urgency === 'today'"
                                        class="mt-1 text-xs font-medium text-warning"
                                    >
                                        Hari ini
                                    </p>
                                    <p
                                        v-if="task.due_date"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Tenggat {{ adminTaskDate(task.due_date) }}
                                    </p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskStatusClass(task.status)">
                                            {{ adminTaskStatusLabels[task.status] }}
                                        </span>
                                        <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskUrgencyClass(task.urgency)">
                                            {{ adminTaskUrgencyLabels[task.urgency] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-1">
                                        <TaskQuickActions :task="task" />
                                        <Button as-child variant="ghost" size="icon" :aria-label="`Lihat ${task.name}`">
                                            <Link :href="`/admin/tasks/${task.id}`"><ArrowUpRight /></Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul class="divide-y divide-border md:hidden" aria-label="Daftar tugas operasional">
                    <li v-for="task in tasks.data" :key="task.id" class="p-4">
                        <Link :href="`/admin/tasks/${task.id}`" class="flex items-start justify-between gap-3 font-semibold">
                            <span>{{ task.name }}</span>
                            <ArrowUpRight class="size-4 shrink-0 text-primary" />
                        </Link>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ task.farm?.name ?? '-' }} · {{ task.block?.code ?? '-' }}
                            <template v-if="task.subblok?.label"> · {{ task.subblok.label }}</template>
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskStatusClass(task.status)">
                                {{ adminTaskStatusLabels[task.status] }}
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskUrgencyClass(task.urgency)">
                                {{ adminTaskUrgencyLabels[task.urgency] }}
                            </span>
                            <span class="inline-flex items-center gap-1 text-xs text-muted-foreground">
                                <CalendarDays class="size-3.5" />
                                {{ adminTaskDate(task.effective_scheduled_date) }}
                            </span>
                            <span
                                v-if="task.urgency === 'overdue'"
                                class="text-xs font-medium text-error"
                            >
                                {{
                                    task.overdue_days
                                        ? `Terlambat ${task.overdue_days} hari`
                                        : 'Terlambat'
                                }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <TaskQuickActions :task="task" show-labels />
                        </div>
                    </li>
                </ul>

                <div v-if="tasks.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm">
                    <p class="text-muted-foreground">
                        Menampilkan {{ tasks.from }}–{{ tasks.to }} dari {{ tasks.total }}
                    </p>
                    <div class="flex gap-2">
                        <Button v-if="tasks.prev_page_url" as-child variant="outline" size="sm">
                            <Link :href="tasks.prev_page_url">Sebelumnya</Link>
                        </Button>
                        <Button v-if="tasks.next_page_url" as-child variant="outline" size="sm">
                            <Link :href="tasks.next_page_url">Berikutnya</Link>
                        </Button>
                    </div>
                </div>
            </template>
        </section>
    </div>
</template>

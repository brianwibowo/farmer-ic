<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    LoaderCircle,
    Search,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import TaskFiltersPopover from '@/components/tasks/TaskFiltersPopover.vue';
import TaskQuickActions from '@/components/tasks/TaskQuickActions.vue';
import TaskSummaryCards from '@/components/tasks/TaskSummaryCards.vue';
import TaskViewTabs from '@/components/tasks/TaskViewTabs.vue';
import { Button } from '@/components/ui/button';
import {
    adminTaskCategoryLabel,
    adminTaskDate,
    adminTaskHst,
    adminTaskStatusClass,
    adminTaskStatusLabels,
    adminTaskUrgencyClass,
    adminTaskUrgencyLabels,
    type AdminTaskBlockOption,
    type AdminTaskCategory,
    type AdminTaskFarmOption,
    type AdminTaskFilters,
    type AdminTaskPage,
    type AdminTaskStatus,
    type AdminTaskSubblokOption,
    type AdminTaskSummary,
    type AdminTaskView,
} from '@/types/tasks';
import { sopActivityCategoryLabels } from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    tasks: AdminTaskPage;
    filters: AdminTaskFilters;
    summary: AdminTaskSummary;
    farms: AdminTaskFarmOption[];
    blocks: AdminTaskBlockOption[];
    subbloks: AdminTaskSubblokOption[];
}>();

const showFilters = ref(false);

const initialSubblok = props.subbloks.find(
    (subblok) => subblok.id === props.filters.subblok_id,
);
const initialBlockId = props.filters.block_id || initialSubblok?.block_id || '';
const initialBlock = props.blocks.find((block) => block.id === initialBlockId);
const initialFarmId = props.filters.farm_id || initialBlock?.farm_id || '';

const form = useForm({
    search: props.filters.search ?? '',
    farm_id: initialFarmId,
    block_id: initialBlockId,
    subblok_id: props.filters.subblok_id ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
    view: (props.filters.view ?? 'actionable') as AdminTaskView,
    per_page: Number(props.filters.per_page ?? 15),
});

const activeTabView = computed<AdminTaskView>(() =>
    props.filters.status ? 'all' : form.view,
);

const appliedFilters = computed(() => {
    const items: {
        key:
            | 'search'
            | 'farm_id'
            | 'block_id'
            | 'subblok_id'
            | 'status'
            | 'category';
        label: string;
    }[] = [];

    if (props.filters.search) {
        items.push({
            key: 'search',
            label: `Pencarian: ${props.filters.search}`,
        });
    }

    if (props.filters.farm_id) {
        const farmName =
            props.farms.find((farm) => farm.id === props.filters.farm_id)
                ?.name ?? 'Tidak tersedia';
        items.push({ key: 'farm_id', label: `Kebun: ${farmName}` });
    }

    if (props.filters.block_id) {
        const block = props.blocks.find(
            (item) => item.id === props.filters.block_id,
        );
        items.push({
            key: 'block_id',
            label: block
                ? `Blok: ${block.name}`
                : 'Blok: Tidak tersedia',
        });
    }

    if (props.filters.subblok_id) {
        const subblok = props.subbloks.find(
            (item) => item.id === props.filters.subblok_id,
        );
        items.push({
            key: 'subblok_id',
            label: subblok
                ? `Subblok: ${subblok.label ?? `Subblok ${subblok.number}`}`
                : 'Subblok: Tidak tersedia',
        });
    }

    if (props.filters.status) {
        const label =
            adminTaskStatusLabels[props.filters.status as AdminTaskStatus] ??
            props.filters.status;
        items.push({ key: 'status', label: `Status: ${label}` });
    }

    if (props.filters.category) {
        const label =
            sopActivityCategoryLabels[
                props.filters.category as AdminTaskCategory
            ] ?? props.filters.category;
        items.push({ key: 'category', label: `Kategori: ${label}` });
    }

    return items;
});

const filterCount = computed(
    () => appliedFilters.value.filter((item) => item.key !== 'search').length,
);

const hasFilters = computed(
    () => appliedFilters.value.length > 0 || form.view !== 'actionable',
);

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

function selectView(view: AdminTaskView): void {
    if (form.processing) return;
    form.view = view;
    form.status = '';
    apply();
}

function changeStatus(): void {
    if (form.status) {
        form.view = 'all';
    }
}

function apply(): void {
    form.get('/admin/tasks', {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onSuccess: () => {
            showFilters.value = false;
        },
        onError: (errors) => {
            if (
                errors.farm_id ||
                errors.block_id ||
                errors.subblok_id ||
                errors.status ||
                errors.category
            ) {
                showFilters.value = true;
            }
        },
    });
}

function clearFilter(
    key:
        | 'search'
        | 'farm_id'
        | 'block_id'
        | 'subblok_id'
        | 'status'
        | 'category',
): void {
    form[key] = '';
    if (key === 'farm_id') {
        form.block_id = '';
        form.subblok_id = '';
    } else if (key === 'block_id') {
        form.subblok_id = '';
    }
    apply();
}

function reset(): void {
    form.search = '';
    form.farm_id = '';
    form.block_id = '';
    form.subblok_id = '';
    form.status = '';
    form.category = '';
    form.view = 'actionable';
    form.clearErrors();
    apply();
}
</script>

<template>
    <Head title="Tugas Operasional" />
    <div class="space-y-[18px]" :aria-busy="form.processing">
        <TaskSummaryCards :summary="summary" />

        <section
            class="user-directory rounded-xl bg-card"
            aria-label="Daftar tugas operasional"
        >
            <div class="user-directory-inset">
                <TaskViewTabs
                    :active="activeTabView"
                    :summary="summary"
                    :processing="form.processing"
                    @select="selectView"
                />
            </div>

            <div class="user-directory-toolbar">
                <form
                    class="flex min-w-0 flex-1 basis-64 items-center gap-2"
                    @submit.prevent="apply"
                >
                    <div class="min-w-0 grow">
                        <label for="search-tasks" class="sr-only"
                            >Cari tugas</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            /><input
                                id="search-tasks"
                                v-model="form.search"
                                type="search"
                                class="admin-field pl-10!"
                                placeholder="Cari nama tugas, kebun, blok, atau subblok…"
                                maxlength="100"
                                :disabled="form.processing"
                                :aria-invalid="!!form.errors.search"
                                :aria-describedby="
                                    form.errors.search
                                        ? 'tasks-search-error'
                                        : undefined
                                "
                            />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        class="size-10 shrink-0 pointer-coarse:min-h-11 pointer-coarse:min-w-11"
                        aria-label="Cari tugas"
                        title="Cari tugas"
                        :disabled="form.processing"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="animate-spin motion-reduce:animate-none"
                        />
                        <Search v-else class="size-4" />
                    </Button>
                </form>

                <div class="flex shrink-0 items-center gap-2">
                    <TaskFiltersPopover
                        v-model:open="showFilters"
                        v-model:farm-id="form.farm_id"
                        v-model:block-id="form.block_id"
                        v-model:subblok-id="form.subblok_id"
                        v-model:status="form.status"
                        v-model:category="form.category"
                        :farms="farms"
                        :blocks="blocks"
                        :subbloks="subbloks"
                        :processing="form.processing"
                        :count="filterCount"
                        :errors="form.errors"
                        @status-change="changeStatus"
                        @apply="apply"
                        @reset="reset"
                    />
                </div>
            </div>

            <div
                v-if="appliedFilters.length"
                class="user-directory-inset mb-4 flex flex-wrap items-center gap-2"
                aria-label="Filter yang diterapkan"
            >
                <button
                    v-for="item in appliedFilters"
                    :key="item.key"
                    type="button"
                    class="inline-flex max-w-full items-center gap-2 rounded-md bg-accent/60 px-2.5 py-1.5 text-xs text-accent-foreground hover:bg-accent focus-visible:outline-2 focus-visible:outline-ring"
                    :disabled="form.processing"
                    :aria-label="'Hapus filter ' + item.label"
                    @click="clearFilter(item.key)"
                >
                    <span class="break-words">{{ item.label }}</span
                    ><X class="size-3.5 shrink-0" />
                </button>
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="form.processing"
                    @click="reset"
                    >Reset semua</Button
                >
            </div>

            <p
                v-if="Object.keys(form.errors).length"
                id="tasks-search-error"
                role="alert"
                class="user-directory-inset mb-4 text-sm text-error"
            >
                {{ Object.values(form.errors)[0] }}
            </p>

            <div
                v-if="form.processing"
                class="user-directory-inset space-y-3 py-5"
                role="status"
                aria-label="Memuat tugas"
            >
                <div
                    v-for="row in 3"
                    :key="row"
                    class="h-[72px] animate-pulse rounded-xl bg-muted motion-reduce:animate-none"
                />
                <p class="text-sm text-muted-foreground">Memuat tugas…</p>
            </div>

            <div v-else-if="!tasks.data.length" class="px-5 py-16 text-center">
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-full bg-accent text-primary"
                >
                    <ClipboardList class="size-6" />
                </div>
                <h2 class="mt-4 text-base font-semibold">
                    {{ emptyCopy.title }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                >
                    {{ emptyCopy.description }}
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
                    class="user-directory-inset hidden overflow-x-auto md:block"
                    role="region"
                    aria-label="Tabel tugas operasional"
                    tabindex="0"
                >
                    <table
                        class="user-directory-table w-full min-w-[820px] text-left text-sm"
                    >
                        <thead>
                            <tr>
                                <th scope="col">Tugas</th>
                                <th scope="col">Lokasi</th>
                                <th scope="col">Jadwal</th>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="task in tasks.data" :key="task.id">
                                <td>
                                    <Link
                                        :href="`/admin/tasks/${task.id}`"
                                        class="font-semibold hover:text-primary focus-visible:outline-2 focus-visible:outline-ring"
                                    >
                                        {{ task.name }}
                                    </Link>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ adminTaskCategoryLabel(task) }}
                                    </p>
                                </td>
                                <td>
                                    <p class="font-medium">
                                        {{ task.farm?.name ?? '-' }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            task.block?.name ??
                                            'Blok belum diisi'
                                        }}
                                        <template v-if="task.subblok?.label">
                                            · {{ task.subblok.label }}</template
                                        >
                                    </p>
                                </td>
                                <td class="whitespace-nowrap">
                                    <p class="font-medium">
                                        {{ adminTaskHst(task.offset_days) }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            adminTaskDate(
                                                task.effective_scheduled_date,
                                            )
                                        }}
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
                                        Tenggat
                                        {{ adminTaskDate(task.due_date) }}
                                    </p>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap"
                                            :class="
                                                adminTaskStatusClass(
                                                    task.status,
                                                )
                                            "
                                        >
                                            {{
                                                adminTaskStatusLabels[
                                                    task.status
                                                ]
                                            }}
                                        </span>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap"
                                            :class="
                                                adminTaskUrgencyClass(
                                                    task.urgency,
                                                )
                                            "
                                        >
                                            {{
                                                adminTaskUrgencyLabels[
                                                    task.urgency
                                                ]
                                            }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <TaskQuickActions :task="task" />
                                        <Button
                                            as-child
                                            variant="ghost"
                                            size="icon"
                                            class="rounded-xl"
                                            :aria-label="`Lihat ${task.name}`"
                                        >
                                            <Link
                                                :href="`/admin/tasks/${task.id}`"
                                                ><ArrowUpRight
                                            /></Link>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul
                    class="user-directory-inset space-y-3 pb-4 md:hidden"
                    aria-label="Daftar tugas operasional"
                >
                    <li
                        v-for="task in tasks.data"
                        :key="task.id"
                        class="space-y-3 rounded-xl bg-background p-4"
                    >
                        <Link
                            :href="`/admin/tasks/${task.id}`"
                            class="flex items-start justify-between gap-3 font-semibold hover:text-primary"
                        >
                            <span>{{ task.name }}</span>
                            <ArrowUpRight
                                class="size-4 shrink-0 text-primary"
                            />
                        </Link>
                        <p class="text-xs text-muted-foreground">
                            {{ adminTaskCategoryLabel(task) }} ·
                            {{ task.farm?.name ?? '-' }} ·
                            {{ task.block?.name ?? '-' }}
                            <template v-if="task.subblok?.label">
                                · {{ task.subblok.label }}</template
                            >
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="adminTaskStatusClass(task.status)"
                            >
                                {{ adminTaskStatusLabels[task.status] }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="adminTaskUrgencyClass(task.urgency)"
                            >
                                {{ adminTaskUrgencyLabels[task.urgency] }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                            >
                                <CalendarDays class="size-3.5" />
                                {{
                                    adminTaskDate(task.effective_scheduled_date)
                                }}
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
                        <div
                            v-if="task.can.update"
                            class="border-t border-border pt-3"
                        >
                            <TaskQuickActions :task="task" show-labels />
                        </div>
                    </li>
                </ul>
            </template>

            <footer class="user-directory-footer">
                <p class="text-xs text-muted-foreground" role="status">
                    {{
                        tasks.total
                            ? `Menampilkan ${tasks.from}–${tasks.to} dari ${tasks.total} tugas`
                            : '0 tugas ditemukan'
                    }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="w-36">
                        <AppSelect
                            id="tasks-page-size"
                            v-model="form.per_page"
                            class="admin-field"
                            aria-label="Jumlah tugas per halaman"
                            :disabled="form.processing"
                            @change="apply"
                        >
                            <AppSelectOption :value="15"
                                >15 / halaman</AppSelectOption
                            >
                            <AppSelectOption :value="30"
                                >30 / halaman</AppSelectOption
                            >
                            <AppSelectOption :value="50"
                                >50 / halaman</AppSelectOption
                            >
                        </AppSelect>
                    </div>
                    <nav
                        class="flex items-center gap-1"
                        aria-label="Pagination tugas"
                    >
                        <Button
                            v-if="tasks.prev_page_url"
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Halaman sebelumnya"
                        >
                            <Link :href="tasks.prev_page_url" preserve-scroll
                                ><ChevronLeft
                            /></Link>
                        </Button>
                        <Button
                            v-else
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Halaman sebelumnya"
                        >
                            <ChevronLeft />
                        </Button>
                        <span
                            aria-current="page"
                            class="px-2 text-xs font-semibold tabular-nums"
                        >
                            {{ tasks.current_page }} / {{ tasks.last_page }}
                        </span>
                        <Button
                            v-if="tasks.next_page_url"
                            as-child
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Halaman berikutnya"
                        >
                            <Link :href="tasks.next_page_url" preserve-scroll
                                ><ChevronRight
                            /></Link>
                        </Button>
                        <Button
                            v-else
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Halaman berikutnya"
                        >
                            <ChevronRight />
                        </Button>
                    </nav>
                </div>
            </footer>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ClipboardList,
    LoaderCircle,
    Pencil,
    Plus,
    Send,
    Sprout,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DeleteSopDialog from '@/components/sops/DeleteSopDialog.vue';
import SopTaskDialog from '@/components/sops/SopTaskDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    hstLabel,
    sopDate,
    sopTaskCategoryLabel,
    sopTaskSubcategoryLabel,
    type SopCan,
    type SopTask,
    type SopTemplateDetail,
    type SopVersion,
} from '@/types/sop';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    template: SopTemplateDetail;
    can: SopCan;
}>();

const taskDialogOpen = ref(false);
const taskVersion = ref<SopVersion | null>(null);
const editingTask = ref<SopTask | null>(null);
const publishingId = ref<string | null>(null);
const creatingVersion = ref(false);
const deletingTemplate = ref(false);
const deletingTaskTarget = ref<{
    version: SopVersion;
    task: SopTask;
} | null>(null);
const deletingTaskProcessing = ref(false);

const draftVersion = computed(
    () =>
        props.template.versions.find((version) => version.status === 'draft') ??
        null,
);
const publishedVersion = computed(
    () =>
        props.template.versions.find(
            (version) => version.status === 'published',
        ) ?? null,
);

const summaryMetrics = computed(() => [
    {
        label: 'Status template',
        value: props.template.is_active ? 'Aktif' : 'Nonaktif',
        unit: props.template.is_active ? 'dipakai' : 'arsip',
        icon: CheckCircle2,
    },
    {
        label: 'Total versi',
        value: props.template.versions.length,
        unit: 'versi',
        icon: ClipboardList,
    },
    {
        label: 'Versi published',
        value: publishedVersion.value
            ? `v${publishedVersion.value.version}`
            : 'Belum ada',
        unit: publishedVersion.value
            ? `${publishedVersion.value.tasks.length} langkah`
            : 'draft',
        icon: Send,
    },
]);

function openTask(version: SopVersion, task: SopTask | null = null): void {
    taskVersion.value = version;
    editingTask.value = task;
    taskDialogOpen.value = true;
}

function createVersion(): void {
    creatingVersion.value = true;
    router.post(
        `/admin/sops/${props.template.id}/versions`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (creatingVersion.value = false),
        },
    );
}

function publish(version: SopVersion): void {
    publishingId.value = version.id;
    router.post(
        `/admin/sops/${props.template.id}/versions/${version.id}/publish`,
        {},
        {
            preserveScroll: true,
            onFinish: () => (publishingId.value = null),
        },
    );
}

function promptDeleteTask(version: SopVersion, task: SopTask): void {
    deletingTaskTarget.value = { version, task };
}

function confirmDeleteTask(): void {
    if (!deletingTaskTarget.value) return;
    const { version, task } = deletingTaskTarget.value;
    deletingTaskProcessing.value = true;
    router.delete(
        `/admin/sops/${props.template.id}/versions/${version.id}/tasks/${task.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                deletingTaskTarget.value = null;
            },
            onFinish: () => {
                deletingTaskProcessing.value = false;
            },
        },
    );
}

function statusLabel(status: SopVersion['status']): string {
    return status === 'draft'
        ? 'Draft'
        : status === 'published'
          ? 'Published'
          : 'Retired';
}

function statusClass(status: SopVersion['status']): string {
    return status === 'draft'
        ? 'bg-warning/10 text-warning'
        : status === 'published'
          ? 'bg-success/10 text-success'
          : 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head :title="template.name" />
    <div class="space-y-6">
        <Link
            href="/admin/sops"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Daftar SOP
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="admin-page-title tracking-tight">
                    {{ template.name }}
                </h1>
                <div
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <Sprout class="size-4 shrink-0 text-primary" />
                        {{
                            template.commodity?.name ?? 'Komoditas belum diisi'
                        }}
                    </span>
                    <span aria-hidden="true">·</span>
                    <span>{{ template.team?.name }}</span>
                    <span
                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="
                            template.is_active
                                ? 'bg-success/10 text-success'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ template.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>
                <p
                    v-if="template.description"
                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                >
                    {{ template.description }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="can.delete"
                    variant="destructive-outline"
                    class="h-10 rounded-xl"
                    @click="deletingTemplate = true"
                >
                    <Trash2 />Hapus
                </Button>
                <Button
                    v-if="can.update"
                    as-child
                    variant="outline"
                    class="h-10 rounded-xl"
                >
                    <Link :href="`/admin/sops/${template.id}/edit`">
                        <Pencil />Ubah SOP
                    </Link>
                </Button>
                <Button
                    v-if="can.manageVersions && !draftVersion"
                    class="h-10 rounded-xl"
                    :disabled="creatingVersion"
                    @click="createVersion"
                >
                    <LoaderCircle v-if="creatingVersion" class="animate-spin" />
                    <Plus v-else />Versi baru
                </Button>
            </div>
        </header>

        <div class="grid grid-cols-3 gap-2 sm:gap-4">
            <section
                v-for="metric in summaryMetrics"
                :key="metric.label"
                class="min-w-0 rounded-xl border border-border bg-card p-3 sm:p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2
                        class="min-h-10 text-xs leading-5 text-muted-foreground sm:min-h-0 sm:text-sm"
                    >
                        {{ metric.label }}
                    </h2>
                    <component
                        :is="metric.icon"
                        class="hidden size-5 shrink-0 text-primary sm:block"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="admin-metric-value mt-2 text-xl! tracking-tight sm:mt-4 sm:text-3xl!"
                >
                    {{ metric.value }}
                    <span
                        class="ml-1 text-xs font-medium text-muted-foreground sm:ml-2 sm:text-sm"
                    >
                        {{ metric.unit }}
                    </span>
                </p>
            </section>
        </div>

        <div
            v-if="!template.versions.length"
            class="rounded-xl border border-dashed border-border bg-card px-5 py-14 text-center"
        >
            <div
                class="mx-auto flex size-14 items-center justify-center rounded-xl bg-accent text-primary"
            >
                <ClipboardList class="size-7" />
            </div>
            <h3 class="mt-4 font-semibold">Belum ada versi SOP</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                Buat versi baru untuk mulai menambahkan langkah budidaya.
            </p>
            <Button
                v-if="can.manageVersions"
                class="mt-5 rounded-xl"
                :disabled="creatingVersion"
                @click="createVersion"
            >
                <Plus />Buat versi pertama
            </Button>
        </div>

        <section
            v-for="version in template.versions"
            :key="version.id"
            class="overflow-hidden rounded-xl border border-border bg-card"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-3 border-b border-border p-4 md:p-5"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="admin-section-title">
                            Versi {{ version.version }}
                        </h2>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusClass(version.status)"
                        >
                            {{ statusLabel(version.status) }}
                        </span>
                        <span
                            class="rounded-full bg-accent px-2 py-0.5 text-xs font-medium text-accent-foreground"
                        >
                            {{ version.tasks.length }} langkah
                        </span>
                    </div>
                    <p
                        v-if="version.notes"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ version.notes }}
                    </p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Dibuat oleh {{ version.creator?.name ?? '-' }}
                        <span v-if="version.published_at">
                            · Dipublikasikan {{ sopDate(version.published_at) }}
                        </span>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="version.status === 'draft' && can.manageTasks"
                        variant="outline"
                        class="h-9 rounded-xl"
                        @click="openTask(version)"
                    >
                        <Plus />Tambah langkah
                    </Button>
                    <Button
                        v-if="version.status === 'draft'"
                        class="h-9 rounded-xl"
                        :disabled="
                            !version.tasks.length || publishingId === version.id
                        "
                        @click="publish(version)"
                    >
                        <LoaderCircle
                            v-if="publishingId === version.id"
                            class="animate-spin"
                        />
                        <Send v-else />Publikasikan
                    </Button>
                </div>
            </div>

            <div v-if="!version.tasks.length" class="px-5 py-12 text-center">
                <p class="text-sm text-muted-foreground">
                    {{
                        version.status === 'draft'
                            ? 'Belum ada langkah. Tambahkan langkah pertama untuk versi ini.'
                            : 'Versi ini tidak memiliki langkah.'
                    }}
                </p>
                <Button
                    v-if="version.status === 'draft' && can.manageTasks"
                    variant="outline"
                    class="mt-4 rounded-xl"
                    @click="openTask(version)"
                >
                    <Plus />Tambah langkah pertama
                </Button>
            </div>
            <template v-else>
                <div
                    class="hidden overflow-x-auto md:block"
                    role="region"
                    :aria-label="`Langkah versi ${version.version}`"
                    tabindex="0"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="bg-muted/50 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    HST
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Langkah
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Kategori
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Subkategori
                                </th>
                                <th scope="col" class="px-5 py-3 font-semibold">
                                    Wajib
                                </th>
                                <th
                                    v-if="
                                        version.status === 'draft' &&
                                        can.manageTasks
                                    "
                                    scope="col"
                                    class="px-5 py-3 text-right font-semibold"
                                >
                                    Tindakan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="task in version.tasks"
                                :key="task.id"
                                class="align-top hover:bg-muted/40"
                            >
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex rounded-md bg-accent px-2 py-0.5 font-mono text-xs font-semibold text-accent-foreground"
                                    >
                                        {{ hstLabel(task) }}
                                    </span>
                                    <span
                                        v-if="task.tolerance_days"
                                        class="ml-1.5 text-xs text-muted-foreground"
                                    >
                                        ±{{ task.tolerance_days }}h
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <p class="font-medium">{{ task.name }}</p>
                                    <p
                                        v-if="task.instructions"
                                        class="mt-1 max-w-xl text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ task.instructions }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    {{ sopTaskCategoryLabel(task) }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    {{ sopTaskSubcategoryLabel(task) }}
                                </td>
                                <td class="px-5 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            task.is_required
                                                ? 'bg-primary/10 text-primary'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{ task.is_required ? 'Ya' : 'Tidak' }}
                                    </span>
                                </td>
                                <td
                                    v-if="
                                        version.status === 'draft' &&
                                        can.manageTasks
                                    "
                                    class="px-5 py-4"
                                >
                                    <div class="flex justify-end gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="`Ubah ${task.name}`"
                                            @click="openTask(version, task)"
                                        >
                                            <Pencil />
                                        </Button>
                                        <Button
                                            variant="destructive-ghost"
                                            size="icon"
                                            :aria-label="`Hapus ${task.name}`"
                                            @click="
                                                promptDeleteTask(version, task)
                                            "
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <ul
                    class="divide-y divide-border md:hidden"
                    :aria-label="`Daftar langkah versi ${version.version}`"
                >
                    <li
                        v-for="task in version.tasks"
                        :key="task.id"
                        class="p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-semibold">{{ task.name }}</p>
                            <span
                                class="inline-flex shrink-0 rounded-md bg-accent px-2 py-0.5 font-mono text-xs font-semibold text-accent-foreground"
                            >
                                {{ hstLabel(task) }}
                            </span>
                        </div>
                        <p
                            v-if="task.instructions"
                            class="mt-1.5 text-xs leading-5 text-muted-foreground"
                        >
                            {{ task.instructions }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ sopTaskCategoryLabel(task) }}
                            </span>
                            <span
                                v-if="task.activity_subcategory"
                                class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground"
                            >
                                {{ sopTaskSubcategoryLabel(task) }}
                            </span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    task.is_required
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{ task.is_required ? 'Wajib' : 'Opsional' }}
                            </span>
                        </div>
                        <div
                            v-if="
                                version.status === 'draft' && can.manageTasks
                            "
                            class="mt-3 flex flex-wrap justify-end gap-2"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openTask(version, task)"
                            >
                                <Pencil />Ubah
                            </Button>
                            <Button
                                variant="destructive-ghost"
                                size="sm"
                                @click="promptDeleteTask(version, task)"
                            >
                                <Trash2 />Hapus
                            </Button>
                        </div>
                    </li>
                </ul>
            </template>
        </section>

        <p class="text-xs leading-5 text-muted-foreground">
            Versi published dibekukan. Revisi membuat versi baru; batch yang
            sudah berjalan tetap memakai snapshot versi semula.
        </p>
    </div>

    <SopTaskDialog
        v-if="taskVersion"
        v-model:open="taskDialogOpen"
        :version="taskVersion"
        :task="editingTask"
    />

    <DeleteSopDialog
        :sop="
            deletingTemplate
                ? { id: template.id, name: template.name }
                : null
        "
        @close="deletingTemplate = false"
    />

    <Dialog
        :open="!!deletingTaskTarget"
        @update:open="
            (open) => {
                if (!open && !deletingTaskProcessing)
                    deletingTaskTarget = null;
            }
        "
    >
        <DialogContent
            class="admin-dashboard-theme rounded-xl bg-card text-foreground"
            :show-close-button="!deletingTaskProcessing"
        >
            <div
                class="flex size-12 items-center justify-center rounded-xl bg-error/10 text-error"
            >
                <AlertTriangle class="size-6" />
            </div>
            <DialogTitle>Hapus langkah SOP?</DialogTitle>
            <DialogDescription class="text-muted-foreground">
                Langkah
                <strong>{{ deletingTaskTarget?.task.name }}</strong> pada versi
                {{ deletingTaskTarget?.version.version }} akan dihapus. Tindakan
                ini tidak dapat dibatalkan.
            </DialogDescription>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    class="rounded-xl"
                    :disabled="deletingTaskProcessing"
                    @click="deletingTaskTarget = null"
                >
                    Batal
                </Button>
                <Button
                    variant="destructive"
                    type="button"
                    class="rounded-xl"
                    :disabled="deletingTaskProcessing"
                    @click="confirmDeleteTask"
                >
                    <LoaderCircle
                        v-if="deletingTaskProcessing"
                        class="animate-spin"
                    />
                    {{
                        deletingTaskProcessing
                            ? 'Menghapus...'
                            : 'Ya, hapus langkah'
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

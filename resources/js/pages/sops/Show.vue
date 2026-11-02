<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardList,
    LoaderCircle,
    Pencil,
    Plus,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SopTaskDialog from '@/components/sops/SopTaskDialog.vue';
import { Button } from '@/components/ui/button';
import {
    hstLabel,
    sopDate,
    sopStageLabels,
    type SopActivityType,
    type SopCan,
    type SopTask,
    type SopTemplateDetail,
    type SopVersion,
} from '@/types/sop';

const props = defineProps<{
    template: SopTemplateDetail;
    activityTypes: SopActivityType[];
    stageCodes: string[];
    anchors: string[];
    can: SopCan;
}>();

const taskDialogOpen = ref(false);
const taskVersion = ref<SopVersion | null>(null);
const editingTask = ref<SopTask | null>(null);
const publishingId = ref<string | null>(null);
const creatingVersion = ref(false);

const draftVersion = computed(
    () => props.template.versions.find((version) => version.status === 'draft') ?? null,
);
const publishedVersion = computed(
    () => props.template.versions.find((version) => version.status === 'published') ?? null,
);

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

function deleteTask(version: SopVersion, task: SopTask): void {
    if (!window.confirm(`Hapus langkah "${task.name}" dari versi ${version.version}?`)) {
        return;
    }
    router.delete(
        `/admin/sops/${props.template.id}/versions/${version.id}/tasks/${task.id}`,
        { preserveScroll: true },
    );
}

function statusLabel(status: SopVersion['status']): string {
    return status === 'draft' ? 'Draft' : status === 'published' ? 'Published' : 'Retired';
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
        <Link href="/admin/sops" class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary">
            <ArrowLeft class="size-4" />Daftar SOP
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">Template SOP</p>
                <h1 class="admin-page-title tracking-tight">{{ template.name }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ template.team?.name }} · {{ template.variety?.name }}
                    <span v-if="template.variety?.commodity?.name"> · {{ template.variety.commodity.name }}</span>
                </p>
                <p v-if="template.description" class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                    {{ template.description }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="can.update" as-child variant="outline" class="h-10 rounded-xl">
                    <Link :href="`/admin/sops/${template.id}/edit`"><Pencil />Ubah</Link>
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

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Status template</p>
                <p class="mt-3 flex items-center gap-2 text-lg font-semibold">
                    <CheckCircle2 class="size-5" :class="template.is_active ? 'text-success' : 'text-muted-foreground'" />
                    {{ template.is_active ? 'Aktif' : 'Nonaktif' }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Versi</p>
                <p class="admin-metric-value mt-2">{{ template.versions.length }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-sm text-muted-foreground">Versi published</p>
                <p class="mt-3 text-lg font-semibold">
                    {{ publishedVersion ? `v${publishedVersion.version}` : 'Belum ada' }}
                </p>
            </div>
        </div>

        <div v-if="!template.versions.length" class="rounded-xl border border-dashed border-border bg-card px-5 py-14 text-center">
            <ClipboardList class="mx-auto size-10 text-muted-foreground" />
            <h3 class="mt-4 font-semibold">Belum ada versi SOP</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">Buat versi baru untuk mulai menambahkan langkah.</p>
            <Button v-if="can.manageVersions" class="mt-5 rounded-xl" :disabled="creatingVersion" @click="createVersion">
                <Plus />Buat versi pertama
            </Button>
        </div>

        <section v-for="version in template.versions" :key="version.id" class="overflow-hidden rounded-xl border border-border bg-card">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-border p-5">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="admin-section-title">Versi {{ version.version }}</h2>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(version.status)">
                            {{ statusLabel(version.status) }}
                        </span>
                    </div>
                    <p v-if="version.notes" class="mt-2 text-sm text-muted-foreground">{{ version.notes }}</p>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Dibuat oleh {{ version.creator?.name ?? '—' }}
                        <span v-if="version.published_at"> · Dipublikasikan {{ sopDate(version.published_at) }}</span>
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
                        :disabled="!version.tasks.length || publishingId === version.id"
                        @click="publish(version)"
                    >
                        <LoaderCircle v-if="publishingId === version.id" class="animate-spin" />
                        <Send v-else />Publikasikan
                    </Button>
                </div>
            </div>

            <div v-if="!version.tasks.length" class="px-5 py-10 text-center">
                <p class="text-sm text-muted-foreground">
                    {{ version.status === 'draft' ? 'Belum ada langkah. Tambahkan langkah pertama untuk versi ini.' : 'Versi ini tidak memiliki langkah.' }}
                </p>
            </div>
            <div v-else class="overflow-x-auto" role="region" :aria-label="`Langkah versi ${version.version}`" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">HST</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Langkah</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Stage</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Jenis</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Wajib</th>
                            <th v-if="version.status === 'draft' && can.manageTasks" scope="col" class="px-5 py-3 text-right font-semibold">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="task in version.tasks" :key="task.id" class="align-top hover:bg-muted/40">
                            <td class="px-5 py-4 font-mono text-xs whitespace-nowrap">{{ hstLabel(task) }}</td>
                            <td class="px-5 py-4">
                                <p class="font-medium">{{ task.name }}</p>
                                <p v-if="task.instructions" class="mt-1 max-w-xl text-xs leading-5 text-muted-foreground">{{ task.instructions }}</p>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">{{ sopStageLabels[task.stage_code] ?? task.stage_code }}</td>
                            <td class="px-5 py-4 whitespace-nowrap">{{ task.activity_type?.name ?? '—' }}</td>
                            <td class="px-5 py-4">{{ task.is_required ? 'Ya' : 'Tidak' }}</td>
                            <td v-if="version.status === 'draft' && can.manageTasks" class="px-5 py-4">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" :aria-label="`Ubah ${task.name}`" @click="openTask(version, task)">
                                        <Pencil />
                                    </Button>
                                    <Button variant="destructive-ghost" size="icon" :aria-label="`Hapus ${task.name}`" @click="deleteTask(version, task)">
                                        <Trash2 />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="text-xs leading-5 text-muted-foreground">
            Versi published dibekukan. Revisi membuat versi baru; batch yang sudah berjalan tetap memakai snapshot versi semula.
        </p>
    </div>

    <SopTaskDialog
        v-if="taskVersion"
        v-model:open="taskDialogOpen"
        :version="taskVersion"
        :task="editingTask"
        :activity-types="activityTypes"
        :stage-codes="stageCodes"
        :anchors="anchors"
    />
</template>

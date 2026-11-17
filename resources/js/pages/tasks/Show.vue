<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarClock,
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    ClipboardList,
    FileText,
    LandPlot,
    Layers,
    LoaderCircle,
    MapPin,
    ShieldCheck,
    SkipForward,
    Tag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    adminTaskCategoryLabel,
    adminTaskDate,
    adminTaskHst,
    adminTaskStatusClass,
    adminTaskStatusLabels,
    adminTaskUrgencyClass,
    adminTaskUrgencyLabels,
    type AdminTask,
} from '@/types/tasks';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ task: AdminTask }>();

const skipOpen = ref(false);
const rescheduleOpen = ref(false);

const skipForm = useForm({ reason: '' });
const rescheduleForm = useForm({
    rescheduled_date: '',
    reschedule_reason: '',
});

const hasNotes = computed(
    () =>
        Boolean(
            props.task.skip_reason ||
                props.task.completion_note ||
                props.task.reschedule_reason,
        ),
);

const locationSummary = computed(() => {
    const parts = [
        props.task.farm?.name,
        props.task.block?.code,
        props.task.subblok?.label ??
            (props.task.subblok?.number
                ? `Subblok ${props.task.subblok.number}`
                : null),
    ].filter(Boolean);

    return parts.join(' · ') || '-';
});

function statusError(errors: object): string | undefined {
    return (errors as { status?: string }).status;
}

function openSkip(): void {
    skipForm.reset();
    skipForm.clearErrors();
    skipOpen.value = true;
}

function openReschedule(): void {
    rescheduleForm.rescheduled_date =
        props.task.effective_scheduled_date ?? '';
    rescheduleForm.reschedule_reason = '';
    rescheduleForm.clearErrors();
    rescheduleOpen.value = true;
}

function submitSkip(): void {
    skipForm.post(`/admin/tasks/${props.task.id}/skip`, {
        preserveScroll: true,
        onSuccess: () => {
            skipOpen.value = false;
            skipForm.reset();
        },
    });
}

function submitReschedule(): void {
    rescheduleForm.post(`/admin/tasks/${props.task.id}/reschedule`, {
        preserveScroll: true,
        onSuccess: () => {
            rescheduleOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="task.name" />
    <div class="space-y-[18px]">
        <Link
            href="/admin/tasks"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Kembali ke daftar tugas
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-accent text-primary"
                >
                    <ClipboardList class="size-7" aria-hidden="true" />
                </div>
                <div class="min-w-0">
                    <h1
                        class="admin-page-title tracking-tight break-words text-primary"
                    >
                        {{ task.name }}
                    </h1>
                    <p class="mt-1.5 text-sm text-muted-foreground">
                        {{ adminTaskCategoryLabel(task) }} ·
                        {{ adminTaskHst(task.offset_days) }} ·
                        {{ locationSummary }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
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
                            v-if="task.urgency === 'overdue' && task.overdue_days"
                            class="inline-flex items-center gap-1 rounded-full bg-error/10 px-2.5 py-1 text-xs font-medium text-error"
                        >
                            <AlertTriangle class="size-3" aria-hidden="true" />
                            Terlambat {{ task.overdue_days }} hari
                        </span>
                    </div>
                </div>
            </div>

            <div v-if="task.can.update" class="flex flex-wrap gap-2">
                <Button class="h-10 rounded-xl" @click="openReschedule">
                    <CalendarClock />Jadwalkan ulang
                </Button>
                <Button
                    variant="outline"
                    class="h-10 rounded-xl"
                    @click="openSkip"
                >
                    <SkipForward />Tidak dikerjakan
                </Button>
            </div>
        </header>

        <div
            class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"
        >
            <div class="min-w-0 space-y-[18px]">
                <section class="admin-panel space-y-5">
                    <h2 class="admin-section-title flex items-center gap-2">
                        <ClipboardCheck
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Informasi tugas
                    </h2>

                    <dl class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <LandPlot class="size-3.5 text-primary" />Kebun
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{ task.farm?.name ?? '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <MapPin class="size-3.5 text-primary" />Blok &
                                subblok
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{ task.block?.code ?? '-' }} ·
                                {{ task.block?.name ?? '-' }}
                                <template v-if="task.subblok?.label">
                                    · {{ task.subblok.label }}</template
                                >
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Tag class="size-3.5 text-primary" />Kategori
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{ adminTaskCategoryLabel(task) }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="size-3.5 text-primary"
                                />Sifat tugas
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{ task.is_required ? 'Wajib dikerjakan' : 'Opsional' }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Layers class="size-3.5 text-primary" />Tahap
                                (Stage)
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{ task.stage?.code ?? '-' }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <FileText class="size-3.5 text-primary" />Versi
                                SOP
                            </dt>
                            <dd class="mt-1.5 font-medium">
                                {{
                                    task.sop_version
                                        ? `Versi ${task.sop_version.version}`
                                        : '-'
                                }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-panel space-y-3">
                    <h2 class="admin-section-title flex items-center gap-2">
                        <FileText
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Instruksi kerja
                    </h2>
                    <p
                        v-if="task.instructions"
                        class="text-sm leading-7 whitespace-pre-line text-muted-foreground"
                    >
                        {{ task.instructions }}
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Tidak ada instruksi tambahan untuk tugas ini.
                    </p>
                </section>

                <section v-if="hasNotes" class="admin-panel space-y-4">
                    <h2 class="admin-section-title flex items-center gap-2">
                        <CheckCircle2
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Catatan & riwayat perubahan
                    </h2>

                    <div
                        v-if="task.completion_note"
                        class="rounded-xl border border-success/30 bg-success/10 p-4"
                    >
                        <p class="text-xs font-semibold text-success">
                            Catatan penyelesaian
                        </p>
                        <p class="mt-1 text-sm leading-6">
                            {{ task.completion_note }}
                        </p>
                    </div>

                    <div
                        v-if="task.reschedule_reason"
                        class="rounded-xl border border-warning/30 bg-warning/10 p-4"
                    >
                        <p class="text-xs font-semibold text-warning">
                            Alasan penjadwalan ulang
                        </p>
                        <p class="mt-1 text-sm leading-6">
                            {{ task.reschedule_reason }}
                        </p>
                        <p
                            v-if="task.rescheduled_at"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Diubah {{ adminTaskDate(task.rescheduled_at) }}
                        </p>
                    </div>

                    <div
                        v-if="task.skip_reason"
                        class="rounded-xl border border-warning/30 bg-warning/10 p-4"
                    >
                        <p class="text-xs font-semibold text-warning">
                            Alasan tidak dikerjakan
                        </p>
                        <p class="mt-1 text-sm leading-6">
                            {{ task.skip_reason }}
                        </p>
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-[18px]">
                <section class="admin-panel space-y-5">
                    <h2 class="admin-section-title flex items-center gap-2">
                        <CalendarDays
                            class="size-5 text-primary"
                            aria-hidden="true"
                        />Jadwal & tenggat
                    </h2>

                    <dl class="space-y-4 text-sm">
                        <div class="rounded-xl bg-background p-3.5">
                            <dt class="text-xs text-muted-foreground">
                                Jadwal berlaku
                            </dt>
                            <dd
                                class="mt-1 flex flex-wrap items-center gap-2 text-base font-semibold"
                            >
                                <span>{{
                                    adminTaskDate(task.effective_scheduled_date)
                                }}</span>
                                <span
                                    v-if="task.rescheduled_date"
                                    class="rounded-full bg-warning/10 px-2 py-0.5 text-[11px] font-medium text-warning"
                                >
                                    Disesuaikan
                                </span>
                            </dd>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Acuan tanam {{ adminTaskHst(task.offset_days) }}
                            </p>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-muted-foreground">
                                Tenggat
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ adminTaskDate(task.due_date) }}
                            </dd>
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-xs text-muted-foreground">
                                Jadwal SOP asli
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ adminTaskDate(task.scheduled_date) }}
                            </dd>
                        </div>

                        <div
                            v-if="task.completed_at"
                            class="flex items-center justify-between gap-3 border-t border-border pt-3"
                        >
                            <dt class="text-xs text-muted-foreground">
                                Ditutup pada
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ adminTaskDate(task.completed_at) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="admin-panel space-y-4">
                    <h2 class="admin-section-title">Tindakan</h2>
                    <p
                        v-if="task.can.update"
                        class="text-sm leading-6 text-muted-foreground"
                    >
                        Tugas masih terbuka. Anda dapat menyesuaikan jadwal
                        berlaku atau menutup tugas jika tidak dikerjakan.
                    </p>
                    <p v-else class="text-sm leading-6 text-muted-foreground">
                        Tugas ini tidak dapat diubah karena status sudah ditutup
                        atau di luar izin akses Anda.
                    </p>

                    <div v-if="task.can.update" class="space-y-2">
                        <Button
                            class="h-10 w-full rounded-xl"
                            @click="openReschedule"
                        >
                            <CalendarClock />Jadwalkan ulang
                        </Button>
                        <Button
                            variant="outline"
                            class="h-10 w-full rounded-xl"
                            @click="openSkip"
                        >
                            <SkipForward />Tandai tidak dikerjakan
                        </Button>
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <Dialog v-model:open="skipOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Tandai tugas tidak dikerjakan?</DialogTitle>
                <DialogDescription>
                    Tugas akan ditutup sebagai tidak dikerjakan. Alasan tetap
                    tersimpan.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitSkip">
                <FarmField
                    id="skip-reason"
                    label="Alasan"
                    required
                    :error="skipForm.errors.reason"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="skipForm.reason"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Contoh: Lahan tergenang setelah hujan deras."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
                <p
                    v-if="statusError(skipForm.errors)"
                    role="alert"
                    class="text-sm text-error"
                >
                    {{ statusError(skipForm.errors) }}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="skipForm.processing"
                        @click="skipOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="skipForm.processing">
                        <LoaderCircle
                            v-if="skipForm.processing"
                            class="animate-spin"
                        />
                        <SkipForward v-else />Tidak dikerjakan
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="rescheduleOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Jadwalkan ulang tugas</DialogTitle>
                <DialogDescription>
                    Tanggal SOP asli tetap disimpan. Tanggal baru menjadi
                    jadwal berlaku dan tenggat dihitung ulang dari toleransi
                    SOP.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitReschedule">
                <FarmField
                    id="reschedule-date"
                    label="Tanggal baru"
                    required
                    :hint="
                        task.activity_category === 'harvest' &&
                        task.subblok?.planted_at
                            ? `Tidak boleh sebelum tanggal tanam ${adminTaskDate(task.subblok.planted_at)}.`
                            : undefined
                    "
                    :error="rescheduleForm.errors.rescheduled_date"
                    v-slot="field"
                >
                    <input
                        :id="field.id"
                        v-model="rescheduleForm.rescheduled_date"
                        class="admin-field"
                        type="date"
                        :min="
                            task.activity_category === 'harvest'
                                ? (task.subblok?.planted_at ?? undefined)
                                : undefined
                        "
                        required
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
                <FarmField
                    id="reschedule-reason"
                    label="Alasan"
                    required
                    :error="rescheduleForm.errors.reschedule_reason"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="rescheduleForm.reschedule_reason"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Contoh: Pekerjaan tertunda karena banjir."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
                <p
                    v-if="statusError(rescheduleForm.errors)"
                    role="alert"
                    class="text-sm text-error"
                >
                    {{ statusError(rescheduleForm.errors) }}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="rescheduleForm.processing"
                        @click="rescheduleOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="rescheduleForm.processing">
                        <LoaderCircle
                            v-if="rescheduleForm.processing"
                            class="animate-spin"
                        />
                        <CalendarClock v-else />Simpan jadwal
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

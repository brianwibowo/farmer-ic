<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarClock,
    LoaderCircle,
    RotateCcw,
    SkipForward,
    XCircle,
} from '@lucide/vue';
import { ref } from 'vue';
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
    adminTaskSubcategoryLabel,
    adminTaskUrgencyClass,
    adminTaskUrgencyLabels,
    type AdminTask,
} from '@/types/tasks';

const props = defineProps<{ task: AdminTask }>();

const skipOpen = ref(false);
const cancelOpen = ref(false);
const rescheduleOpen = ref(false);

const skipForm = useForm({ reason: '' });
const cancelForm = useForm({ reason: '' });
const rescheduleForm = useForm({
    rescheduled_date: '',
    reschedule_reason: '',
});

function openSkip(): void {
    skipForm.reset();
    skipForm.clearErrors();
    skipOpen.value = true;
}

function openCancel(): void {
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelOpen.value = true;
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

function submitCancel(): void {
    cancelForm.post(`/admin/tasks/${props.task.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
            cancelForm.reset();
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
    <div class="space-y-6">
        <Link
            href="/admin/tasks"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-primary"
        >
            <ArrowLeft class="size-4" />Daftar tugas
        </Link>

        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="mb-2 text-xs font-semibold tracking-widest text-primary uppercase">
                    Detail tugas
                </p>
                <h1 class="admin-page-title tracking-tight">{{ task.name }}</h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ adminTaskCategoryLabel(task) }} · {{ adminTaskSubcategoryLabel(task) }}
                    · {{ adminTaskHst(task.offset_days) }}
                </p>
            </div>
            <div v-if="task.can.update" class="flex flex-wrap gap-2">
                <Button variant="outline" class="h-10 rounded-xl" @click="openReschedule">
                    <CalendarClock />Jadwalkan ulang
                </Button>
                <Button variant="outline" class="h-10 rounded-xl" @click="openSkip">
                    <SkipForward />Lewati
                </Button>
                <Button variant="destructive-outline" class="h-10 rounded-xl" @click="openCancel">
                    <XCircle />Batalkan
                </Button>
            </div>
        </header>

        <div class="grid items-start gap-5 lg:grid-cols-3">
            <section class="rounded-xl border border-border bg-card p-5 md:p-6 lg:col-span-2">
                <h2 class="admin-section-title">Informasi tugas</h2>
                <dl class="mt-6 grid gap-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-1.5">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskStatusClass(task.status)">
                                {{ adminTaskStatusLabels[task.status] }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Urgensi</dt>
                        <dd class="mt-1.5">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="adminTaskUrgencyClass(task.urgency)">
                                {{ adminTaskUrgencyLabels[task.urgency] }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Kebun</dt>
                        <dd class="mt-1.5 font-medium">{{ task.farm?.name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Blok / subblok</dt>
                        <dd class="mt-1.5 font-medium">
                            {{ task.block?.code ?? '-' }} · {{ task.block?.name ?? '-' }}
                            <template v-if="task.subblok?.label"> · {{ task.subblok.label }}</template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Stage</dt>
                        <dd class="mt-1.5 font-medium">{{ task.stage?.code ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Versi SOP</dt>
                        <dd class="mt-1.5 font-medium">
                            {{ task.sop_version ? `Versi ${task.sop_version.version}` : '-' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Jadwal SOP</dt>
                        <dd class="mt-1.5 font-medium">{{ adminTaskDate(task.scheduled_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Jadwal berlaku</dt>
                        <dd class="mt-1.5 font-medium">
                            {{ adminTaskDate(task.effective_scheduled_date) }}
                            <span v-if="task.rescheduled_date" class="ml-2 rounded-full bg-warning/10 px-2 py-0.5 text-[11px] font-medium text-warning">
                                Disesuaikan
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Tenggat</dt>
                        <dd class="mt-1.5 font-medium">{{ adminTaskDate(task.due_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Wajib</dt>
                        <dd class="mt-1.5 font-medium">{{ task.is_required ? 'Ya' : 'Tidak' }}</dd>
                    </div>
                    <div v-if="task.instructions" class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">Instruksi</dt>
                        <dd class="mt-1.5 whitespace-pre-line text-sm leading-6">{{ task.instructions }}</dd>
                    </div>
                </dl>

                <div v-if="task.skip_reason" class="mt-6 rounded-xl border border-warning/30 bg-warning/10 p-4">
                    <p class="text-xs font-semibold text-warning">Alasan dilewati</p>
                    <p class="mt-1 text-sm">{{ task.skip_reason }}</p>
                </div>
                <div v-if="task.cancel_reason" class="mt-6 rounded-xl border border-error/30 bg-error/10 p-4">
                    <p class="text-xs font-semibold text-error">Alasan dibatalkan</p>
                    <p class="mt-1 text-sm">{{ task.cancel_reason }}</p>
                </div>
                <div v-if="task.reschedule_reason" class="mt-6 rounded-xl border border-warning/30 bg-warning/10 p-4">
                    <p class="text-xs font-semibold text-warning">Alasan penjadwalan ulang</p>
                    <p class="mt-1 text-sm">{{ task.reschedule_reason }}</p>
                    <p v-if="task.rescheduled_at" class="mt-2 text-xs text-muted-foreground">
                        Diubah {{ adminTaskDate(task.rescheduled_at) }}
                    </p>
                </div>
            </section>

            <section class="rounded-xl border border-border bg-card p-5">
                <h2 class="admin-section-title">Tindakan</h2>
                <p v-if="task.can.update" class="mt-2 text-sm leading-6 text-muted-foreground">
                    Tugas masih terbuka. Gunakan tindakan di atas untuk menjadwalkan ulang,
                    melewati, atau membatalkan tugas.
                </p>
                <p v-else class="mt-2 text-sm leading-6 text-muted-foreground">
                    Tugas ini tidak dapat diubah karena statusnya sudah
                    {{ adminTaskStatusLabels[task.status].toLowerCase() }}.
                </p>
                <Button v-if="task.can.update" variant="outline" class="mt-4 h-10 w-full rounded-xl" @click="openReschedule">
                    <RotateCcw />Ubah jadwal
                </Button>
            </section>
        </div>
    </div>

    <Dialog v-model:open="skipOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Lewati tugas?</DialogTitle>
                <DialogDescription>
                    Tugas akan ditandai dilewati dan tidak dihitung sebagai pekerjaan selesai.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitSkip">
                <FarmField id="skip-reason" label="Alasan" required :error="skipForm.errors.reason" v-slot="field">
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
                <p v-if="skipForm.errors.status" role="alert" class="text-sm text-error">{{ skipForm.errors.status }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="skipForm.processing" @click="skipOpen = false">Batal</Button>
                    <Button type="submit" :disabled="skipForm.processing">
                        <LoaderCircle v-if="skipForm.processing" class="animate-spin" />
                        <SkipForward v-else />Lewati tugas
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="cancelOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Batalkan tugas?</DialogTitle>
                <DialogDescription>
                    Tugas akan ditandai dibatalkan. Tindakan ini tidak menghapus histori.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCancel">
                <FarmField id="cancel-reason" label="Alasan pembatalan" required :error="cancelForm.errors.reason" v-slot="field">
                    <textarea
                        :id="field.id"
                        v-model="cancelForm.reason"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Contoh: Subblok dibatalkan karena perubahan rencana tanam."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
                <p v-if="cancelForm.errors.status" role="alert" class="text-sm text-error">{{ cancelForm.errors.status }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="cancelForm.processing" @click="cancelOpen = false">Batal</Button>
                    <Button type="submit" variant="destructive" :disabled="cancelForm.processing">
                        <LoaderCircle v-if="cancelForm.processing" class="animate-spin" />
                        <XCircle v-else />Batalkan tugas
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
                    Tanggal SOP asli tetap disimpan. Tanggal baru menjadi jadwal berlaku
                    dan tenggat dihitung ulang dari toleransi SOP.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitReschedule">
                <FarmField id="reschedule-date" label="Tanggal baru" required :error="rescheduleForm.errors.rescheduled_date" v-slot="field">
                    <input
                        :id="field.id"
                        v-model="rescheduleForm.rescheduled_date"
                        class="admin-field"
                        type="date"
                        required
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>
                <FarmField id="reschedule-reason" label="Alasan" required :error="rescheduleForm.errors.reschedule_reason" v-slot="field">
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
                <p v-if="rescheduleForm.errors.status" role="alert" class="text-sm text-error">{{ rescheduleForm.errors.status }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="rescheduleForm.processing" @click="rescheduleOpen = false">Batal</Button>
                    <Button type="submit" :disabled="rescheduleForm.processing">
                        <LoaderCircle v-if="rescheduleForm.processing" class="animate-spin" />
                        <CalendarClock v-else />Simpan jadwal
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

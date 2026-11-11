<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CalendarClock, LoaderCircle, SkipForward } from '@lucide/vue';
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
import type { AdminTask } from '@/types/tasks';

const props = withDefaults(
    defineProps<{
        task: AdminTask;
        showLabels?: boolean;
    }>(),
    { showLabels: false },
);

const skipOpen = ref(false);
const rescheduleOpen = ref(false);

const skipForm = useForm({ reason: '' });
const rescheduleForm = useForm({
    rescheduled_date: '',
    reschedule_reason: '',
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
    <div class="flex flex-wrap items-center gap-1">
        <Button
            v-if="task.can.update"
            type="button"
            :variant="showLabels ? 'outline' : 'ghost'"
            :size="showLabels ? 'sm' : 'icon'"
            class="rounded-xl"
            aria-label="Jadwalkan ulang tugas"
            title="Jadwalkan ulang"
            @click="openReschedule"
        >
            <CalendarClock />
            <span v-if="showLabels">Jadwalkan</span>
        </Button>
        <Button
            v-if="task.can.update"
            type="button"
            :variant="showLabels ? 'outline' : 'ghost'"
            :size="showLabels ? 'sm' : 'icon'"
            class="rounded-xl"
            aria-label="Tandai tugas tidak dikerjakan"
            title="Tandai tidak dikerjakan"
            @click="openSkip"
        >
            <SkipForward />
            <span v-if="showLabels">Lewati</span>
        </Button>
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
                    :id="`skip-reason-${task.id}`"
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
                    :id="`reschedule-date-${task.id}`"
                    label="Tanggal baru"
                    required
                    :error="rescheduleForm.errors.rescheduled_date"
                    v-slot="field"
                >
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
                <FarmField
                    :id="`reschedule-reason-${task.id}`"
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
                    <Button
                        type="submit"
                        :disabled="rescheduleForm.processing"
                    >
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

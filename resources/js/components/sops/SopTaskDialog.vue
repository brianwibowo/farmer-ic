<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { watch } from 'vue';
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
import { sopStageLabels, type SopActivityType, type SopTask, type SopVersion } from '@/types/sop';

const props = defineProps<{
    open: boolean;
    version: SopVersion;
    task: SopTask | null;
    activityTypes: SopActivityType[];
    stageCodes: string[];
    anchors: string[];
}>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

const form = useForm({
    activity_type_id: '',
    stage_code: 'planting',
    name: '',
    instructions: '',
    schedule_anchor: 'planting_date',
    offset_days: 0,
    tolerance_days: 2,
    position: 1,
    is_required: true,
});

function reset(): void {
    form.clearErrors();
    form.activity_type_id = props.task?.activity_type_id ?? '';
    form.stage_code = props.task?.stage_code ?? 'planting';
    form.name = props.task?.name ?? '';
    form.instructions = props.task?.instructions ?? '';
    form.schedule_anchor = props.task?.schedule_anchor ?? 'planting_date';
    form.offset_days = props.task?.offset_days ?? 0;
    form.tolerance_days = props.task?.tolerance_days ?? 2;
    form.position = props.task?.position ?? props.version.tasks.length + 1;
    form.is_required = props.task?.is_required ?? true;
}

watch(
    () => props.open,
    (open) => {
        if (open) reset();
    },
);

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => close(),
    };
    if (props.task) {
        form.put(
            `/admin/sops/${props.version.sop_template_id}/versions/${props.version.id}/tasks/${props.task.id}`,
            options,
        );
    } else {
        form.post(
            `/admin/sops/${props.version.sop_template_id}/versions/${props.version.id}/tasks`,
            options,
        );
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="admin-dashboard-theme max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ task ? 'Ubah langkah SOP' : 'Tambah langkah SOP' }}</DialogTitle>
                <DialogDescription>
                    Langkah ini tersimpan pada versi {{ version.version }} yang masih draft.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FarmField id="task-name" label="Nama langkah" required :error="form.errors.name" class="sm:col-span-2" v-slot="field">
                        <input
                            :id="field.id"
                            v-model="form.name"
                            class="admin-field"
                            required
                            maxlength="200"
                            placeholder="Contoh: Pemupukan susulan pertama"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>
                    <FarmField id="task-type" label="Jenis aktivitas" required :error="form.errors.activity_type_id" v-slot="field">
                        <select
                            :id="field.id"
                            v-model="form.activity_type_id"
                            class="admin-field"
                            required
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        >
                            <option value="" disabled>Pilih jenis</option>
                            <option v-for="type in activityTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="task-stage" label="Stage" required :error="form.errors.stage_code" v-slot="field">
                        <select :id="field.id" v-model="form.stage_code" class="admin-field" required>
                            <option v-for="stage in stageCodes" :key="stage" :value="stage">
                                {{ sopStageLabels[stage] ?? stage }}
                            </option>
                        </select>
                    </FarmField>
                    <FarmField id="task-anchor" label="Acuan jadwal" required :error="form.errors.schedule_anchor" v-slot="field">
                        <select :id="field.id" v-model="form.schedule_anchor" class="admin-field" required>
                            <option value="planting_date">Tanggal tanam (HST)</option>
                            <option value="stage_start">Mulai stage</option>
                        </select>
                    </FarmField>
                    <FarmField id="task-offset" label="Offset hari" required :error="form.errors.offset_days" hint="H+0 adalah tanggal acuan. Gunakan angka negatif untuk pekerjaan sebelum tanam." v-slot="field">
                        <input
                            :id="field.id"
                            v-model.number="form.offset_days"
                            class="admin-field"
                            type="number"
                            required
                            min="-3650"
                            max="3650"
                            step="1"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>
                    <FarmField id="task-tolerance" label="Toleransi (hari)" required :error="form.errors.tolerance_days" v-slot="field">
                        <input
                            :id="field.id"
                            v-model.number="form.tolerance_days"
                            class="admin-field"
                            type="number"
                            required
                            min="0"
                            max="365"
                            step="1"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>
                    <FarmField id="task-position" label="Urutan" required :error="form.errors.position" v-slot="field">
                        <input
                            :id="field.id"
                            v-model.number="form.position"
                            class="admin-field"
                            type="number"
                            required
                            min="1"
                            max="999"
                            step="1"
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>
                    <FarmField id="task-instructions" label="Instruksi" :error="form.errors.instructions" class="sm:col-span-2" v-slot="field">
                        <textarea
                            :id="field.id"
                            v-model="form.instructions"
                            class="admin-field resize-y"
                            rows="4"
                            maxlength="5000"
                            placeholder="Petunjuk pelaksanaan, dosis, atau hal yang perlu diperiksa."
                            :aria-invalid="field.invalid"
                            :aria-describedby="field.description"
                        />
                    </FarmField>
                    <label class="flex items-center gap-3 text-sm sm:col-span-2">
                        <input v-model="form.is_required" type="checkbox" class="size-4 accent-primary" />
                        Wajib diselesaikan sebelum stage dianggap selesai
                    </label>
                </div>

                <p v-if="form.hasErrors" role="alert" class="rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error">
                    Periksa isian yang ditandai.
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="close">Batal</Button>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="animate-spin" />
                        <Save v-else />{{ task ? 'Simpan perubahan' : 'Tambah langkah' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

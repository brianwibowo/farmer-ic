<script setup lang="ts">
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Save } from '@lucide/vue';
import { computed, watch } from 'vue';
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
    sopActivityCategoryLabels,
    sopActivitySubcategoryLabels,
    type SopActivityCategory,
    type SopActivitySubcategory,
    type SopTask,
    type SopVersion,
} from '@/types/sop';

const props = defineProps<{
    open: boolean;
    version: SopVersion;
    task: SopTask | null;
}>();
const emit = defineEmits<{ 'update:open': [boolean] }>();

const categoryOptions = Object.entries(sopActivityCategoryLabels) as [
    SopActivityCategory,
    string,
][];
const subcategoryOptions = Object.entries(sopActivitySubcategoryLabels) as [
    SopActivitySubcategory,
    string,
][];

const form = useForm({
    activity_category: 'maintenance' as SopActivityCategory,
    activity_subcategory: '' as SopActivitySubcategory | '',
    name: '',
    instructions: '',
    offset_days: 0,
    tolerance_days: 2,
    is_required: true,
});

const needsSubcategory = computed(
    () => form.activity_category === 'maintenance',
);

function reset(): void {
    form.clearErrors();
    form.activity_category = props.task?.activity_category ?? 'maintenance';
    form.activity_subcategory = props.task?.activity_subcategory ?? '';
    form.name = props.task?.name ?? '';
    form.instructions = props.task?.instructions ?? '';
    form.offset_days = props.task?.offset_days ?? 0;
    form.tolerance_days = props.task?.tolerance_days ?? 2;
    form.is_required = props.task?.is_required ?? true;
}

watch(
    () => props.open,
    (open) => {
        if (open) reset();
    },
);

watch(
    () => form.activity_category,
    (category) => {
        if (category !== 'maintenance') {
            form.activity_subcategory = '';
        }
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
        <DialogContent
            class="admin-dashboard-theme flex max-h-[90dvh] flex-col overflow-hidden rounded-xl bg-card text-foreground sm:max-w-2xl"
        >
            <DialogHeader class="shrink-0">
                <DialogTitle>{{
                    task ? 'Ubah langkah SOP' : 'Tambah langkah SOP'
                }}</DialogTitle>
                <DialogDescription>
                    SOP mencakup langkah perawatan sampai panen pada versi
                    {{ version.version }} yang masih draft.
                </DialogDescription>
            </DialogHeader>

            <form
                class="flex min-h-0 flex-1 flex-col gap-4"
                @submit.prevent="submit"
            >
                <div class="min-h-0 flex-1 overflow-y-auto px-1.5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <FarmField
                            id="task-name"
                            label="Nama langkah"
                            required
                            :error="form.errors.name"
                            class="sm:col-span-2"
                            v-slot="field"
                        >
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
                        <FarmField
                            id="task-category"
                            label="Kategori aktivitas"
                            required
                            :error="form.errors.activity_category"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="form.activity_category"
                                class="admin-field"
                                required
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption
                                    v-for="[value, label] in categoryOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </AppSelectOption>
                            </AppSelect>
                        </FarmField>
                        <FarmField
                            v-if="needsSubcategory"
                            id="task-subcategory"
                            label="Subkategori aktivitas"
                            required
                            :error="form.errors.activity_subcategory"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="form.activity_subcategory"
                                class="admin-field"
                                required
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            >
                                <AppSelectOption value="" disabled
                                    >Pilih subkategori</AppSelectOption
                                >
                                <AppSelectOption
                                    v-for="[value, label] in subcategoryOptions"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ label }}
                                </AppSelectOption>
                            </AppSelect>
                        </FarmField>
                        <FarmField
                            id="task-offset"
                            label="Offset HST (hari)"
                            required
                            :error="form.errors.offset_days"
                            hint="H+0 adalah tanggal tanam. Offset dihitung dari tanggal tanam dan tidak negatif."
                            v-slot="field"
                        >
                            <input
                                :id="field.id"
                                v-model.number="form.offset_days"
                                class="admin-field"
                                type="number"
                                required
                                min="0"
                                max="3650"
                                step="1"
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>
                        <FarmField
                            id="task-tolerance"
                            label="Toleransi (hari)"
                            required
                            :error="form.errors.tolerance_days"
                            v-slot="field"
                        >
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
                        <FarmField
                            id="task-instructions"
                            label="Instruksi"
                            :error="form.errors.instructions"
                            class="sm:col-span-2"
                            v-slot="field"
                        >
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
                        <label
                            for="task-is-required"
                            class="flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-border bg-background px-3 text-sm transition-colors hover:bg-muted/30 sm:col-span-2"
                        >
                            <input
                                id="task-is-required"
                                v-model="form.is_required"
                                type="checkbox"
                                class="size-4 accent-primary"
                            />
                            Wajib diselesaikan sebelum tahap batch terkait
                            dianggap selesai
                        </label>
                    </div>

                    <p
                        v-if="form.hasErrors"
                        role="alert"
                        class="mt-4 rounded-xl border border-error/30 bg-error/10 p-3 text-sm text-error"
                    >
                        Periksa isian yang ditandai.
                    </p>
                </div>

                <DialogFooter class="shrink-0 border-t border-border pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl"
                        :disabled="form.processing"
                        @click="close"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        class="rounded-xl"
                        :disabled="form.processing"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="animate-spin"
                        />
                        <Save v-else />{{
                            task ? 'Simpan perubahan' : 'Tambah langkah'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

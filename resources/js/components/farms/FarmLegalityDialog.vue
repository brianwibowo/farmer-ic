<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    FileText,
    LoaderCircle,
    ShieldCheck,
    Trash2,
    Upload,
    X,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatLegalityFileSize } from '@/types/farm-legality';
import type {
    FarmLegalityDetail,
    FarmLegalityLimits,
    FarmLegalityPermissions,
    FarmLegalityUrls,
} from '@/types/farm-legality';
import type { LandTenure } from '@/types/farms';

const props = defineProps<{
    open: boolean;
    farm: { id: string; name: string; land_tenure: LandTenure | null };
    legality: FarmLegalityDetail | null;
    permissions: FarmLegalityPermissions;
    urls: FarmLegalityUrls;
    limits: FarmLegalityLimits;
}>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const form = useForm({
    pks_started_at: '',
    pks_ends_at: '',
    institution: '',
    summary: '',
    documents: [] as File[],
    remove_document_ids: [] as string[],
});
const deletion = useForm({});
const confirmingDelete = ref(false);
const errorSummary = ref<HTMLElement | null>(null);
const busy = computed(() => form.processing || deletion.processing);
const canSave = computed(() =>
    props.legality ? props.permissions.update : props.permissions.create,
);
const datesRequired = computed(() => props.farm.land_tenure !== 'owned');
const remainingDocuments = computed(() =>
    (props.legality?.documents ?? []).filter(
        (document) => !form.remove_document_ids.includes(document.id),
    ),
);
const errors = computed(() =>
    Object.entries(form.errors as Record<string, string>).map(
        ([field, message]) => ({ field, message }),
    ),
);
const deleteError = computed(() =>
    Object.values(deletion.errors as Record<string, string>).join(' '),
);

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.defaults({
            pks_started_at: props.legality?.pks_started_at ?? '',
            pks_ends_at: props.legality?.pks_ends_at ?? '',
            institution: props.legality?.institution ?? '',
            summary: props.legality?.summary ?? '',
            documents: [],
            remove_document_ids: [],
        });
        form.reset();
        form.clearErrors();
        deletion.clearErrors();
        confirmingDelete.value = false;
    },
);

function close(open: boolean): void {
    if (busy.value) return;
    if (
        !open &&
        form.isDirty &&
        !window.confirm(
            'Tutup dan buang perubahan legalitas yang belum disimpan?',
        )
    )
        return;
    emit('update:open', open);
}
function validateFiles(files: File[]): string | null {
    if (files.length + remainingDocuments.value.length > props.limits.max_files)
        return `Maksimal ${props.limits.max_files} draft PDF per lahan. Hapus draft lama sebelum menambah lagi.`;
    if (
        files.some(
            (file) =>
                !/\.pdf$/i.test(file.name) ||
                (file.type && file.type !== 'application/pdf'),
        )
    )
        return 'Draft legalitas hanya menerima file PDF.';
    if (
        files.some(
            (file) => file.size > props.limits.max_file_size_mb * 1024 * 1024,
        )
    )
        return `Ukuran setiap PDF maksimal ${props.limits.max_file_size_mb} MB.`;
    if (
        files.reduce((total, file) => total + file.size, 0) >
        props.limits.max_upload_size_mb * 1024 * 1024
    )
        return `Total PDF baru maksimal ${props.limits.max_upload_size_mb} MB per penyimpanan.`;
    return null;
}
function addFiles(event: Event): void {
    const input = event.target as HTMLInputElement;
    const selected = Array.from(input.files ?? []);
    const combined = [...form.documents];
    selected.forEach((file) => {
        if (
            !combined.some(
                (existing) =>
                    existing.name === file.name &&
                    existing.size === file.size &&
                    existing.lastModified === file.lastModified,
            )
        )
            combined.push(file);
    });
    const error = validateFiles(combined);
    if (error) form.setError('documents', error);
    else {
        form.documents = combined;
        form.clearErrors('documents');
    }
    input.value = '';
}
function toggleRemoval(id: string): void {
    form.remove_document_ids = form.remove_document_ids.includes(id)
        ? form.remove_document_ids.filter((value) => value !== id)
        : [...form.remove_document_ids, id];
    form.clearErrors('documents', 'remove_document_ids');
}
async function focusErrors(): Promise<void> {
    await nextTick();
    errorSummary.value?.focus();
}
function submit(): void {
    if (busy.value || !canSave.value) return;
    form.clearErrors();
    if (datesRequired.value && !form.pks_started_at)
        form.setError('pks_started_at', 'Isi tanggal mulai PKS.');
    if (datesRequired.value && !form.pks_ends_at)
        form.setError('pks_ends_at', 'Isi tanggal berakhir PKS.');
    if (
        form.pks_started_at &&
        form.pks_ends_at &&
        form.pks_ends_at < form.pks_started_at
    )
        form.setError(
            'pks_ends_at',
            'Tanggal berakhir harus sama atau setelah tanggal mulai PKS.',
        );
    if (!form.institution.trim())
        form.setError('institution', 'Isi instansi terkait.');
    else if (form.institution.trim().length > 255)
        form.setError('institution', 'Instansi terkait maksimal 255 karakter.');
    if (!form.summary.trim())
        form.setError('summary', 'Isi ringkasan legalitas.');
    else if (form.summary.trim().length > 10000)
        form.setError('summary', 'Ringkasan maksimal 10.000 karakter.');
    const fileError = validateFiles(form.documents);
    if (fileError) form.setError('documents', fileError);
    if (Object.keys(form.errors).length) {
        void focusErrors();
        return;
    }
    const url = props.legality ? props.urls.update : props.urls.store;
    if (!url) return;
    form.transform((data) => ({
        ...data,
        institution: data.institution.trim(),
        summary: data.summary.trim(),
        pks_started_at: data.pks_started_at || null,
        pks_ends_at: data.pks_ends_at || null,
        _method: props.legality ? 'put' : 'post',
    })).post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
        onError: () => {
            void focusErrors();
        },
    });
}
function remove(): void {
    if (busy.value || !props.permissions.delete || !props.urls.destroy) return;
    deletion.delete(props.urls.destroy, {
        preserveScroll: true,
        onSuccess: () => emit('update:open', false),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent
            :show-close-button="!busy"
            class="admin-dashboard-theme flex max-h-[calc(100dvh-2rem)] flex-col gap-0 overflow-hidden rounded-xl border-border bg-card p-0 text-foreground sm:max-w-3xl"
            @interact-outside.prevent
        >
            <DialogHeader
                class="flex-row items-start gap-3 border-b border-border bg-muted/30 px-5 py-5 pr-12 text-left sm:px-6 sm:pr-14"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                    ><ShieldCheck class="size-6" aria-hidden="true"
                /></span>
                <div class="min-w-0 space-y-1">
                    <DialogTitle class="text-xl leading-7 font-semibold">{{
                        confirmingDelete
                            ? 'Hapus legalitas?'
                            : legality
                              ? 'Ubah legalitas'
                              : 'Tambah legalitas'
                    }}</DialogTitle>
                    <p class="text-sm font-medium [overflow-wrap:anywhere]">
                        {{ farm.name }}
                    </p>
                    <DialogDescription class="text-xs leading-5">{{
                        confirmingDelete
                            ? 'Periksa data yang akan dihapus.'
                            : 'Simpan informasi PKS dan draft PDF untuk ditampilkan kepada Owner.'
                    }}</DialogDescription>
                </div>
            </DialogHeader>
            <form
                class="flex min-h-0 flex-1 flex-col"
                novalidate
                @submit.prevent="submit"
            >
                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5 sm:px-6">
                    <div v-if="confirmingDelete" class="space-y-4">
                        <div
                            class="flex items-start gap-3 rounded-xl bg-error/10 p-4 text-sm leading-6"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-5 shrink-0 text-error"
                                aria-hidden="true"
                            />
                            <p>
                                Informasi PKS, instansi, ringkasan, dan
                                {{ legality?.documents.length ?? 0 }} draft PDF
                                pada lahan ini akan dihapus.
                            </p>
                        </div>
                        <p
                            v-if="deleteError"
                            role="alert"
                            class="text-sm text-error"
                        >
                            {{ deleteError }}
                        </p>
                    </div>
                    <div v-else class="space-y-5">
                        <div
                            v-if="errors.length"
                            ref="errorSummary"
                            tabindex="-1"
                            role="alert"
                            class="rounded-xl border border-error/30 bg-error/5 p-3 text-sm text-error focus:outline-2 focus:outline-error"
                        >
                            <p class="font-semibold">
                                Periksa isian legalitas.
                            </p>
                            <ul class="mt-1 list-disc space-y-1 pl-5">
                                <li v-for="error in errors" :key="error.field">
                                    {{ error.message }}
                                </li>
                            </ul>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="min-w-0 space-y-2">
                                <label
                                    for="legality-start"
                                    class="text-sm font-medium"
                                    >Tanggal mulai PKS
                                    <span
                                        v-if="datesRequired"
                                        aria-hidden="true"
                                        class="text-error"
                                        >*</span
                                    ></label
                                >
                                <input
                                    id="legality-start"
                                    v-model="form.pks_started_at"
                                    type="date"
                                    class="admin-field w-full min-w-0"
                                    :required="datesRequired"
                                    :disabled="busy || !canSave"
                                    :aria-invalid="!!form.errors.pks_started_at"
                                    :aria-describedby="
                                        form.errors.pks_started_at
                                            ? 'legality-start-error'
                                            : undefined
                                    "
                                />
                                <p
                                    v-if="form.errors.pks_started_at"
                                    id="legality-start-error"
                                    class="text-xs text-error"
                                >
                                    {{ form.errors.pks_started_at }}
                                </p>
                            </div>
                            <div class="min-w-0 space-y-2">
                                <label
                                    for="legality-end"
                                    class="text-sm font-medium"
                                    >Tanggal berakhir PKS
                                    <span
                                        v-if="datesRequired"
                                        aria-hidden="true"
                                        class="text-error"
                                        >*</span
                                    ></label
                                >
                                <input
                                    id="legality-end"
                                    v-model="form.pks_ends_at"
                                    type="date"
                                    :min="form.pks_started_at || undefined"
                                    class="admin-field w-full min-w-0"
                                    :required="datesRequired"
                                    :disabled="busy || !canSave"
                                    :aria-invalid="!!form.errors.pks_ends_at"
                                    :aria-describedby="
                                        form.errors.pks_ends_at
                                            ? 'legality-end-error'
                                            : undefined
                                    "
                                />
                                <p
                                    v-if="form.errors.pks_ends_at"
                                    id="legality-end-error"
                                    class="text-xs text-error"
                                >
                                    {{ form.errors.pks_ends_at }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="!datesRequired"
                            class="text-xs leading-5 text-muted-foreground"
                        >
                            Tanggal PKS boleh dikosongkan untuk lahan milik
                            sendiri.
                        </p>
                        <div class="space-y-2">
                            <label
                                for="legality-institution"
                                class="text-sm font-medium"
                                >Instansi terkait
                                <span aria-hidden="true" class="text-error"
                                    >*</span
                                ></label
                            >
                            <input
                                id="legality-institution"
                                v-model="form.institution"
                                type="text"
                                maxlength="255"
                                required
                                class="admin-field w-full"
                                placeholder="Nama instansi atau pihak yang terkait"
                                :disabled="busy || !canSave"
                                :aria-invalid="!!form.errors.institution"
                                :aria-describedby="
                                    form.errors.institution
                                        ? 'legality-institution-error'
                                        : undefined
                                "
                            />
                            <p
                                v-if="form.errors.institution"
                                id="legality-institution-error"
                                class="text-xs text-error"
                            >
                                {{ form.errors.institution }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label
                                for="legality-summary"
                                class="text-sm font-medium"
                                >Ringkasan legalitas
                                <span aria-hidden="true" class="text-error"
                                    >*</span
                                ></label
                            >
                            <textarea
                                id="legality-summary"
                                v-model="form.summary"
                                rows="5"
                                maxlength="10000"
                                required
                                class="admin-field w-full resize-y"
                                placeholder="Ringkas ruang lingkup kerja sama, hak, kewajiban, atau catatan legalitas lahan."
                                :disabled="busy || !canSave"
                                :aria-invalid="!!form.errors.summary"
                                :aria-describedby="
                                    form.errors.summary
                                        ? 'legality-summary-error'
                                        : undefined
                                "
                            />
                            <p
                                v-if="form.errors.summary"
                                id="legality-summary-error"
                                class="text-xs text-error"
                            >
                                {{ form.errors.summary }}
                            </p>
                        </div>
                        <section
                            class="space-y-3 border-t border-border pt-4"
                            aria-labelledby="legality-drafts-title"
                        >
                            <div>
                                <h3
                                    id="legality-drafts-title"
                                    class="text-sm font-semibold"
                                >
                                    Draft legalitas
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opsional)</span
                                    >
                                </h3>
                                <p
                                    id="legality-file-help"
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    PDF saja. Maksimal
                                    {{ limits.max_files }} file per lahan,
                                    {{ limits.max_file_size_mb }} MB per file,
                                    dan {{ limits.max_upload_size_mb }} MB PDF
                                    baru per penyimpanan.
                                </p>
                            </div>
                            <ul
                                v-if="legality?.documents.length"
                                class="space-y-2"
                                aria-label="Draft tersimpan"
                            >
                                <li
                                    v-for="document in legality.documents"
                                    :key="document.id"
                                    class="flex min-w-0 items-center gap-3 rounded-xl border border-border p-3"
                                    :class="
                                        form.remove_document_ids.includes(
                                            document.id,
                                        )
                                            ? 'bg-error/5'
                                            : ''
                                    "
                                >
                                    <FileText
                                        class="size-5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />
                                    <span class="min-w-0 flex-1"
                                        ><a
                                            :href="document.download_url"
                                            class="block text-sm font-medium [overflow-wrap:anywhere] underline underline-offset-4"
                                            >{{ document.original_name }}</a
                                        ><span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                            >{{
                                                formatLegalityFileSize(
                                                    document.size_bytes,
                                                )
                                            }}
                                            ·
                                            {{
                                                form.remove_document_ids.includes(
                                                    document.id,
                                                )
                                                    ? 'Akan dihapus saat disimpan'
                                                    : 'Tersimpan'
                                            }}</span
                                        ></span
                                    >
                                    <Button
                                        v-if="
                                            permissions.removeDocuments &&
                                            canSave
                                        "
                                        type="button"
                                        :variant="
                                            form.remove_document_ids.includes(
                                                document.id,
                                            )
                                                ? 'outline'
                                                : 'destructive-ghost'
                                        "
                                        class="min-h-11 shrink-0"
                                        :disabled="busy"
                                        :aria-label="`${form.remove_document_ids.includes(document.id) ? 'Batalkan penghapusan' : 'Hapus draft'} ${document.original_name}`"
                                        @click="toggleRemoval(document.id)"
                                        ><template
                                            v-if="
                                                form.remove_document_ids.includes(
                                                    document.id,
                                                )
                                            "
                                            >Batal</template
                                        ><Trash2
                                            v-else
                                            class="size-4"
                                            aria-hidden="true"
                                    /></Button>
                                </li>
                            </ul>
                            <ul
                                v-if="form.documents.length"
                                class="space-y-2"
                                aria-label="PDF baru"
                            >
                                <li
                                    v-for="(document, index) in form.documents"
                                    :key="`${document.name}-${index}`"
                                    class="flex min-w-0 items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 p-3"
                                >
                                    <FileText
                                        class="size-5 shrink-0 text-primary"
                                        aria-hidden="true"
                                    /><span class="min-w-0 flex-1"
                                        ><span
                                            class="block text-sm font-medium [overflow-wrap:anywhere]"
                                            >{{ document.name }}</span
                                        ><span
                                            class="mt-0.5 block text-xs text-muted-foreground"
                                            >{{
                                                formatLegalityFileSize(
                                                    document.size,
                                                )
                                            }}
                                            · Belum disimpan</span
                                        ></span
                                    ><Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-11 shrink-0"
                                        :disabled="busy"
                                        :aria-label="`Batalkan unggah ${document.name}`"
                                        @click="
                                            form.documents.splice(index, 1);
                                            form.clearErrors('documents');
                                        "
                                        ><X class="size-4" aria-hidden="true"
                                    /></Button>
                                </li>
                            </ul>
                            <div v-if="permissions.upload && canSave">
                                <label
                                    for="legality-documents"
                                    class="mb-2 flex items-center gap-2 text-sm font-medium"
                                    ><Upload
                                        class="size-4 text-primary"
                                        aria-hidden="true"
                                    />Tambah draft PDF</label
                                >
                                <input
                                    id="legality-documents"
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    multiple
                                    class="admin-field w-full text-sm file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-muted file:px-3 file:py-1 file:text-foreground"
                                    :disabled="busy"
                                    aria-describedby="legality-file-help"
                                    :aria-invalid="!!form.errors.documents"
                                    @change="addFiles"
                                />
                                <p
                                    v-if="form.errors.documents"
                                    role="alert"
                                    class="mt-2 text-xs text-error"
                                >
                                    {{ form.errors.documents }}
                                </p>
                            </div>
                        </section>
                        <div
                            v-if="form.progress"
                            role="status"
                            class="space-y-2"
                        >
                            <p class="text-xs text-muted-foreground">
                                Mengunggah PDF…
                                {{ form.progress.percentage ?? 0 }}%
                            </p>
                            <progress
                                :value="form.progress.percentage ?? 0"
                                max="100"
                                class="h-2 w-full accent-primary"
                                aria-label="Progres unggah PDF"
                            />
                        </div>
                    </div>
                </div>
                <footer
                    class="flex flex-wrap items-center justify-end gap-2 border-t border-border bg-card px-5 py-4 sm:px-6"
                >
                    <template v-if="confirmingDelete">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="busy"
                            @click="confirmingDelete = false"
                            >Batal</Button
                        >
                        <Button
                            type="button"
                            variant="destructive"
                            :disabled="busy"
                            @click="remove"
                            ><LoaderCircle
                                v-if="deletion.processing"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />{{
                                deletion.processing
                                    ? 'Menghapus…'
                                    : 'Hapus legalitas'
                            }}</Button
                        >
                    </template>
                    <template v-else>
                        <Button
                            v-if="legality && permissions.delete"
                            type="button"
                            variant="destructive-ghost"
                            class="mr-auto"
                            :disabled="busy"
                            @click="confirmingDelete = true"
                            ><Trash2
                                class="size-4"
                                aria-hidden="true"
                            />Hapus</Button
                        >
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="busy"
                            @click="close(false)"
                            >Batal</Button
                        >
                        <Button v-if="canSave" type="submit" :disabled="busy"
                            ><LoaderCircle
                                v-if="form.processing"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />{{
                                form.processing ? 'Menyimpan…' : 'Simpan'
                            }}</Button
                        >
                    </template>
                </footer>
            </form>
        </DialogContent>
    </Dialog>
</template>

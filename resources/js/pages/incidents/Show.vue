<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    Camera,
    CheckCircle2,
    ClipboardCheck,
    LoaderCircle,
    MapPin,
    RotateCcw,
    Send,
    ShieldAlert,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FarmField from '@/components/farms/FarmField.vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
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
    formatIncidentDate,
    formatIncidentDateTime,
    incidentSeverityClasses,
    incidentStatusClasses,
    type IncidentSeverityOption,
    type IncidentTicketDetail,
} from '@/types/incidents';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    ticket: IncidentTicketDetail;
    severities: IncidentSeverityOption[];
}>();

const respondForm = useForm({
    admin_note: props.ticket.admin_note ?? '',
    severity: props.ticket.severity,
});

const resolveOpen = ref(false);
const resolveForm = useForm({
    resolution_note: '',
    severity: props.ticket.severity,
});

const reopenOpen = ref(false);
const reopenForm = useForm({
    reason: '',
});

const cancelOpen = ref(false);
const cancelReasonError = ref('');
const cancelForm = useForm({ reason: '' });
const cancelStatusError = computed(
    () => (cancelForm.errors as Record<string, string | undefined>).status,
);
function openCancel(): void {
    cancelForm.reset();
    cancelForm.clearErrors();
    cancelReasonError.value = '';
    cancelOpen.value = true;
}
function submitCancel(): void {
    cancelReasonError.value =
        cancelForm.reason.trim().length < 5
            ? 'Alasan penutupan minimal 5 karakter.'
            : '';
    if (cancelReasonError.value || cancelForm.processing) return;
    cancelForm.post(`/admin/incidents/${props.ticket.id}/cancel-subblock`, {
        preserveScroll: true,
        onSuccess: () => {
            cancelOpen.value = false;
            cancelForm.reset();
        },
    });
}

const failedPhotoIds = ref(new Set<string>());

function markPhotoFailed(id: string): void {
    failedPhotoIds.value = new Set([...failedPhotoIds.value, id]);
}

function submitRespond(): void {
    respondForm.post(`/admin/incidents/${props.ticket.id}/respond`, {
        preserveScroll: true,
    });
}

function openResolve(): void {
    resolveForm.resolution_note = '';
    resolveForm.severity = props.ticket.severity;
    resolveForm.clearErrors();
    resolveOpen.value = true;
}

function submitResolve(): void {
    resolveForm.post(`/admin/incidents/${props.ticket.id}/resolve`, {
        preserveScroll: true,
        onSuccess: () => {
            resolveOpen.value = false;
            resolveForm.reset();
        },
    });
}

function openReopen(): void {
    reopenForm.reset();
    reopenForm.clearErrors();
    reopenOpen.value = true;
}

function submitReopen(): void {
    reopenForm.post(`/admin/incidents/${props.ticket.id}/reopen`, {
        preserveScroll: true,
        onSuccess: () => {
            reopenOpen.value = false;
            reopenForm.reset();
        },
    });
}
</script>

<template>
    <Head
        :title="`Tiket Insiden · ${ticket.incident_category || 'Insiden Kebun'}`"
    />
    <div class="space-y-[18px]">
        <div class="flex min-w-0 items-start gap-2">
            <PageBackButton
                href="/admin/incidents"
                label="Kembali ke daftar insiden"
            />
            <header
                class="min-w-0 flex-1 flex flex-wrap items-start justify-between gap-4"
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            :class="incidentSeverityClasses[ticket.severity]"
                        >
                            {{ ticket.severity_label }}
                        </span>
                        <span
                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                            :class="incidentStatusClasses[ticket.status]"
                        >
                            {{ ticket.status_label }}
                        </span>
                    </div>
                    <h1 class="admin-page-title">
                        {{ ticket.incident_category || 'Laporan Insiden' }}
                    </h1>
                    <p
                        class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-4 text-primary" />
                            {{ ticket.location_label }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDays class="size-4 text-primary" />
                            {{
                                formatIncidentDate(
                                    ticket.work_date || ticket.reported_at,
                                )
                            }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <UserRound class="size-4 text-primary" />
                            {{ ticket.reporter_name ?? 'Petugas' }}
                        </span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="ticket.can.cancel_subblock"
                        type="button"
                        variant="destructive-outline"
                        class="min-h-11"
                        @click="openCancel"
                    >
                        <ShieldAlert class="size-4" />
                        Tutup Subblok
                    </Button>
                    <Button
                        v-if="ticket.can.resolve_directly"
                        type="button"
                        variant="outline"
                        @click="openResolve"
                    >
                        <CheckCircle2 class="size-4" />
                        Tutup &amp; Selesaikan Langsung
                    </Button>
                    <Button
                        v-if="ticket.can.reopen"
                        type="button"
                        variant="outline"
                        @click="openReopen"
                    >
                        <RotateCcw class="size-4" />
                        Buka Kembali Tiket
                    </Button>
                </div>
            </header>
        </div>

        <div
            v-if="ticket.cancellation"
            class="rounded-2xl border border-warning/35 bg-warning/10 p-4 text-sm"
            role="status"
        >
            <p class="font-semibold">
                Subblok dibatalkan · tiket diselesaikan otomatis
            </p>
            <p class="mt-1 break-words whitespace-pre-line">
                {{ ticket.cancellation.reason }}
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ formatIncidentDateTime(ticket.cancellation.cancelled_at) }}
            </p>
        </div>

        <div
            v-if="ticket.reopen_reason"
            class="rounded-2xl border border-warning/35 bg-warning/10 p-4 text-sm"
            role="status"
        >
            <p class="font-semibold text-foreground">
                Tiket dibuka kembali
                <span
                    v-if="ticket.reopened_by_name"
                    class="font-normal text-muted-foreground"
                >
                    oleh {{ ticket.reopened_by_name }} ({{
                        formatIncidentDateTime(ticket.reopened_at)
                    }})
                </span>
            </p>
            <p class="mt-1 leading-6 text-foreground">
                {{ ticket.reopen_reason }}
            </p>
        </div>

        <div
            class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,2fr)_minmax(300px,1fr)]"
        >
            <div class="min-w-0 space-y-[18px]">
                <section class="admin-panel space-y-4">
                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <h2 class="admin-section-title flex items-center gap-2">
                            <ShieldAlert class="size-4 text-warning" />
                            Laporan insiden awal
                        </h2>
                        <Link
                            v-if="ticket.daily_log_id"
                            :href="`/admin/logbook/${ticket.farm_id}/${ticket.daily_log_id}`"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        >
                            <BookOpen class="size-3.5" />
                            Lihat logbook harian
                        </Link>
                    </div>

                    <div
                        v-if="ticket.description"
                        class="rounded-xl bg-muted/40 p-4 text-sm leading-6 break-words whitespace-pre-line"
                    >
                        {{ ticket.description }}
                    </div>

                    <div
                        v-if="ticket.immediate_action"
                        class="rounded-xl border border-border bg-card p-4"
                    >
                        <p class="text-xs font-semibold text-muted-foreground">
                            Tindakan segera oleh pelapor
                        </p>
                        <p
                            class="mt-1 text-sm leading-6 break-words whitespace-pre-line"
                        >
                            {{ ticket.immediate_action }}
                        </p>
                    </div>

                    <dl
                        v-if="
                            ticket.report?.details?.length ||
                            ticket.report?.worker_count
                        "
                        class="divide-y divide-border"
                    >
                        <div
                            v-if="ticket.report?.worker_count !== null"
                            class="flex items-start justify-between gap-4 py-2.5 text-sm"
                        >
                            <dt class="text-muted-foreground">
                                Jumlah pekerja
                            </dt>
                            <dd class="font-semibold">
                                {{ ticket.report?.worker_count }} orang
                            </dd>
                        </div>
                        <div
                            v-for="(item, idx) in ticket.report?.details ?? []"
                            :key="`${item.label}-${idx}`"
                            class="flex items-start justify-between gap-4 py-2.5 text-sm"
                        >
                            <dt class="text-muted-foreground">
                                {{ item.label }}
                            </dt>
                            <dd
                                class="max-w-[60%] text-right font-medium break-words"
                            >
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="ticket.report?.photos?.length" class="space-y-2">
                        <p
                            class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                        >
                            <Camera class="size-4 text-primary" />
                            Dokumentasi foto ({{ ticket.report.photos.length }})
                        </p>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <template
                                v-for="(photo, idx) in ticket.report.photos"
                                :key="photo.id"
                            >
                                <a
                                    v-if="!failedPhotoIds.has(photo.id)"
                                    :href="photo.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="aspect-square overflow-hidden rounded-xl border border-border bg-muted"
                                >
                                    <img
                                        :src="photo.url"
                                        :alt="`Foto insiden ${idx + 1}`"
                                        class="size-full object-cover"
                                        loading="lazy"
                                        @error="markPhotoFailed(photo.id)"
                                    />
                                </a>
                            </template>
                        </div>
                    </div>
                </section>

                <section
                    v-if="ticket.status === 'resolved'"
                    class="admin-panel space-y-4"
                >
                    <div
                        class="flex items-start justify-between gap-3"
                    >
                        <h2 class="admin-section-title flex items-center gap-2">
                            <ClipboardCheck class="size-4 text-success" />
                            Penyelesaian tiket
                        </h2>
                        <Link
                            v-if="ticket.follow_up_activity"
                            :href="`/admin/logbook/aktivitas/${ticket.follow_up_activity.id}`"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"
                        >
                            Lihat di logbook
                            <ArrowUpRight class="size-3.5" />
                        </Link>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Diselesaikan oleh
                        <span class="font-semibold text-foreground">{{
                            ticket.resolved_by_name ?? 'Pengelola'
                        }}</span>
                        pada {{ formatIncidentDateTime(ticket.resolved_at) }}
                    </p>

                    <div
                        v-if="ticket.resolution_note"
                        class="rounded-xl border border-success/30 bg-success/10 p-4 text-sm leading-6 break-words whitespace-pre-line"
                    >
                        {{ ticket.resolution_note }}
                    </div>

                    <div
                        v-if="ticket.follow_up_activity"
                        class="space-y-3 rounded-xl border border-border p-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-semibold text-foreground">
                                Aktivitas tindak lanjut:
                                {{ ticket.follow_up_activity.name }}
                            </p>
                            <span class="text-xs text-muted-foreground">
                                {{
                                    formatIncidentDate(
                                        ticket.follow_up_activity.work_date ??
                                            null,
                                    )
                                }}
                            </span>
                        </div>
                        <dl
                            v-if="ticket.follow_up_activity.details.length"
                            class="divide-y divide-border text-sm"
                        >
                            <div
                                v-for="(item, idx) in ticket.follow_up_activity
                                    .details"
                                :key="`${item.label}-${idx}`"
                                class="flex items-start justify-between gap-4 py-2"
                            >
                                <dt class="text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd class="text-right font-medium">
                                    {{ item.value }}
                                </dd>
                            </div>
                        </dl>
                        <div
                            v-if="ticket.follow_up_activity.photos.length"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <a
                                v-for="(photo, idx) in ticket.follow_up_activity
                                    .photos"
                                :key="photo.id"
                                :href="photo.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="aspect-square overflow-hidden rounded-xl border border-border bg-muted"
                            >
                                <img
                                    :src="photo.url"
                                    :alt="`Foto tindak lanjut ${idx + 1}`"
                                    class="size-full object-cover"
                                    loading="lazy"
                                />
                            </a>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="min-w-0 space-y-[18px]">
                <section class="admin-panel space-y-4">
                    <h2 class="admin-section-title">
                        Tanggapan &amp; Arahan Admin
                    </h2>

                    <div
                        v-if="ticket.admin_note && !ticket.can.respond"
                        class="rounded-xl bg-muted/40 p-3.5 text-sm leading-6 break-words whitespace-pre-line"
                    >
                        {{ ticket.admin_note }}
                    </div>

                    <p
                        v-if="ticket.responded_at"
                        class="text-xs text-muted-foreground"
                    >
                        Terakhir diperbarui oleh
                        <span class="font-medium text-foreground">{{
                            ticket.responded_by_name ?? 'Admin'
                        }}</span>
                        · {{ formatIncidentDateTime(ticket.responded_at) }}
                    </p>

                    <form
                        v-if="ticket.can.respond"
                        class="space-y-4"
                        @submit.prevent="submitRespond"
                    >
                        <FarmField
                            id="admin-incident-severity"
                            label="Tingkat kedaruratan"
                            :error="respondForm.errors.severity"
                            v-slot="field"
                        >
                            <AppSelect
                                :id="field.id"
                                v-model="respondForm.severity"
                                aria-label="Tingkat kedaruratan"
                            >
                                <AppSelectOption
                                    v-for="option in severities"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </AppSelectOption>
                            </AppSelect>
                        </FarmField>

                        <FarmField
                            id="admin-incident-note"
                            label="Arahan / catatan penanganan untuk petugas"
                            required
                            :error="respondForm.errors.admin_note"
                            v-slot="field"
                        >
                            <textarea
                                :id="field.id"
                                v-model="respondForm.admin_note"
                                class="admin-field resize-y"
                                rows="4"
                                maxlength="2000"
                                required
                                placeholder="Contoh: Lakukan penyemprotan fungisida sore ini dan catat aktivitas perawatannya."
                                :aria-invalid="field.invalid"
                                :aria-describedby="field.description"
                            />
                        </FarmField>

                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="respondForm.processing"
                        >
                            <LoaderCircle
                                v-if="respondForm.processing"
                                class="animate-spin"
                            />
                            <Send v-else class="size-4" />
                            {{
                                ticket.status === 'in_progress'
                                    ? 'Perbarui Arahan'
                                    : 'Tanggapi & Tandai Sedang Ditangani'
                            }}
                        </Button>
                    </form>

                    <p
                        v-else-if="!ticket.admin_note"
                        class="text-sm text-muted-foreground"
                    >
                        Belum ada arahan admin tercatat.
                    </p>
                </section>
            </aside>
        </div>
    </div>

    <Dialog
        :open="cancelOpen"
        @update:open="
            (open) => {
                if (!cancelForm.processing) cancelOpen = open;
            }
        "
    >
        <DialogContent
            class="admin-dashboard-theme max-h-[calc(100dvh-2rem)] overflow-y-auto"
            :show-close-button="!cancelForm.processing"
            @interact-outside.prevent
        >
            <DialogHeader>
                <DialogTitle>Tutup subblok {{ ticket.subblok }}?</DialogTitle>
                <DialogDescription>
                    Seluruh subblok dibatalkan dan tidak dapat dibuka kembali.
                    Semua tiket insiden terbuka pada subblok ini akan
                    diselesaikan. Kapasitas lahan dilepas; tugas dan laporan
                    tetap tersimpan sebagai riwayat.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCancel">
                <FarmField
                    id="cancel-subblock-reason"
                    label="Alasan penutupan subblok"
                    required
                    :error="cancelReasonError || cancelForm.errors.reason"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="cancelForm.reason"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        :disabled="cancelForm.processing"
                        placeholder="Jelaskan alasan subblok harus ditutup."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                        @input="cancelReasonError = ''"
                    />
                </FarmField>
                <p
                    v-if="cancelStatusError"
                    role="alert"
                    class="text-sm text-error"
                >
                    {{ cancelStatusError }}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11"
                        :disabled="cancelForm.processing"
                        @click="cancelOpen = false"
                        >Batal</Button
                    >
                    <Button
                        type="submit"
                        variant="destructive"
                        class="min-h-11"
                        :disabled="cancelForm.processing"
                    >
                        <LoaderCircle
                            v-if="cancelForm.processing"
                            class="animate-spin"
                        />
                        {{
                            cancelForm.processing
                                ? 'Menyimpan…'
                                : 'Tutup Subblok'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="resolveOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Selesaikan tiket insiden langsung?</DialogTitle>
                <DialogDescription>
                    Gunakan ini bila masalah sudah ditangani di lapangan atau
                    tidak memerlukan aktivitas tambahan oleh petugas.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitResolve">
                <FarmField
                    id="resolve-severity"
                    label="Tingkat kedaruratan final"
                    :error="resolveForm.errors.severity"
                    v-slot="field"
                >
                    <AppSelect
                        :id="field.id"
                        v-model="resolveForm.severity"
                        aria-label="Tingkat kedaruratan final"
                    >
                        <AppSelectOption
                            v-for="option in severities"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </AppSelectOption>
                    </AppSelect>
                </FarmField>

                <FarmField
                    id="resolve-note"
                    label="Catatan penyelesaian"
                    required
                    :error="resolveForm.errors.resolution_note"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="resolveForm.resolution_note"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Jelaskan hasil pengecekan atau penyelesaian akhir."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="resolveForm.processing"
                        @click="resolveOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="resolveForm.processing">
                        <LoaderCircle
                            v-if="resolveForm.processing"
                            class="animate-spin"
                        />
                        <CheckCircle2 v-else class="size-4" />
                        Tandai Selesai
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="reopenOpen">
        <DialogContent class="admin-dashboard-theme">
            <DialogHeader>
                <DialogTitle>Buka kembali tiket insiden?</DialogTitle>
                <DialogDescription>
                    Status tiket akan kembali menjadi Perlu penanganan agar
                    dapat ditindaklanjuti ulang.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitReopen">
                <FarmField
                    id="reopen-reason"
                    label="Alasan membuka kembali"
                    required
                    :error="reopenForm.errors.reason"
                    v-slot="field"
                >
                    <textarea
                        :id="field.id"
                        v-model="reopenForm.reason"
                        class="admin-field resize-y"
                        rows="4"
                        maxlength="2000"
                        required
                        placeholder="Contoh: Gejala muncul kembali di sisi utara blok."
                        :aria-invalid="field.invalid"
                        :aria-describedby="field.description"
                    />
                </FarmField>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="reopenForm.processing"
                        @click="reopenOpen = false"
                        >Batal</Button
                    >
                    <Button type="submit" :disabled="reopenForm.processing">
                        <LoaderCircle
                            v-if="reopenForm.processing"
                            class="animate-spin"
                        />
                        <RotateCcw v-else class="size-4" />
                        Buka Kembali
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

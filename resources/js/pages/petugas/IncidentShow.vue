<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Camera,
    CheckCircle2,
    ChevronRight,
    ClipboardPlus,
    FileText,
    Info,
    LoaderCircle,
    MapPin,
    RotateCcw,
    ShieldAlert,
    UserRound,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    formatIncidentDate,
    formatIncidentDateTime,
    incidentSeverityClasses,
    incidentStatusClasses,
    type IncidentTicketDetail,
} from '@/types/incidents';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    ticket: IncidentTicketDetail;
}>();

const reopenVisible = ref(false);
const reopenForm = useForm({ reason: '' });
const failedPhotoIds = ref(new Set<string>());

function markPhotoFailed(id: string): void {
    failedPhotoIds.value = new Set([...failedPhotoIds.value, id]);
}

function submitReopen(): void {
    reopenForm.post(`/petugas/incidents/${props.ticket.id}/reopen`, {
        preserveScroll: true,
        onSuccess: () => {
            reopenVisible.value = false;
            reopenForm.reset();
        },
    });
}
</script>

<template>
    <Head
        :title="`Tiket Insiden · ${ticket.incident_category || 'Insiden Kebun'}`"
    />
    <div class="min-w-0 space-y-4 pb-12 text-foreground">
        <header class="flex items-center gap-2.5">
            <Link
                href="/petugas/incidents"
                aria-label="Kembali ke daftar tiket insiden"
                class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <ArrowLeft class="size-5" aria-hidden="true" />
            </Link>
            <h1>Detail tiket insiden</h1>
        </header>

        <section
            class="petugas-card overflow-hidden"
            aria-label="Ringkasan tiket insiden"
        >
            <div class="space-y-3 bg-primary/5 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="incidentSeverityClasses[ticket.severity]"
                    >
                        {{ ticket.severity_label }}
                    </span>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="incidentStatusClasses[ticket.status]"
                    >
                        {{ ticket.status_label }}
                    </span>
                </div>
                <h2 class="text-lg font-bold text-foreground">
                    {{ ticket.incident_category || 'Insiden lahan' }}
                </h2>
                <div class="space-y-1 text-xs text-muted-foreground">
                    <p class="flex items-center gap-1.5">
                        <MapPin
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{ ticket.location_label }}
                    </p>
                    <p class="flex items-center gap-1.5">
                        <CalendarDays
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        {{
                            formatIncidentDate(
                                ticket.work_date || ticket.reported_at,
                            )
                        }}
                    </p>
                    <p class="flex items-center gap-1.5">
                        <UserRound
                            class="size-3.5 text-primary"
                            aria-hidden="true"
                        />
                        Dilaporkan oleh {{ ticket.reporter_name ?? 'Petugas' }}
                    </p>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <p
                    v-if="ticket.description"
                    class="text-sm leading-6 break-words whitespace-pre-line text-foreground"
                >
                    {{ ticket.description }}
                </p>

                <div
                    v-if="ticket.immediate_action"
                    class="rounded-xl bg-muted/50 p-3 text-sm"
                >
                    <p class="text-xs font-semibold text-muted-foreground">
                        Tindakan segera saat pelaporan
                    </p>
                    <p class="mt-1 leading-6 break-words whitespace-pre-line">
                        {{ ticket.immediate_action }}
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="ticket.reopen_reason"
            class="petugas-card border border-warning/35 bg-warning/10 p-4 text-sm"
            role="status"
        >
            <p class="font-semibold text-foreground">
                Tiket dibuka kembali
                <span
                    v-if="ticket.reopened_by_name"
                    class="font-normal text-muted-foreground"
                >
                    oleh {{ ticket.reopened_by_name }}
                </span>
            </p>
            <p class="mt-1 text-xs leading-5 text-foreground">
                {{ ticket.reopen_reason }}
            </p>
        </section>

        <section
            v-if="ticket.admin_note"
            class="petugas-card border border-info/30 bg-info/10 p-4"
            aria-label="Arahan admin"
        >
            <p class="flex items-center gap-2 text-xs font-semibold text-info">
                <Info class="size-4" aria-hidden="true" />
                Arahan Admin
                <span
                    v-if="ticket.responded_by_name"
                    class="font-normal text-muted-foreground"
                >
                    · {{ ticket.responded_by_name }} ({{
                        formatIncidentDateTime(ticket.responded_at)
                    }})
                </span>
            </p>
            <p
                class="mt-2 text-sm leading-6 break-words whitespace-pre-line text-foreground"
            >
                {{ ticket.admin_note }}
            </p>
        </section>

        <section
            v-if="
                ticket.status !== 'resolved' && ticket.can.resolve_by_follow_up
            "
            class="petugas-card space-y-3 p-4"
            aria-label="Tindak lanjut dan selesaikan tiket"
        >
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <ClipboardPlus class="size-5" aria-hidden="true" />
                </span>
                <div>
                    <h2 class="text-base font-semibold">
                        Tindak lanjuti &amp; selesaikan
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Pilih kategori pekerjaan untuk mencatat tindak lanjut
                        pada lokasi tiket ini.
                    </p>
                </div>
            </div>

            <div v-if="ticket.follow_up_options.length" class="space-y-2">
                <Link
                    v-for="option in ticket.follow_up_options"
                    :key="option.id"
                    :href="option.href"
                    class="flex min-h-12 items-center justify-between gap-2 rounded-xl border border-primary/25 bg-primary/5 px-4 py-3 text-sm font-semibold text-primary hover:bg-primary/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <span>Catat {{ option.name }}</span>
                    <ChevronRight class="size-4 shrink-0" aria-hidden="true" />
                </Link>
            </div>

            <p
                v-else-if="ticket.follow_up_blocked_reason"
                class="rounded-xl border border-warning/40 bg-warning/10 p-3 text-xs leading-5 text-foreground"
            >
                {{ ticket.follow_up_blocked_reason }}
            </p>
        </section>

        <section
            v-if="ticket.status === 'resolved'"
            class="petugas-card space-y-3 p-4"
            aria-label="Hasil penyelesaian"
        >
            <div class="flex items-center gap-2 text-success">
                <CheckCircle2 class="size-5 shrink-0" aria-hidden="true" />
                <h2 class="text-base font-semibold text-foreground">
                    Tiket selesai
                </h2>
            </div>
            <p class="text-xs text-muted-foreground">
                Diselesaikan oleh
                <span class="font-semibold text-foreground">{{
                    ticket.resolved_by_name ?? 'Pengelola'
                }}</span>
                · {{ formatIncidentDateTime(ticket.resolved_at) }}
            </p>
            <div
                v-if="ticket.resolution_note"
                class="rounded-xl bg-success/10 p-3 text-sm leading-6 break-words whitespace-pre-line text-foreground"
            >
                {{ ticket.resolution_note }}
            </div>
            <p v-if="ticket.cancellation" class="text-sm text-muted-foreground">
                Tiket diselesaikan otomatis karena subblok dibatalkan dan tidak
                dapat dibuka kembali.
            </p>

            <div
                v-if="ticket.follow_up_activity"
                class="space-y-2 rounded-xl border border-border p-3"
            >
                <p class="text-xs font-semibold text-muted-foreground">
                    Aktivitas tindak lanjut:
                    {{ ticket.follow_up_activity.name }}
                </p>
                <Link
                    :href="`/petugas/laporan/${ticket.follow_up_activity.id}`"
                    class="inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-primary underline"
                >
                    Lihat laporan tindak lanjut
                    <ChevronRight class="size-4" aria-hidden="true" />
                </Link>
            </div>

            <div v-if="ticket.can.reopen" class="border-t border-border pt-3">
                <Button
                    v-if="!reopenVisible"
                    type="button"
                    variant="outline"
                    class="w-full"
                    @click="reopenVisible = true"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                    Buka kembali tiket
                </Button>

                <form v-else class="space-y-3" @submit.prevent="submitReopen">
                    <label class="block space-y-1.5 text-sm">
                        <span class="font-medium"
                            >Alasan membuka kembali *</span
                        >
                        <textarea
                            v-model="reopenForm.reason"
                            rows="3"
                            maxlength="2000"
                            required
                            placeholder="Contoh: Masalah masih ditemukan di lokasi yang sama."
                            class="petugas-field w-full rounded-xl border border-border px-3 py-2 text-foreground"
                        />
                        <span
                            v-if="reopenForm.errors.reason"
                            class="block text-xs text-error"
                        >
                            {{ reopenForm.errors.reason }}
                        </span>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="reopenForm.processing"
                            @click="reopenVisible = false"
                        >
                            Batal
                        </Button>
                        <Button type="submit" :disabled="reopenForm.processing">
                            <LoaderCircle
                                v-if="reopenForm.processing"
                                class="size-4 animate-spin"
                            />
                            <RotateCcw v-else class="size-4" />
                            Buka kembali
                        </Button>
                    </div>
                </form>
            </div>
        </section>

        <section
            v-if="ticket.report"
            class="petugas-card space-y-3 p-4"
            aria-label="Rincian laporan insiden"
        >
            <div
                class="flex items-center justify-between gap-2 border-b border-border pb-3"
            >
                <h2 class="flex items-center gap-2 text-base font-semibold">
                    <ShieldAlert
                        class="size-4 text-warning"
                        aria-hidden="true"
                    />
                    Rincian laporan awal
                </h2>
                <Link
                    :href="`/petugas/laporan/${ticket.activity_id}`"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary underline"
                >
                    <FileText class="size-3.5" aria-hidden="true" />
                    Buka di Laporan
                </Link>
            </div>

            <dl
                v-if="
                    ticket.report.details.length ||
                    ticket.report.worker_count !== null
                "
                class="divide-y divide-border"
            >
                <div
                    v-if="ticket.report.worker_count !== null"
                    class="flex items-start justify-between gap-4 py-2.5 text-sm"
                >
                    <dt class="text-muted-foreground">Jumlah pekerja</dt>
                    <dd class="font-semibold">
                        {{ ticket.report.worker_count }} orang
                    </dd>
                </div>
                <div
                    v-for="(item, idx) in ticket.report.details"
                    :key="`${item.label}-${idx}`"
                    class="flex items-start justify-between gap-4 py-2.5 text-sm"
                >
                    <dt class="text-muted-foreground">{{ item.label }}</dt>
                    <dd class="max-w-[60%] text-right font-medium break-words">
                        {{ item.value }}
                    </dd>
                </div>
            </dl>

            <div v-if="ticket.report.photos.length" class="space-y-2">
                <p
                    class="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground"
                >
                    <Camera class="size-4 text-primary" aria-hidden="true" />
                    {{ ticket.report.photos.length }} foto dokumentasi
                </p>
                <div class="grid grid-cols-3 gap-2">
                    <template
                        v-for="(photo, idx) in ticket.report.photos"
                        :key="photo.id"
                    >
                        <a
                            v-if="!failedPhotoIds.has(photo.id)"
                            :href="photo.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="aspect-square overflow-hidden rounded-xl bg-muted"
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
    </div>
</template>

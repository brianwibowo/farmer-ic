<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Info, Siren, TriangleAlert } from '@lucide/vue';
import type { PetugasHomeIncident } from '@/types/petugas-home';

defineProps<{ incidents: { data: PetugasHomeIncident[]; total: number } }>();
const appearance = {
    light: { icon: Info, color: 'bg-info/10 text-info' },
    medium: {
        icon: TriangleAlert,
        color: 'bg-warning/15 text-warning',
    },
    emergency: { icon: Siren, color: 'bg-error/10 text-error' },
};
const date = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
function detailUrl(incident: PetugasHomeIncident): string {
    if (incident.detail_url) return incident.detail_url;
    return incident.ticket_id
        ? `/petugas/incidents/${incident.ticket_id}`
        : `/petugas/laporan/${incident.id}`;
}
</script>

<template>
    <section
        v-if="incidents.data.length"
        class="space-y-3"
        aria-label="Peringatan insiden lahan"
    >
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-foreground">
                Insiden aktif ({{ incidents.total }})
            </h2>
            <Link
                href="/petugas/incidents"
                class="inline-flex min-h-9 items-center gap-1 text-xs font-semibold text-primary hover:underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-ring"
            >
                Lihat semua
                <ChevronRight class="size-3.5" aria-hidden="true" />
            </Link>
        </div>
        <article
            v-for="incident in incidents.data"
            :key="incident.id"
            class="petugas-card p-4"
            :aria-label="`Insiden · ${incident.severity_label}`"
            :role="incident.severity === 'emergency' ? 'alert' : 'status'"
        >
            <Link
                :href="detailUrl(incident)"
                class="flex items-start gap-3 rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <span
                    class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full"
                    :class="appearance[incident.severity].color"
                >
                    <component
                        :is="appearance[incident.severity].icon"
                        class="size-4"
                        aria-hidden="true"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h3
                            class="text-sm leading-5 font-semibold break-words text-foreground"
                        >
                            {{
                                incident.description ||
                                incident.category ||
                                'Insiden lahan dilaporkan'
                            }}
                        </h3>
                        <ChevronRight
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5"
                    >
                        <span
                            class="rounded-full px-2 py-1 text-[11px] font-medium"
                            :class="appearance[incident.severity].color"
                            >{{ incident.severity_label }}</span
                        >
                        <time
                            :datetime="incident.reported_at"
                            class="text-xs text-muted-foreground"
                            >{{ date(incident.reported_at) }}</time
                        >
                    </div>
                    <p class="mt-2 text-xs leading-5 text-muted-foreground">
                        {{
                            incident.status === 'in_progress'
                                ? 'Dalam penanganan'
                                : 'Perlu ditindaklanjuti'
                        }}
                    </p>
                </div>
            </Link>
            <details
                v-if="incident.action"
                class="mt-2 ml-11 border-t border-border text-xs"
            >
                <summary
                    class="min-h-11 cursor-pointer py-3 font-medium text-muted-foreground hover:text-primary focus-visible:rounded focus-visible:outline-2 focus-visible:outline-ring"
                >
                    Arahan / tindakan
                </summary>
                <p class="mt-1 leading-5 break-words text-foreground">
                    {{ incident.action }}
                </p>
            </details>
        </article>
        <p
            v-if="incidents.total > incidents.data.length"
            class="text-xs text-muted-foreground"
        >
            Menampilkan {{ incidents.data.length }} dari
            {{ incidents.total }} insiden aktif, diurutkan berdasarkan
            kedaruratan.
        </p>
    </section>
</template>

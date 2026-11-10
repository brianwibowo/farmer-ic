<script setup lang="ts">
import { Info, ShieldAlert, Siren, TriangleAlert } from '@lucide/vue';
import type { PetugasHomeIncident } from '@/types/petugas-home';

defineProps<{ incidents: { data: PetugasHomeIncident[]; total: number } }>();
const appearance = {
    light: { icon: Info, color: 'bg-info/10 text-info' },
    medium: {
        icon: TriangleAlert,
        color: 'bg-warning/15 text-warning',
    },
    heavy: { icon: ShieldAlert, color: 'petugas-incident-heavy' },
    emergency: { icon: Siren, color: 'bg-error/10 text-error' },
};
const date = (value: string) =>
    new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta',
        day: 'numeric',
        month: 'short',
    }).format(new Date(value));
</script>

<template>
    <section
        v-if="incidents.data.length"
        class="space-y-3"
        aria-label="Peringatan insiden kebun"
    >
        <article
            v-for="incident in incidents.data"
            :key="incident.id"
            class="petugas-card flex items-start gap-3 p-4"
            :aria-label="`Insiden · ${incident.severity_label}`"
            :role="incident.severity === 'emergency' ? 'alert' : 'status'"
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
                <h3
                    class="text-sm leading-5 font-semibold break-words text-foreground"
                >
                    {{
                        incident.description ||
                        incident.category ||
                        'Insiden kebun dilaporkan'
                    }}
                </h3>
                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5">
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
                <details
                    v-if="incident.action"
                    class="mt-2 border-t border-border text-xs"
                >
                    <summary
                        class="min-h-11 cursor-pointer py-3 font-medium text-muted-foreground hover:text-primary focus-visible:rounded focus-visible:outline-2 focus-visible:outline-ring"
                    >
                        Tindakan penanganan
                    </summary>
                    <p class="mt-1 leading-5 break-words text-foreground">
                        {{ incident.action }}
                    </p>
                </details>
            </div>
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

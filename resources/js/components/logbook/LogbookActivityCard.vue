<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    Camera,
    ClipboardList,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { LogbookReportActivity } from '@/types/logbook';

const props = defineProps<{
    activity: LogbookReportActivity;
    showDetailLink?: boolean;
}>();

const failedPhotos = ref(new Set<string>());

function markPhotoFailed(id: string): void {
    failedPhotos.value = new Set([...failedPhotos.value, id]);
}

const isIncident = computed(() => props.activity.code === 'incident');

const locationSummary = computed(() =>
    [
        props.activity.block,
        props.activity.subblok ? `Subblok ${props.activity.subblok}` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <article class="admin-panel space-y-5">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <p
                    v-if="showDetailLink || activity.position !== null"
                    class="text-xs text-muted-foreground"
                >
                    <template
                        v-if="showDetailLink && activity.position !== null"
                    >
                        Kegiatan {{ activity.position }} ·
                    </template>
                    {{
                        activity.source === 'sop'
                            ? 'Tugas SOP'
                            : 'Aktivitas Mandiri'
                    }}
                </p>
                <h3
                    class="flex items-center gap-2 text-base font-semibold tracking-tight"
                >
                    <TriangleAlert
                        v-if="isIncident"
                        class="size-4 shrink-0 text-warning"
                        aria-hidden="true"
                    />
                    <ClipboardList
                        v-else
                        class="size-4 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <span>{{ activity.name }}</span>
                </h3>
                <p class="text-sm text-muted-foreground">
                    {{ activity.performer_name ?? 'Petugas tidak tersedia' }}
                    <template v-if="locationSummary">
                        · {{ locationSummary }}
                    </template>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="isIncident"
                    class="inline-flex items-center gap-1.5 rounded-full bg-warning/15 px-3 py-1.5 text-xs font-semibold text-warning"
                >
                    <TriangleAlert class="size-3.5" aria-hidden="true" />
                    Insiden
                </span>
                <span
                    class="rounded-full px-3 py-1.5 text-xs font-semibold"
                    :class="
                        activity.source === 'sop'
                            ? 'bg-accent text-accent-foreground'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{ activity.source === 'sop' ? 'SOP' : 'Mandiri' }}
                </span>
                <span
                    v-if="activity.worker_count !== null"
                    class="rounded-full bg-muted px-3 py-1.5 text-xs font-semibold text-muted-foreground tabular-nums"
                >
                    {{ activity.worker_count }} pekerja
                </span>
            </div>
        </header>

        <dl
            v-if="activity.details.length"
            class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="(detail, index) in activity.details"
                :key="`${detail.label}-${index}`"
                class="rounded-xl bg-background p-4"
            >
                <dt class="text-xs text-muted-foreground">
                    {{ detail.label }}
                </dt>
                <dd
                    class="mt-1.5 text-sm font-semibold break-words whitespace-pre-line"
                >
                    {{ detail.value }}
                </dd>
            </div>
        </dl>

        <div v-if="activity.notes" class="rounded-xl border border-border p-4">
            <h4 class="text-sm font-semibold">Catatan aktivitas</h4>
            <p
                class="mt-2 text-sm leading-6 whitespace-pre-wrap text-muted-foreground"
            >
                {{ activity.notes }}
            </p>
        </div>

        <div v-if="activity.photos.length" class="space-y-3">
            <h4 class="flex items-center gap-1.5 text-sm font-semibold">
                <Camera class="size-4 text-primary" aria-hidden="true" />
                Foto dokumentasi ({{ activity.photos.length }})
            </h4>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <a
                    v-for="(photo, index) in activity.photos"
                    :key="photo.id"
                    :href="photo.url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group overflow-hidden rounded-xl border border-border bg-muted focus-visible:outline-2 focus-visible:outline-ring"
                >
                    <div
                        v-if="failedPhotos.has(photo.id)"
                        class="flex aspect-[4/3] items-center justify-center p-3 text-center text-xs text-muted-foreground"
                    >
                        Foto tidak dapat dimuat
                    </div>
                    <img
                        v-else
                        :src="photo.url"
                        :alt="`Foto ${activity.name} ${index + 1}`"
                        class="aspect-[4/3] w-full object-cover transition-transform duration-200 group-hover:scale-[1.02]"
                        @error="markPhotoFailed(photo.id)"
                    />
                </a>
            </div>
        </div>

        <footer
            v-if="showDetailLink"
            class="flex justify-end border-t border-border pt-4"
        >
            <Button as-child variant="outline" size="sm">
                <Link :href="'/admin/logbook/aktivitas/' + activity.id">
                    Detail aktivitas <ArrowUpRight />
                </Link>
            </Button>
        </footer>
    </article>
</template>

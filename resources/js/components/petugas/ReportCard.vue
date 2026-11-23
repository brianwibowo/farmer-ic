<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Camera,
    CalendarDays,
    ChevronRight,
    ClipboardList,
    ImageOff,
    MapPin,
    UsersRound,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { formatReportDate } from '@/lib/petugasReport';
import type { PetugasReportSummary } from '@/types/petugas-report';

const props = defineProps<{
    report: PetugasReportSummary;
    href: string;
}>();

const imageFailed = ref(false);
watch(
    () => props.report.photo,
    () => (imageFailed.value = false),
);
</script>

<template>
    <article class="petugas-card overflow-hidden">
        <Link
            :href="href"
            class="group flex items-start gap-3 rounded-[inherit] p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
        >
            <div
                class="relative aspect-square w-1/4 max-w-24 shrink-0 overflow-hidden rounded-xl bg-muted"
            >
                <img
                    v-if="report.photo && !imageFailed"
                    :src="report.photo"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover"
                    @error="imageFailed = true"
                />
                <div
                    v-else
                    class="flex size-full flex-col items-center justify-center gap-1 p-1 text-center text-muted-foreground"
                >
                    <ImageOff
                        v-if="report.photo_count"
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <Camera v-else class="size-4 shrink-0" aria-hidden="true" />
                    <span class="text-[10px] leading-3">
                        {{
                            report.photo_count
                                ? 'Foto tidak tersedia'
                                : 'Belum ada foto'
                        }}
                    </span>
                </div>
                <span
                    v-if="report.photo && !imageFailed && report.photo_count"
                    class="absolute right-1.5 bottom-1.5 inline-flex items-center gap-1 rounded-md bg-foreground/75 px-1.5 py-1 text-[10px] font-semibold text-background"
                    :aria-label="`${report.photo_count} foto tercatat`"
                >
                    <Camera class="size-3" aria-hidden="true" />
                    {{ report.photo_count }}
                </span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3
                            class="text-base leading-6 font-semibold break-words text-foreground"
                        >
                            {{ report.name }}
                        </h3>
                        <span
                            class="inline-flex max-w-full rounded-full bg-primary/10 px-2.5 py-1 text-[11px] leading-4 font-semibold text-primary"
                        >
                            {{ report.source }}
                        </span>
                    </div>
                    <ChevronRight
                        class="mt-1 size-4 shrink-0 text-muted-foreground group-hover:text-primary"
                        aria-hidden="true"
                    />
                </div>
                <div class="mt-2 space-y-1 text-xs text-muted-foreground">
                    <p class="flex items-start gap-1.5">
                        <CalendarDays
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ formatReportDate(report.date) }}</span>
                    </p>
                    <p class="flex items-start gap-1.5">
                        <MapPin
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 break-words">{{
                            report.location
                        }}</span>
                    </p>
                    <p
                        v-if="report.worker_count !== null"
                        class="flex items-start gap-1.5"
                    >
                        <UsersRound
                            class="mt-0.5 size-3.5 shrink-0 text-primary"
                            aria-hidden="true"
                        />
                        <span>{{ report.worker_count }} pekerja</span>
                    </p>
                </div>
                <p
                    v-if="report.highlight"
                    class="mt-2 flex items-start gap-1.5 text-xs font-medium text-primary"
                >
                    <ClipboardList
                        class="mt-0.5 size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span class="line-clamp-2 break-words">{{
                        report.highlight
                    }}</span>
                </p>
            </div>
        </Link>
    </article>
</template>

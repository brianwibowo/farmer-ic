<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowUpRight,
    BookOpen,
    CalendarDays,
    ClipboardCheck,
    LandPlot,
    MapPin,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import LogbookActivityCard from '@/components/logbook/LogbookActivityCard.vue';
import { Button } from '@/components/ui/button';
import {
    farmLocation,
    logbookDate,
    type LogbookActivityShow,
    type LogbookFarm,
} from '@/types/logbook';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    farm: LogbookFarm;
    activity: LogbookActivityShow;
}>();

const locationLabel = computed(
    () =>
        [
            props.activity.block,
            props.activity.subblok ? `Subblok ${props.activity.subblok}` : null,
        ]
            .filter(Boolean)
            .join(' · ') || props.farm.name,
);
</script>

<template>
    <Head :title="`${activity.name} - ${farm.name}`" />
    <div class="space-y-[18px]">
        <div class="flex min-w-0 items-start gap-2">
            <PageBackButton href="/admin/logbook" label="Kembali ke logbook" />
            <header class="min-w-0 flex-1">
                <p
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Detail aktivitas
                </p>
                <h1 class="admin-page-title mt-1 text-primary">
                    {{ activity.name }}
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ farm.name }} · {{ logbookDate(activity.work_date) }}
                </p>
            </header>
        </div>

        <div
            class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]"
        >
            <div class="min-w-0 space-y-[18px]">
                <section
                    class="admin-panel"
                    aria-labelledby="logbook-activity-meta-title"
                >
                    <h2
                        id="logbook-activity-meta-title"
                        class="admin-section-title"
                    >
                        Informasi aktivitas
                    </h2>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <CalendarDays
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />Tanggal kerja
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {{ logbookDate(activity.work_date) }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <UserRound
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />Pelaksana
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {{
                                    activity.performer_name ??
                                    'Pelaksana tidak tersedia'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <MapPin
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />Lokasi
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {{ locationLabel }}
                            </dd>
                        </div>
                        <div v-if="activity.task">
                            <dt
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <ClipboardCheck
                                    class="size-4 text-primary"
                                    aria-hidden="true"
                                />Tugas SOP
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {{ activity.task.name }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <LogbookActivityCard :activity="activity" />
            </div>

            <aside class="space-y-[18px]">
                <section class="admin-panel space-y-4">
                    <div class="flex items-center gap-2">
                        <LandPlot class="size-5 text-primary" />
                        <h2 class="admin-section-title">Kebun laporan</h2>
                    </div>
                    <p class="text-base font-semibold">{{ farm.name }}</p>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ farmLocation(farm) }}
                    </p>
                    <div class="flex flex-wrap gap-3 pt-1">
                        <Link
                            :href="'/admin/logbook/' + farm.id"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-primary underline underline-offset-4"
                        >
                            Riwayat kebun
                        </Link>
                        <Link
                            :href="'/admin/farm-management/' + farm.id"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-muted-foreground underline underline-offset-4 hover:text-primary"
                        >
                            Detail kebun
                        </Link>
                    </div>
                </section>

                <section
                    v-if="activity.daily_log"
                    class="admin-panel space-y-4"
                >
                    <div class="flex items-center gap-2">
                        <BookOpen class="size-5 text-primary" />
                        <h2 class="admin-section-title">Laporan harian</h2>
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Aktivitas ini bagian dari laporan tanggal
                        <strong class="text-foreground">{{
                            logbookDate(activity.daily_log.work_date)
                        }}</strong
                        ><template
                            v-if="activity.daily_log.activities_count > 1"
                        >
                            ({{ activity.daily_log.activities_count }} aktivitas
                            pada hari tersebut)</template
                        >.
                    </p>
                    <dl class="space-y-3 border-t border-border pt-4 text-xs">
                        <div>
                            <dt class="text-muted-foreground">
                                Pencatat pertama
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{
                                    activity.daily_log.recorder_name ??
                                    'Petugas kebun'
                                }}
                            </dd>
                        </div>
                        <div v-if="activity.created_at">
                            <dt class="text-muted-foreground">
                                Waktu pencatatan
                            </dt>
                            <dd class="mt-1 font-medium">
                                {{ logbookDate(activity.created_at) }}
                            </dd>
                        </div>
                    </dl>
                    <Button as-child variant="outline" class="w-full">
                        <Link
                            :href="
                                '/admin/logbook/' +
                                farm.id +
                                '/' +
                                activity.daily_log.id
                            "
                        >
                            Lihat laporan harian lengkap <ArrowUpRight />
                        </Link>
                    </Button>
                </section>
            </aside>
        </div>
    </div>
</template>

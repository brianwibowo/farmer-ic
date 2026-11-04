<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    LandPlot,
    UserRound,
    Users,
} from '@lucide/vue';
import LogbookActivities from '@/components/logbook/LogbookActivities.vue';
import {
    logbookDate,
    farmLocation,
    type LogbookDetail,
    type LogbookFarm,
} from '@/types/logbook';
defineOptions({ inheritAttrs: false });
defineProps<{ farm: LogbookFarm; log: LogbookDetail }>();
</script>
<template>
    <Head :title="'Detail Logbook - ' + farm.name" />
    <div class="space-y-[18px]">
        <Link
            :href="'/admin/logbook/' + farm.id"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Kembali ke logbook kebun</Link
        >
        <header>
            <p
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Laporan harian
            </p>
            <h1 class="admin-page-title mt-1 text-primary">
                Logbook {{ logbookDate(log.work_date) }}
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm.name }} · {{ farmLocation(farm) }}
            </p>
        </header>
        <div
            class="grid items-start gap-[18px] xl:grid-cols-[minmax(0,2fr)_minmax(260px,1fr)]"
        >
            <div class="min-w-0 space-y-[18px]">
                <section
                    class="admin-panel"
                    aria-labelledby="logbook-summary-title"
                >
                    <h2 id="logbook-summary-title" class="admin-section-title">
                        Informasi laporan
                    </h2>
                    <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div
                            v-for="item in [
                                {
                                    label: 'Tanggal kerja',
                                    value: logbookDate(log.work_date),
                                    icon: CalendarDays,
                                },
                                {
                                    label: 'Waktu kerja (WIB)',
                                    value: log.start_time + '–' + log.end_time,
                                    icon: Clock3,
                                },
                                {
                                    label: 'Jumlah pekerja',
                                    value: log.worker_count + ' orang',
                                    icon: Users,
                                },
                                {
                                    label: 'Pelapor',
                                    value:
                                        log.recorder_name ??
                                        'Pelapor tidak tersedia',
                                    icon: UserRound,
                                },
                            ]"
                            :key="item.label"
                        >
                            <dt
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4 text-primary"
                                />{{ item.label }}
                            </dt>
                            <dd class="mt-2 text-base font-semibold">
                                {{ item.value }}
                            </dd>
                        </div>
                    </dl>
                </section>
                <section class="admin-panel">
                    <h2 class="admin-section-title">Catatan harian</h2>
                    <p
                        class="mt-3 text-sm leading-7 whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ log.notes || 'Tidak ada catatan harian tambahan.' }}
                    </p>
                </section>
                <LogbookActivities :activities="log.activities" />
            </div>
            <aside class="admin-panel space-y-5">
                <div class="flex items-center gap-2">
                    <LandPlot class="size-5 text-primary" />
                    <h2 class="admin-section-title">Kebun laporan</h2>
                </div>
                <p class="text-base font-semibold">{{ farm.name }}</p>
                <p class="text-sm leading-6 text-muted-foreground">
                    {{ farmLocation(farm) }}
                </p>
                <Link
                    :href="'/admin/farm-management/' + farm.id"
                    class="inline-flex text-sm font-semibold text-primary underline underline-offset-4"
                    >Lihat kebun</Link
                >
                <dl class="space-y-4 border-t border-border pt-4 text-xs">
                    <div>
                        <dt class="text-muted-foreground">
                            Jumlah kegiatan yang ditampilkan
                        </dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums">
                            {{ log.activities.length }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Tanggal pencatatan
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ logbookDate(log.created_at) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">
                            Terakhir diperbarui
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{
                                log.updated_at
                                    ? logbookDate(log.updated_at)
                                    : 'Belum diperbarui'
                            }}
                        </dd>
                    </div>
                </dl>
            </aside>
        </div>
    </div>
</template>

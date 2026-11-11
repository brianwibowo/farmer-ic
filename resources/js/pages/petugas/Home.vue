<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Sprout, Tractor, Wheat } from '@lucide/vue';
import { computed } from 'vue';
import DashboardWeather from '@/components/dashboard/DashboardWeather.vue';
import DashboardHeader from '@/components/farmer/DashboardHeader.vue';
import { Button } from '@/components/ui/button';
import HomeSopTask from '@/components/petugas/HomeSopTask.vue';
import HomeIncidentAlerts from '@/components/petugas/HomeIncidentAlerts.vue';
import type { PetugasHome } from '@/types/petugas-home';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ home: PetugasHome }>();
const cards = computed(() => [
    {
        label: 'Pra Tanam',
        value: props.home.summary.pre_planting,
        icon: Tractor,
    },
    {
        label: 'Penanaman',
        value: props.home.summary.planting,
        icon: Sprout,
    },
    {
        label: 'Panen',
        value: props.home.summary.harvest,
        icon: Wheat,
    },
]);
</script>

<template>
    <Head title="Beranda · Petugas" />
    <div class="petugas-home">
        <h1 class="sr-only">Beranda petugas</h1>
        <section class="petugas-home-hero" aria-label="Ringkasan kebun">
            <DashboardHeader
                dashboard-theme
                :show-brand="false"
                class="petugas-home-actions"
            >
                <DashboardWeather
                    compact
                    :farms="home.farm ? [home.farm] : []"
                    weather-base-url="/petugas/weather"
                />
            </DashboardHeader>
            <section
                class="petugas-home-hero-metrics grid grid-cols-3 px-6"
                aria-label="Ringkasan batch kebun"
            >
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="flex w-fit min-w-0 flex-col items-center justify-self-center text-center first:justify-self-start last:justify-self-end min-w-[73px]"
                >
                    <h2
                        class="text-xs leading-5 font-medium min-[360px]:text-[13px]"
                    >
                        {{ card.label }}
                    </h2>
                    <div class="mt-2 flex items-center justify-center gap-2">
                        <component
                            :is="card.icon"
                            class="size-5 shrink-0"
                            aria-hidden="true"
                        />
                        <p class="petugas-metric-value">
                            {{
                                new Intl.NumberFormat('id-ID').format(
                                    card.value,
                                )
                            }}
                        </p>
                    </div>
                    <p class="mt-2 text-xs leading-4 font-normal">Batch</p>
                </article>
            </section>
        </section>
        <section
            aria-labelledby="home-today-tasks"
            class="petugas-home-tasks space-y-4"
        >
            <HomeIncidentAlerts :incidents="home.incidents" />
            <header class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 id="home-today-tasks" class="admin-section-title">
                    To-do hari ini
                </h2>
                <span
                    v-if="home.task_summary.total"
                    class="text-xs font-medium text-secondary dark:text-primary"
                    >{{ home.task_summary.completed }}/{{
                        home.task_summary.total
                    }}
                    selesai</span
                >
            </header>
            <div
                v-if="home.task_summary.total"
                role="progressbar"
                aria-label="Penyelesaian tugas hari ini"
                :aria-valuenow="home.task_summary.completed"
                :aria-valuemax="home.task_summary.total"
                :aria-valuemin="0"
                class="h-1.5 overflow-hidden rounded-full bg-border/70"
            >
                <div
                    class="h-full rounded-full bg-primary"
                    :style="{
                        width: `${Math.min(100, Math.max(0, (home.task_summary.completed / home.task_summary.total) * 100))}%`,
                    }"
                />
            </div>
            <div v-if="!home.farm" class="px-2 py-10 text-center">
                <h3 class="text-sm font-medium">Belum ada kebun penugasan</h3>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Hubungi admin untuk penugasan kebun.
                </p>
            </div>
            <div v-else-if="!home.tasks.total" class="px-2 py-10 text-center">
                <h3 class="text-sm text-muted-foreground">
                    Belum ada tugas hari ini
                </h3>
            </div>
            <template v-else>
                <ul class="space-y-3" aria-label="Tugas SOP terjadwal hari ini">
                    <li v-for="task in home.tasks.data" :key="task.id">
                        <HomeSopTask :task="task" />
                    </li>
                </ul>
                <nav
                    v-if="home.tasks.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-2 pt-1 [&_a]:h-11 [&_a]:rounded-xl [&_button]:h-11 [&_button]:rounded-xl"
                    aria-label="Halaman tugas hari ini"
                >
                    <Button
                        v-if="home.tasks.prev_page_url"
                        as-child
                        variant="outline"
                        size="sm"
                        ><Link :href="home.tasks.prev_page_url" preserve-scroll
                            >Sebelumnya</Link
                        ></Button
                    ><Button v-else variant="outline" size="sm" disabled
                        >Sebelumnya</Button
                    ><span
                        class="text-xs text-muted-foreground"
                        aria-current="page"
                        >{{ home.tasks.current_page }} /
                        {{ home.tasks.last_page }}</span
                    ><Button
                        v-if="home.tasks.next_page_url"
                        as-child
                        variant="outline"
                        size="sm"
                        ><Link :href="home.tasks.next_page_url" preserve-scroll
                            >Berikutnya</Link
                        ></Button
                    ><Button v-else variant="outline" size="sm" disabled
                        >Berikutnya</Button
                    >
                </nav>
            </template>
        </section>
    </div>
</template>

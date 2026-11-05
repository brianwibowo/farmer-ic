<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, LandPlot, Sprout, Tractor, Wheat } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import HomeSopTask from '@/components/petugas/HomeSopTask.vue';
import type { PetugasHome } from '@/types/petugas-home';

defineOptions({ inheritAttrs: false });

const props = defineProps<{ home: PetugasHome }>();
const page = usePage();
const name = computed(
    () => page.props.auth.user.name.trim().split(/\s+/)[0] || 'Petugas',
);
const date = computed(() =>
    new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(props.home.date + 'T00:00:00+07:00')),
);
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
    <div class="space-y-5">
        <header>
            <p class="text-xs leading-5 text-muted-foreground">{{ date }}</p>
            <h1 class="mt-1 text-xl leading-7 font-semibold tracking-tight">
                Selamat datang,
                <span class="text-secondary dark:text-primary"
                    >{{ name }}!</span
                >
            </h1>
        </header>
        <div
            v-if="home.farm"
            class="flex items-center gap-2 text-sm font-medium text-secondary dark:text-primary"
        >
            <LandPlot class="size-4 shrink-0" aria-hidden="true" /><span>{{
                home.farm.name
            }}</span>
        </div>
        <section
            class="grid grid-cols-3 gap-2"
            aria-label="Ringkasan batch kebun"
        >
            <article
                v-for="card in cards"
                :key="card.label"
                class="min-w-0 rounded-xl border border-border bg-card p-3"
            >
                <component
                    :is="card.icon"
                    class="mb-3 size-5 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
                <h2 class="text-xs leading-5 font-medium">{{ card.label }}</h2>
                <p class="mt-1 text-[28px] leading-9 font-bold tabular-nums">
                    {{ new Intl.NumberFormat('id-ID').format(card.value)
                    }}<span
                        class="ml-1 text-[11px] font-normal text-muted-foreground"
                        >batch</span
                    >
                </p>
            </article>
        </section>
        <section aria-labelledby="home-today-tasks" class="space-y-3">
            <header class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 id="home-today-tasks" class="text-lg font-semibold">
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
                v-if="!home.farm"
                class="rounded-xl border border-dashed border-border bg-card px-5 py-9 text-center"
            >
                <LandPlot
                    class="mx-auto size-9 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
                <h3 class="mt-3 text-base font-semibold">
                    Belum ada kebun penugasan
                </h3>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Hubungi admin untuk penugasan kebun.
                </p>
            </div>
            <div
                v-else-if="!home.tasks.total"
                class="rounded-xl border border-dashed border-border bg-card px-5 py-9 text-center"
            >
                <ClipboardList
                    class="mx-auto size-9 text-secondary dark:text-primary"
                    aria-hidden="true"
                />
                <h3 class="mt-3 text-base font-semibold">
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

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DailyReportContent from '@/components/petugas/DailyReportContent.vue';
import type { DailyReport } from '@/types/daily-activity';

const props = defineProps<{
    farm: { id: string; name: string } | null;
    date: string;
    today: string;
    log: DailyReport | null;
    canUpdate: boolean;
    canRecord: boolean;
}>();
const pending = ref(false);
function changeDate(event: Event): void {
    const date = (event.target as HTMLInputElement).value;
    if (!date || date > props.today) return;
    router.get(
        '/petugas/aktivitas',
        { work_date: date },
        {
            onStart: () => {
                pending.value = true;
            },
            onFinish: () => {
                pending.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Aktivitas Harian" />
    <div class="min-w-0 space-y-5 text-foreground" :aria-busy="pending">
        <header>
            <p class="text-xs text-muted-foreground">Ruang Petugas</p>
            <h1 class="mt-2 text-2xl leading-8 font-semibold">
                Aktivitas harian
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm?.name ?? 'Belum ada penugasan kebun' }} · Satu laporan
                bersama per hari.
            </p>
        </header>
        <p
            v-if="!farm"
            class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
        >
            Anda belum memiliki penugasan kebun. Hubungi Admin untuk mulai
            mencatat aktivitas.
        </p>
        <label v-if="farm" class="block space-y-2 text-sm"
            ><span>Tanggal kerja</span
            ><input
                :value="date"
                type="date"
                required
                :max="today"
                :disabled="pending"
                class="block min-h-12 w-full rounded-xl border border-border bg-background px-3 text-base [color-scheme:light] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring dark:[color-scheme:dark]"
                @change="changeDate"
        /></label>
        <p v-if="pending" role="status" class="text-sm text-muted-foreground">
            Memuat laporan harian…
        </p>
        <DailyReportContent
            v-else-if="farm"
            :farm="farm"
            :date="date"
            :log="log"
            :can-update="canUpdate"
            :can-record="canRecord"
        />
        <nav
            v-if="farm"
            aria-label="Pencatatan pekerjaan"
            class="flex flex-wrap gap-x-4 text-sm"
        >
            <Link
                :href="`/petugas/tasks?work_date=${date}`"
                class="inline-flex min-h-11 items-center text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >To-do SOP</Link
            ><Link
                :href="`/petugas/penanaman?work_date=${date}`"
                class="inline-flex min-h-11 items-center text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >Penanaman aktual</Link
            >
        </nav>
    </div>
</template>

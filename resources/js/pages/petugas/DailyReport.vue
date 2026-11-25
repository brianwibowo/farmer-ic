<script setup lang="ts">
import PageBackButton from '@/components/PageBackButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DailyReportContent from '@/components/petugas/DailyReportContent.vue';
import { withPetugasBack } from '@/lib/petugasBackLink';
import type { DailyReport } from '@/types/daily-activity';

const props = defineProps<{
    farm: { id: string; name: string } | null;
    date: string;
    today: string;
    log: DailyReport | null;
    canRecord: boolean;
}>();
const pending = ref(false);
const dailyBackUrl = computed(
    () => `/petugas/laporan/harian?work_date=${props.date}`,
);
function changeDate(event: Event): void {
    const date = (event.target as HTMLInputElement).value;
    if (!date || date > props.today) return;
    router.get(
        '/petugas/laporan/harian',
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
            <div class="flex min-w-0 items-start gap-2">
                <PageBackButton
                    href="/petugas/laporan"
                    label="Kembali ke laporan"
                />
                <h1 class="text-2xl leading-8 font-semibold">
                    Aktivitas harian
                </h1>
            </div>
            <p class="mt-2 text-sm text-muted-foreground">
                {{ farm?.name ?? 'Belum ada penugasan lahan' }} · Satu laporan
                bersama per hari.
            </p>
        </header>
        <p
            v-if="!farm"
            class="rounded-xl border border-dashed border-border p-5 text-sm leading-6 text-muted-foreground"
        >
            Anda belum memiliki penugasan lahan. Hubungi Admin untuk mulai
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
            :can-record="canRecord"
        />
        <nav
            v-if="farm"
            aria-label="Pencatatan pekerjaan"
            class="flex flex-wrap gap-x-4 text-sm"
        >
            <Link
                :href="`/petugas/aktivitas?work_date=${date}`"
                class="inline-flex min-h-11 items-center text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >To-do SOP</Link
            ><Link
                :href="
                    withPetugasBack(
                        `/petugas/penanaman?work_date=${date}`,
                        dailyBackUrl,
                    )
                "
                class="inline-flex min-h-11 items-center text-primary underline focus-visible:outline-2 focus-visible:outline-ring"
                >Penanaman aktual</Link
            >
        </nav>
    </div>
</template>

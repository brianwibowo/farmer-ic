<script setup lang="ts">
import {
    CheckCircle2,
    CircleAlert,
    CircleX,
    LandPlot,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import type { SeedType } from '@/data/kebun';
import { listMappedKebun, type MappedKebun } from '@/services/kebunService';

const kebun = ref<MappedKebun[]>([]);
const searchQuery = ref('');
const selectedSeed = ref<'all' | SeedType>('all');
const isLoading = ref(true);
const errorMessage = ref('');

const filteredKebun = computed(() => {
    const query = searchQuery.value.trim().toLocaleLowerCase();

    return kebun.value.filter((item) => {
        const searchable = [
            item.name,
            item.city,
            item.district,
            item.seedType,
            item.seedTypeDetail ?? '',
            item.healthStatus,
        ]
            .join(' ')
            .toLocaleLowerCase();
        const matchesSearch = !query || searchable.includes(query);
        const matchesSeed = selectedSeed.value === 'all' || item.seedType === selectedSeed.value;

        return matchesSearch && matchesSeed;
    });
});

// Summary counts follow the active search/commodity filter so they always match
// the list below.
const healthyCount = computed(() => filteredKebun.value.filter((item) => item.healthStatus === 'Sehat').length);
const attentionCount = computed(() => filteredKebun.value.filter((item) => item.healthStatus === 'Perlu Perhatian').length);
const criticalCount = computed(() => filteredKebun.value.filter((item) => item.healthStatus === 'Kritis').length);

function healthMeta(status: MappedKebun['healthStatus']) {
    if (status === 'Sehat') {
        return { icon: CheckCircle2, label: 'Sehat', class: 'border-success/25 bg-success/10 text-success' };
    }
    if (status === 'Kritis') {
        return { icon: CircleX, label: 'Kritis', class: 'border-error/25 bg-error/10 text-error' };
    }
    return { icon: CircleAlert, label: 'Perlu perhatian', class: 'border-warning/45 bg-warning/30 text-warning-foreground' };
}

async function loadKebun(): Promise<void> {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        kebun.value = await listMappedKebun();
    } catch {
        errorMessage.value = 'Data kebun belum dapat dimuat. Coba lagi.';
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    void loadKebun();
});
</script>

<template> 
    <section class="mt-1" aria-label="Ringkasan kondisi kebun">
        <div class="flex items-center justify-between rounded-2xl border border-primary/20 bg-surface/35 p-4">
            <div>
                <p class="text-xs text-muted-foreground">Total kebun terdaftar</p>
                <p class="mt-1 text-3xl leading-9 font-semibold text-secondary">{{ filteredKebun.length }}</p>
            </div>
            <span class="flex size-11 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                <LandPlot :size="22" aria-hidden="true" />
            </span>
        </div>
        <div class="mt-3 grid grid-cols-3 gap-2">
            <article class="flex items-center gap-1.5 rounded-2xl border border-success/25 bg-success/10 px-2 py-2.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-success/15 text-success">
                    <component :is="healthMeta('Sehat').icon" :size="15" aria-hidden="true" />
                </span>
                <p class="text-lg leading-6 font-semibold text-success">{{ healthyCount }}</p>
                <p class="truncate text-[10px] font-medium text-success/80">Sehat</p>
            </article>
            <article class="flex items-center gap-1.5 rounded-2xl border border-warning/45 bg-warning/20 px-2 py-2.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-warning/30 text-warning-foreground">
                    <component :is="healthMeta('Perlu Perhatian').icon" :size="15" aria-hidden="true" />
                </span>
                <p class="text-lg leading-6 font-semibold text-warning-foreground">{{ attentionCount }}</p>
                <p class="truncate text-[10px] font-medium text-warning-foreground">Perhatian</p>
            </article>
            <article class="flex items-center gap-1.5 rounded-2xl border border-error/25 bg-error/10 px-2 py-2.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-error/10 text-error">
                    <component :is="healthMeta('Kritis').icon" :size="15" aria-hidden="true" />
                </span>
                <p class="text-lg leading-6 font-semibold text-error">{{ criticalCount }}</p>
                <p class="truncate text-[10px] font-medium text-error">Kritis</p>
            </article>
        </div>
    </section>
</template>
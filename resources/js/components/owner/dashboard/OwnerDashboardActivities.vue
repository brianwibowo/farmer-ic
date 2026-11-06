<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppSelect from '@/components/selects/AppSelect.vue';
import AppSelectOption from '@/components/selects/AppSelectOption.vue';
import { Button } from '@/components/ui/button';
import type { DashboardActivity, DashboardOfficer } from '@/types/dashboard';
import { ownerStatus } from '@/types/owner';

const props = defineProps<{
    activities: DashboardActivity[];
    officers: DashboardOfficer[];
}>();
const search = ref('');
const officerId = ref('');
const hasFilters = computed(() =>
    Boolean(search.value.trim() || officerId.value),
);
const filtered = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('id-ID');
    return props.activities.filter(
        (activity) =>
            (!officerId.value || activity.officerId === officerId.value) &&
            `${activity.title} ${activity.description ?? ''} ${activity.farm} ${activity.officerName}`
                .toLocaleLowerCase('id-ID')
                .includes(query),
    );
});
watch(
    () => props.officers,
    (officers) => {
        if (
            officerId.value &&
            !officers.some((officer) => officer.id === officerId.value)
        )
            officerId.value = '';
    },
);
function reset(): void {
    search.value = '';
    officerId.value = '';
}
function statusLabel(status: string): string {
    const normalized = status.trim().toLocaleLowerCase('id-ID');
    return (
        ownerStatus[normalized] ?? (normalized === 'done' ? 'Selesai' : status)
    );
}
function statusTone(status: string): string {
    const normalized = status.trim().toLocaleLowerCase('id-ID');
    if (['completed', 'done', 'selesai'].includes(normalized))
        return 'bg-success/15 text-success';
    if (
        [
            'in_progress',
            'active',
            'submitted',
            'berlangsung',
            'dalam proses',
        ].includes(normalized)
    )
        return 'bg-primary/15 text-secondary dark:text-primary';
    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <section
        class="min-w-0 overflow-hidden rounded-xl border border-border bg-card"
        aria-labelledby="owner-activities-heading"
    >
        <header class="flex items-start justify-between gap-3 p-4">
            <div class="min-w-0">
                <h2
                    id="owner-activities-heading"
                    class="text-base font-semibold"
                >
                    Aktivitas lapangan
                </h2>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    Catatan terbaru dari kebun yang dapat Anda lihat.
                </p>
            </div>
            <Link
                href="/aktivitas"
                class="inline-flex min-h-11 shrink-0 items-center gap-1 rounded-lg px-1 text-xs font-medium text-secondary hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                >Lihat semua<ArrowUpRight class="size-3.5" aria-hidden="true"
            /></Link>
        </header>
        <div
            class="space-y-3 border-y border-border bg-muted/30 p-4 sm:grid sm:grid-cols-2 sm:gap-4 sm:space-y-0 lg:p-5"
        >
            <div>
                <label
                    for="owner-dashboard-officer"
                    class="mb-1.5 block text-sm font-medium"
                    >Petugas</label
                >
                <AppSelect
                    id="owner-dashboard-officer"
                    v-model="officerId"
                    aria-controls="owner-dashboard-activity-results"
                >
                    <AppSelectOption value="">Semua petugas</AppSelectOption>
                    <AppSelectOption
                        v-for="officer in officers"
                        :key="officer.id"
                        :value="officer.id"
                        >{{ officer.name }}</AppSelectOption
                    >
                </AppSelect>
            </div>
            <div>
                <label
                    for="owner-dashboard-activity-search"
                    class="mb-1.5 block text-sm font-medium"
                    >Cari kegiatan</label
                >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        id="owner-dashboard-activity-search"
                        v-model="search"
                        type="search"
                        placeholder="Aktivitas, kebun, atau petugas"
                        aria-controls="owner-dashboard-activity-results"
                        class="min-h-11 w-full min-w-0 rounded-xl border border-input bg-background py-2 pr-3 pl-10 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring"
                    />
                </div>
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-2 sm:col-span-2"
            >
                <p
                    role="status"
                    aria-live="polite"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    Menampilkan {{ filtered.length }} dari
                    {{ activities.length }} aktivitas terbaru.
                </p>
                <Button
                    v-if="hasFilters"
                    type="button"
                    variant="outline"
                    size="sm"
                    class="min-h-11"
                    @click="reset"
                    >Reset filter</Button
                >
            </div>
        </div>
        <div id="owner-dashboard-activity-results">
            <ul
                v-if="filtered.length"
                class="divide-y divide-border lg:grid lg:grid-cols-2 lg:gap-4 lg:divide-y-0 lg:p-4 2xl:grid-cols-3"
                aria-label="Daftar aktivitas lapangan"
            >
                <li
                    v-for="activity in filtered"
                    :key="activity.id"
                    class="min-w-0 lg:overflow-hidden lg:rounded-xl lg:border lg:border-border"
                >
                    <Link
                        :href="`/aktivitas/${encodeURIComponent(activity.id)}`"
                        class="block min-w-0 space-y-3 p-4 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none lg:h-full lg:p-5"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <h3
                                class="min-w-0 flex-1 basis-36 text-sm leading-6 font-semibold break-words"
                            >
                                {{ activity.title }}
                            </h3>
                            <span
                                class="max-w-full rounded-full px-2 py-1 text-xs leading-5 font-medium break-words"
                                :class="statusTone(activity.status)"
                                >{{ statusLabel(activity.status) }}</span
                            >
                        </div>
                        <p
                            v-if="activity.description"
                            class="text-sm leading-6 break-words text-muted-foreground"
                        >
                            {{ activity.description }}
                        </p>
                        <dl
                            class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-xs leading-5"
                        >
                            <dt class="text-muted-foreground">Kebun</dt>
                            <dd class="break-words">{{ activity.farm }}</dd>
                            <dt class="text-muted-foreground">Petugas</dt>
                            <dd class="break-words">
                                {{ activity.officerName }}
                            </dd>
                            <dt class="text-muted-foreground">Tanggal</dt>
                            <dd class="break-words">{{ activity.date }}</dd>
                        </dl>
                    </Link>
                </li>
            </ul>
            <div v-else role="status" class="space-y-2 p-5 text-center">
                <p class="text-sm font-medium">
                    {{
                        hasFilters
                            ? 'Tidak ada aktivitas yang cocok.'
                            : 'Belum ada aktivitas lapangan.'
                    }}
                </p>
                <p class="text-xs leading-5 text-muted-foreground">
                    {{
                        hasFilters
                            ? 'Ubah kata kunci atau pilih petugas lain.'
                            : 'Catatan petugas pada kebun dalam cakupan Anda akan muncul di sini.'
                    }}
                </p>
            </div>
        </div>
        <p
            class="border-t border-border px-4 py-3 text-xs leading-5 text-muted-foreground"
        >
            Hingga 30 aktivitas terbaru. Filter periode berlaku untuk grafik
            Aktivitas lahan.
        </p>
    </section>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, Search, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import type { DashboardActivity, DashboardOfficer } from '@/types/dashboard';

const props = defineProps<{
    activities: DashboardActivity[];
    officers: DashboardOfficer[];
}>();
const activitySearch = ref('');
const selectedOfficerId = ref<string | null>(null);
const selectedOfficer = computed(() =>
    props.officers.find((officer) => officer.id === selectedOfficerId.value),
);
const hasFilters = computed(() =>
    Boolean(activitySearch.value.trim() || selectedOfficerId.value),
);
const visibleActivities = computed(() => {
    const query = activitySearch.value.trim().toLocaleLowerCase('id-ID');
    return props.activities.filter((activity) => {
        const matchesOfficer =
            selectedOfficerId.value === null ||
            activity.officerId === selectedOfficerId.value;
        const matchesSearch =
            `${activity.title} ${activity.description ?? ''} ${activity.farm} ${activity.officerName}`
                .toLocaleLowerCase('id-ID')
                .includes(query);
        return matchesOfficer && matchesSearch;
    });
});
function resetFilters(): void {
    activitySearch.value = '';
    selectedOfficerId.value = null;
}
function activityTone(status: string): string {
    const normalized = status.trim().toLocaleLowerCase('id-ID');
    return ['selesai', 'done', 'completed'].includes(normalized)
        ? 'bg-success/15 text-success'
        : ['proses', 'in_progress', 'in progress', 'progress'].includes(
                normalized,
            )
          ? 'bg-primary/15 text-primary'
          : 'bg-muted text-muted-foreground';
}
function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}
</script>

<template>
    <section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_300px]">
        <article
            class="min-w-0 overflow-hidden rounded-xl border border-border bg-card transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none"
            aria-labelledby="activities-heading"
        >
            <div
                class="flex flex-col justify-between gap-4 p-5 lg:flex-row lg:items-end"
            >
                <div>
                    <h2
                        id="activities-heading"
                        class="admin-section-title text-foreground"
                    >
                        Aktivitas lapangan
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aktivitas terbaru yang dicatat oleh petugas pada kebun
                        dalam cakupan Anda.
                    </p>
                </div>
            </div>
            <div
                class="grid gap-3 border-t border-border bg-muted/30 px-5 py-4 sm:grid-cols-2 2xl:grid-cols-1"
            >
                <label class="space-y-2 2xl:hidden">
                    <span class="block text-xs font-medium text-foreground"
                        >Petugas</span
                    >
                    <select
                        v-model="selectedOfficerId"
                        class="admin-field"
                        aria-controls="dashboard-activity-results"
                    >
                        <option :value="null">Semua petugas</option>
                        <option
                            v-for="officer in officers"
                            :key="officer.id"
                            :value="officer.id"
                        >
                            {{ officer.name }}
                        </option>
                    </select>
                </label>
                <label class="block space-y-2">
                    <span class="block text-xs font-medium text-foreground"
                        >Cari kegiatan</span
                    >
                    <span class="relative block">
                        <Search
                            :size="17"
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <input
                            v-model="activitySearch"
                            type="search"
                            class="admin-field pl-10!"
                            placeholder="Cari aktivitas atau kebun"
                            aria-controls="dashboard-activity-results"
                        />
                    </span>
                </label>
            </div>
            <div
                v-if="hasFilters"
                class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-5 py-3"
            >
                <p class="min-w-0 text-xs break-words text-muted-foreground">
                    Filter aktif<span v-if="selectedOfficer">
                        · {{ selectedOfficer.name }}</span
                    ><span v-if="activitySearch.trim()">
                        · “{{ activitySearch.trim() }}”</span
                    >
                </p>
                <Button
                    v-if="visibleActivities.length"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="resetFilters"
                    >Reset filter</Button
                >
            </div>
            <div id="dashboard-activity-results">
                <div
                    v-if="visibleActivities.length"
                    class="hidden overflow-x-auto focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring md:block"
                    role="region"
                    aria-label="Tabel aktivitas lapangan"
                    tabindex="0"
                >
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <caption class="sr-only">
                            Aktivitas terbaru sesuai pencarian dan petugas yang
                            dipilih.
                        </caption>
                        <thead
                            class="border-y border-border bg-muted/60 text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-5 py-3">Kegiatan</th>
                                <th scope="col" class="px-5 py-3">Kebun</th>
                                <th scope="col" class="px-5 py-3">Petugas</th>
                                <th scope="col" class="px-5 py-3">Tanggal</th>
                                <th scope="col" class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="activityItem in visibleActivities"
                                :key="activityItem.id"
                                class="transition-colors hover:bg-muted/50 motion-reduce:transition-none"
                            >
                                <td
                                    class="px-5 py-4 font-medium text-foreground"
                                >
                                    <span class="inline-flex items-start gap-2"
                                        ><ClipboardList
                                            :size="18"
                                            class="mt-0.5 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />{{ activityItem.title }}</span
                                    >
                                    <p
                                        v-if="activityItem.description"
                                        class="mt-1 max-w-sm text-xs leading-5 font-normal break-words text-muted-foreground"
                                    >
                                        {{ activityItem.description }}
                                    </p>
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.farm }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.officerName }}
                                </td>
                                <td
                                    class="px-5 py-4 text-sm text-muted-foreground"
                                >
                                    {{ activityItem.date }}
                                </td>
                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs leading-5 font-semibold"
                                        :class="
                                            activityTone(activityItem.status)
                                        "
                                        >{{ activityItem.status }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul
                    v-if="visibleActivities.length"
                    class="divide-y divide-border md:hidden"
                    aria-label="Daftar aktivitas lapangan"
                >
                    <li
                        v-for="activityItem in visibleActivities"
                        :key="activityItem.id"
                        class="space-y-3 p-5"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <h3
                                class="min-w-0 flex-1 font-semibold break-words text-foreground"
                            >
                                {{ activityItem.title }}
                            </h3>
                            <span
                                class="inline-flex max-w-full rounded-full px-2.5 py-1 text-xs leading-5 font-semibold"
                                :class="activityTone(activityItem.status)"
                                >{{ activityItem.status }}</span
                            >
                        </div>
                        <p
                            v-if="activityItem.description"
                            class="text-sm leading-6 break-words text-muted-foreground"
                        >
                            {{ activityItem.description }}
                        </p>
                        <dl
                            class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-xs leading-5"
                        >
                            <dt class="text-muted-foreground">Kebun</dt>
                            <dd class="break-words text-foreground">
                                {{ activityItem.farm }}
                            </dd>
                            <dt class="text-muted-foreground">Petugas</dt>
                            <dd class="break-words text-foreground">
                                {{ activityItem.officerName }}
                            </dd>
                            <dt class="text-muted-foreground">Tanggal</dt>
                            <dd class="text-foreground">
                                {{ activityItem.date }}
                            </dd>
                        </dl>
                    </li>
                </ul>
                <div
                    v-if="visibleActivities.length === 0"
                    class="flex flex-col items-center gap-2 p-10 text-center"
                >
                    <ClipboardList
                        :size="28"
                        class="text-muted-foreground"
                        aria-hidden="true"
                    />
                    <p class="font-medium text-foreground">
                        {{
                            hasFilters
                                ? 'Tidak ada aktivitas yang cocok.'
                                : 'Belum ada aktivitas lapangan.'
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            hasFilters
                                ? 'Pilih petugas lain atau ubah kata kunci pencarian.'
                                : 'Aktivitas yang dicatat pada kebun dalam cakupan Anda akan muncul di sini.'
                        }}
                    </p>
                    <Button
                        v-if="hasFilters"
                        type="button"
                        variant="outline"
                        class="mt-2"
                        @click="resetFilters"
                        >Reset filter</Button
                    >
                </div>
            </div>
            <footer
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-3 text-xs text-muted-foreground"
            >
                <span role="status" aria-live="polite" aria-atomic="true"
                    >Menampilkan {{ visibleActivities.length }} dari
                    {{ activities.length }} aktivitas terbaru.</span
                ><Button
                    v-if="$page.props.navigation?.farms"
                    as-child
                    variant="link"
                    size="sm"
                    ><Link href="/admin/farm-management"
                        >Kelola kebun</Link
                    ></Button
                >
            </footer>
        </article>

        <aside
            class="hidden min-w-0 self-start rounded-xl border border-border bg-card p-5 transition-shadow transition-transform hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none motion-reduce:transition-none 2xl:block"
            aria-labelledby="officers-heading"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2
                        id="officers-heading"
                        class="admin-section-title text-foreground"
                    >
                        Petugas
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Pilih petugas untuk melihat kegiatan yang telah dicatat.
                    </p>
                </div>
                <span
                    class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary"
                    ><Users :size="20" aria-hidden="true"
                /></span>
            </div>
            <div class="mt-5 space-y-2">
                <Button
                    type="button"
                    :variant="selectedOfficerId === null ? 'default' : 'ghost'"
                    class="h-auto w-full justify-start rounded-xl px-3 py-2.5 text-left whitespace-normal"
                    :aria-pressed="selectedOfficerId === null"
                    @click="selectedOfficerId = null"
                    ><span
                        class="grid size-9 place-items-center rounded-full bg-background/20 text-xs font-bold"
                        ><Users :size="18" aria-hidden="true" /></span
                    ><span class="min-w-0 flex-1"
                        ><span class="block font-semibold">Semua petugas</span
                        ><span class="block text-xs opacity-80"
                            >{{ activities.length }} aktivitas terbaru</span
                        ></span
                    ></Button
                >
                <Button
                    v-for="(officer, index) in officers"
                    :key="officer.id"
                    type="button"
                    :variant="
                        selectedOfficerId === officer.id ? 'default' : 'ghost'
                    "
                    class="h-auto w-full justify-start rounded-xl px-3 py-2.5 text-left whitespace-normal"
                    :aria-pressed="selectedOfficerId === officer.id"
                    @click="selectedOfficerId = officer.id"
                    ><span
                        class="grid size-6 shrink-0 place-items-center text-xs font-semibold opacity-80"
                        >{{ index + 1 }}</span
                    ><Avatar class="size-9 shrink-0 rounded-full"
                        ><AvatarImage
                            v-if="officer.avatarUrl"
                            :src="officer.avatarUrl"
                            :alt="officer.name"
                        /><AvatarFallback
                            class="rounded-full bg-muted text-xs font-semibold text-muted-foreground"
                            >{{ initials(officer.name) }}</AvatarFallback
                        ></Avatar
                    ><span class="min-w-0 flex-1"
                        ><span class="block font-semibold break-words">{{
                            officer.name
                        }}</span
                        ><span class="block text-xs opacity-80"
                            >{{ officer.activityCount }} aktivitas<span
                                v-if="officer.latestActivityAt"
                            >
                                · terakhir
                                {{ officer.latestActivityAt }}</span
                            ></span
                        ></span
                    ></Button
                >
                <div
                    v-if="officers.length === 0"
                    class="rounded-xl border border-dashed border-border p-5 text-center text-sm text-muted-foreground"
                >
                    Belum ada petugas aktif yang dapat ditampilkan untuk cakupan
                    kebun ini.
                </div>
            </div>
        </aside>
    </section>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, UserRound } from '@lucide/vue';
import { ref } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import {
    ownerReadDate,
    ownerReadStatus,
    type OwnerActivityDetail,
} from '@/types/owner-secondary';

defineProps<{ activity: OwnerActivityDetail }>();
const failedPhotos = ref(new Set<string>());
function photoFailed(id: string): void {
    failedPhotos.value = new Set([...failedPhotos.value, id]);
}
</script>

<template>
    <Head :title="activity.type?.name ?? 'Rincian aktivitas'" />
    <div class="space-y-5">
        <OwnerPageHeader
            title="Rincian aktivitas"
            :description="activity.type?.name ?? 'Catatan pekerjaan lapangan'"
            back-href="/aktivitas"
        />

        <section class="rounded-xl border border-border bg-card p-4">
            <div class="mb-3 flex flex-wrap gap-2">
                <span
                    class="rounded-lg bg-primary/10 px-2 py-1 text-xs font-medium text-secondary dark:text-primary"
                    >{{ activity.type?.name ?? 'Aktivitas lapangan' }}</span
                >
                <span
                    class="rounded-lg bg-muted px-2 py-1 text-xs text-muted-foreground"
                    >{{ ownerReadStatus(activity.status) }}</span
                >
            </div>
            <h2 class="text-base leading-7 font-semibold">
                {{ activity.description || 'Catatan aktivitas' }}
            </h2>
            <div class="mt-4 space-y-3 text-sm leading-6 text-muted-foreground">
                <p class="flex items-start gap-2">
                    <MapPin
                        class="mt-1 size-4 shrink-0"
                        aria-hidden="true"
                    /><Link
                        v-if="activity.farm"
                        :href="`/kebun/${activity.farm.id}`"
                        class="rounded text-secondary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-ring dark:text-primary"
                        >{{ activity.farm.name }}</Link
                    ><span v-else>Kebun tidak tersedia</span>
                </p>
                <p class="flex items-start gap-2">
                    <UserRound
                        class="mt-1 size-4 shrink-0"
                        aria-hidden="true"
                    />{{ activity.performer?.name ?? 'Petugas tidak tersedia' }}
                </p>
                <p class="flex items-start gap-2">
                    <CalendarDays
                        class="mt-1 size-4 shrink-0"
                        aria-hidden="true"
                    />{{ ownerReadDate(activity.activity_date, true) }} WIB
                </p>
            </div>
            <p
                v-if="activity.cycle || activity.block_name"
                class="mt-3 rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground"
            >
                {{ [activity.block_name, activity.cycle?.label].filter(Boolean).join(' · ') }}
            </p>
        </section>

        <p v-if="activity.worker_count !== null" class="text-sm text-muted-foreground">Jumlah pekerja aktivitas: {{ activity.worker_count }} orang</p>
        <section
            v-if="activity.details.length"
            class="rounded-xl border border-border bg-card p-4"
        >
            <h2 class="mb-4 text-base font-semibold">Detail pekerjaan</h2>
            <dl class="space-y-3">
                <div
                    v-for="(detail, index) in activity.details"
                    :key="`${detail.label}-${index}`"
                    class="grid grid-cols-1 gap-1 border-b border-border pb-3 last:border-0 last:pb-0"
                >
                    <dt class="text-xs text-muted-foreground">
                        {{ detail.label }}
                    </dt>
                    <dd
                        class="text-sm leading-6 break-words whitespace-pre-line"
                    >
                        {{ detail.value ?? 'Belum tercatat' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="rounded-xl border border-border bg-card p-4">
            <h2 class="mb-3 text-base font-semibold">Kendala dan penanganan</h2>
            <p class="mb-4 text-xs text-muted-foreground">
                {{ ownerReadStatus(activity.obstacle_status) }}
            </p>
            <dl class="space-y-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Kendala</dt>
                    <dd class="mt-1 text-sm leading-6 whitespace-pre-line">
                        {{
                            activity.obstacles ||
                            'Tidak ada kendala yang dicatat.'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Usulan solusi</dt>
                    <dd class="mt-1 text-sm leading-6 whitespace-pre-line">
                        {{ activity.proposed_solution || 'Belum tercatat' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Tindakan yang dilakukan
                    </dt>
                    <dd class="mt-1 text-sm leading-6 whitespace-pre-line">
                        {{ activity.action_taken || 'Belum tercatat' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section
            v-if="activity.log"
            class="rounded-xl border border-border bg-card p-4"
        >
            <h2 class="mb-4 text-base font-semibold">Logbook harian</h2>
            <dl class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <dt class="text-xs text-muted-foreground">Tanggal kerja</dt>
                    <dd class="mt-1 text-sm">
                        {{ ownerReadDate(activity.log.work_date) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Jam kerja</dt>
                    <dd class="mt-1 text-sm">
                        {{ activity.log.start_time.slice(0, 5) }}–{{
                            activity.log.end_time?.slice(0, 5) ?? 'Belum diisi'
                        }}
                        WIB
                    </dd>
                </div>
                <div v-if="activity.log.notes" class="col-span-2">
                    <dt class="text-xs text-muted-foreground">
                        Catatan harian
                    </dt>
                    <dd class="mt-1 text-sm leading-6 whitespace-pre-line">
                        {{ activity.log.notes }}
                    </dd>
                </div>
            </dl>
        </section>

        <section class="space-y-3">
            <h2 class="text-base font-semibold">Dokumentasi</h2>
            <template v-if="activity.photos.length">
                <figure
                    v-for="photo in activity.photos"
                    :key="photo.id"
                    class="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <a
                        v-if="!failedPhotos.has(photo.id)"
                        :href="photo.image_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block focus-visible:outline-2 focus-visible:outline-ring"
                        :aria-label="`Buka foto ${photo.label || 'aktivitas'} pada tab baru`"
                    >
                        <img
                            :src="photo.image_url"
                            :alt="
                                photo.caption ||
                                photo.label ||
                                'Dokumentasi aktivitas kebun'
                            "
                            loading="lazy"
                            decoding="async"
                            class="aspect-[4/3] w-full object-cover"
                            @error="photoFailed(photo.id)"
                        />
                    </a>
                    <p
                        v-else
                        class="bg-muted px-4 py-9 text-center text-sm text-muted-foreground"
                    >
                        Foto belum dapat ditampilkan.
                    </p>
                    <figcaption
                        v-if="photo.label || photo.caption"
                        class="space-y-1 p-4"
                    >
                        <p v-if="photo.label" class="text-sm font-medium">
                            {{ photo.label }}
                        </p>
                        <p
                            v-if="photo.caption"
                            class="text-xs leading-5 text-muted-foreground"
                        >
                            {{ photo.caption }}
                        </p>
                    </figcaption>
                </figure>
            </template>
            <p
                v-else
                class="rounded-xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
            >
                Belum ada foto pada aktivitas ini.
            </p>
        </section>
    </div>
</template>

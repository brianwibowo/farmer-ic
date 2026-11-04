<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, ArrowRight, BookOpen, UsersRound } from '@lucide/vue';
import { computed } from 'vue';
import OwnerPageHeader from '@/components/owner/OwnerPageHeader.vue';
import OwnerReadEmpty from '@/components/owner/OwnerReadEmpty.vue';

const props = defineProps<{
    can: { activities: boolean; sops: boolean; users: boolean };
}>();
const entries = computed(() =>
    [
        {
            allowed: props.can.activities,
            title: 'Aktivitas',
            description:
                'Catatan pekerjaan, petugas, dan dokumentasi lapangan.',
            href: '/aktivitas',
            icon: Activity,
        },
        {
            allowed: props.can.sops,
            title: 'SOP',
            description: 'Panduan kerja, versi, dan jadwal kegiatan kebun.',
            href: '/lainnya/sop',
            icon: BookOpen,
        },
        {
            allowed: props.can.users,
            title: 'Data pengguna',
            description:
                'Anggota tim dan penugasan kebun yang dapat Anda lihat.',
            href: '/lainnya/pengguna',
            icon: UsersRound,
        },
    ].filter((entry) => entry.allowed),
);
</script>

<template>
    <Head title="Lainnya" />
    <div class="space-y-5">
        <OwnerPageHeader
            title="Lainnya"
            description="Informasi tim dan kegiatan untuk melengkapi pemantauan kebun Anda."
        />
        <div v-if="entries.length" class="space-y-3">
            <Link
                v-for="entry in entries"
                :key="entry.href"
                :href="entry.href"
                class="flex items-start gap-3 rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                    ><component
                        :is="entry.icon"
                        class="size-5"
                        aria-hidden="true"
                /></span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base leading-6 font-semibold">
                        {{ entry.title }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-muted-foreground">
                        {{ entry.description }}
                    </p>
                </div>
                <ArrowRight
                    class="mt-3 size-4 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </Link>
        </div>
        <OwnerReadEmpty
            v-else
            title="Belum ada menu yang tersedia"
            description="Pilih tenant yang Anda ikuti untuk melihat informasi kegiatan dan tim."
        />
    </div>
</template>

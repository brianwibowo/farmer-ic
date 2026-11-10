<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardPlus, ListChecks } from '@lucide/vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <Head title="Aktivitas Petugas" />
    <header>
        <p class="text-xs font-medium tracking-wide text-muted-foreground">
            Ruang Petugas
        </p>
        <h1 class="mt-2 text-2xl leading-8 font-semibold text-foreground">
            Aktivitas
        </h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Catat pekerjaan lapangan satu aktivitas dalam satu laporan.
        </p>
    </header>

    <section class="petugas-card mt-[var(--admin-content-gap)] p-4">
        <div class="flex items-start gap-3">
            <span
                class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
            >
                <ClipboardPlus :size="22" aria-hidden="true" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-foreground">
                    Catat aktivitas lapangan
                </h2>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    Pilih kategori pekerjaan, isi bukti foto, dan lengkapi
                    detail yang relevan di lapangan.
                </p>
            </div>
        </div>
        <Link
            href="/petugas/aktivitas/buat"
            class="mt-5 inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground transition-colors hover:bg-secondary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <ClipboardPlus :size="18" aria-hidden="true" />
            Buat aktivitas
        </Link>
    </section>

    <section
        class="petugas-card petugas-empty mt-[var(--admin-content-gap)] p-4"
    >
        <div
            class="flex items-center gap-2 text-sm font-semibold text-foreground"
        >
            <ListChecks :size="18" class="text-primary" aria-hidden="true" />
            Riwayat aktivitas
        </div>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Riwayat akan ditampilkan setelah form ini dihubungkan ke logbook dan
            database.
        </p>
    </section>
</template>

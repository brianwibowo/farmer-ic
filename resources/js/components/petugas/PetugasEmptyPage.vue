<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ClipboardList, FileText, House, Sprout } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    description: string;
    section: 'home' | 'garden' | 'activity' | 'report';
}>();
const icon = computed(
    () =>
        ({
            home: House,
            garden: Sprout,
            activity: ClipboardList,
            report: FileText,
        })[props.section],
);
</script>

<template>
    <Head :title="`${title} · Petugas`" />
    <header>
        <p class="text-xs font-medium tracking-wide text-muted-foreground">
            Ruang Petugas
        </p>
        <h1 class="mt-2 text-2xl leading-8 font-semibold">{{ title }}</h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
            {{ description }}
        </p>
    </header>
    <section
        :aria-label="`Konten ${title}`"
        class="mt-6 flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed border-border bg-background px-5 py-12 text-center"
    >
        <div
            class="mb-4 flex size-14 items-center justify-center rounded-xl bg-muted text-secondary dark:text-primary"
        >
            <component :is="icon" class="size-7" aria-hidden="true" />
        </div>
        <h2 class="text-base font-semibold">
            Halaman {{ title.toLowerCase() }}
        </h2>
        <p class="mt-2 max-w-64 text-sm leading-6 text-muted-foreground">
            Konten halaman ini akan ditambahkan pada tahap berikutnya.
        </p>
    </section>
</template>

<script setup lang="ts">
import { Download, FileText } from '@lucide/vue';
import {
    formatLegalityFileSize,
    type FarmLegalityDocument,
} from '@/types/farm-legality';

defineProps<{ documents: FarmLegalityDocument[] }>();
</script>

<template>
    <ul v-if="documents.length" class="divide-y divide-border">
        <li v-for="document in documents" :key="document.id">
            <a
                :href="document.download_url"
                :aria-label="`Unduh draft ${document.original_name}`"
                class="flex min-h-14 items-center gap-3 rounded-md py-3 focus-visible:outline-2 focus-visible:outline-ring"
            >
                <FileText
                    class="size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="block text-sm font-medium [overflow-wrap:anywhere] text-foreground underline decoration-primary/30 underline-offset-4"
                        >{{ document.original_name }}</span
                    >
                    <span class="mt-1 block text-xs text-muted-foreground"
                        >PDF ·
                        {{ formatLegalityFileSize(document.size_bytes) }}</span
                    >
                </span>
                <Download
                    class="size-4 shrink-0 text-primary"
                    aria-hidden="true"
                />
            </a>
        </li>
    </ul>
    <p v-else class="text-sm leading-6 text-muted-foreground">
        Belum ada draft legalitas yang diunggah.
    </p>
</template>

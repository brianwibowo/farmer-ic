<script setup lang="ts">
import { CircleCheck, TriangleAlert } from '@lucide/vue';
import type { DashboardData } from '@/types/dashboard';

defineProps<{ risks: DashboardData['risks'] }>();
</script>

<template>
    <article
        class="dashboard-panel flex min-h-64 min-w-0 flex-col"
        aria-labelledby="dashboard-risks-heading"
    >
        <header class="flex items-start justify-between gap-3">
            <div>
                <h2 id="dashboard-risks-heading" class="admin-section-title">
                    Risiko belum ditutup
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ risks.total }} risiko perlu ditinjau
                </p>
            </div>
            <TriangleAlert
                v-if="risks.total > 0"
                :size="21"
                class="shrink-0 text-error"
                role="img"
                :aria-label="`${risks.total} risiko belum ditutup`"
            />
        </header>

        <div
            v-if="risks.items.length"
            class="mt-4 max-h-72 divide-y divide-border/60 overflow-y-auto overscroll-contain pr-1"
            role="list"
            aria-label="Daftar risiko belum ditutup"
        >
            <div
                v-for="(risk, index) in risks.items"
                :key="risk.id"
                class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                role="listitem"
            >
                <span
                    class="grid size-7 shrink-0 place-items-center rounded-full bg-muted text-xs font-semibold text-muted-foreground tabular-nums"
                    aria-hidden="true"
                >
                    {{ index + 1 }}
                </span>
                <div class="min-w-0">
                    <p class="text-sm leading-5 font-medium text-foreground">
                        {{ risk.title }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ risk.detectedAt }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-else
            class="flex flex-1 flex-col items-center justify-center px-2 py-8 text-center"
            role="status"
        >
            <CircleCheck :size="30" class="text-success" aria-hidden="true" />
            <p class="mt-3 text-sm font-semibold text-foreground">
                Tidak ada risiko terbuka saat ini
            </p>
            <p class="mt-1 max-w-56 text-xs leading-5 text-muted-foreground">
                Risiko kebun yang perlu ditinjau akan muncul di sini.
            </p>
        </div>
    </article>
</template>

<script setup lang="ts">
import { LandPlot, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import { landTenureLabel } from '@/types/farms';
import type { PetugasGarden } from '@/types/petugas-garden';
import { gardenArea } from '@/types/petugas-garden';

const props = defineProps<{
    farm: NonNullable<PetugasGarden['farm']>;
    summary: PetugasGarden['summary'];
}>();
const location = computed(() =>
    [props.farm.district, props.farm.city, props.farm.province]
        .filter(Boolean)
        .join(', '),
);
</script>

<template>
    <section
        aria-labelledby="garden-farm-name"
        class="space-y-4 rounded-xl border border-border bg-card p-4"
    >
        <div class="flex items-start gap-3">
            <span
                class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-secondary dark:text-primary"
                ><LandPlot class="size-6" aria-hidden="true"
            /></span>
            <div class="min-w-0">
                <h2
                    id="garden-farm-name"
                    class="text-lg leading-6 font-semibold break-words"
                >
                    {{ farm.name }}
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ landTenureLabel(farm.land_tenure) }}
                </p>
            </div>
        </div>
        <div v-if="location || farm.address" class="flex items-start gap-2">
            <MapPin
                class="mt-0.5 size-4 shrink-0 text-secondary dark:text-primary"
                aria-hidden="true"
            />
            <div class="min-w-0 space-y-1">
                <p v-if="farm.address" class="text-sm leading-5 break-words">
                    {{ farm.address }}
                </p>
                <p
                    v-if="location"
                    class="text-xs leading-5 text-muted-foreground"
                >
                    {{ location }}
                </p>
            </div>
        </div>
        <dl class="grid grid-cols-3 gap-3 border-t border-border pt-4">
            <div>
                <dt class="text-xs text-muted-foreground">Luas (ha)</dt>
                <dd class="mt-1 text-2xl leading-8 font-bold tabular-nums">
                    {{ gardenArea(farm.total_area_ha) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Blok</dt>
                <dd class="mt-1 text-2xl leading-8 font-bold tabular-nums">
                    {{ summary.blocks }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Subblok</dt>
                <dd class="mt-1 text-2xl leading-8 font-bold tabular-nums">
                    {{ summary.batches }}
                </dd>
            </div>
        </dl>
    </section>
</template>

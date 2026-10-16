<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, MapPin, Sprout, TrendingUp, X } from '@lucide/vue';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import KebunSatelliteMap from './KebunSatelliteMap.vue';
import type { MappedKebun } from '@/services/kebunService';

defineProps<{ kebun: MappedKebun }>();
const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ closed: [] }>();
const rupiah = (value: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent :show-close-button="false" class="max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto overscroll-contain rounded-3xl bg-card p-0 text-card-foreground sm:max-w-[420px]" @close-auto-focus="(event) => { event.preventDefault(); emit('closed'); }">
            <header class="relative px-5 pt-5 pb-4 pr-14">
                <DialogTitle class="text-lg font-semibold">{{ kebun.name }}</DialogTitle>
                <DialogDescription class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"><MapPin :size="14" aria-hidden="true" />{{ kebun.city }} · {{ kebun.seedType }}</DialogDescription>
                <DialogClose class="absolute top-4 right-4 flex size-9 items-center justify-center rounded-full bg-muted text-card-foreground focus-visible:outline-2 focus-visible:outline-ring" aria-label="Tutup ringkasan kebun"><X :size="18" aria-hidden="true" /></DialogClose>
            </header>

            <KebunSatelliteMap :key="kebun.id" :position="kebun.position" :name="kebun.name" />

            <div class="space-y-4 p-5">
                <section aria-label="Kondisi tanaman">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="flex items-center gap-2 text-sm font-semibold"><Sprout :size="18" class="text-primary" aria-hidden="true" />Kondisi tanaman</h3>
                        <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', kebun.healthStatus === 'Kritis' ? 'bg-error/10 text-error' : kebun.healthStatus === 'Perlu Perhatian' ? 'bg-warning/15 text-card-foreground' : 'bg-success/10 text-success']">{{ kebun.healthStatus }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ kebun.plantCondition }}</p>
                </section>
                <section class="rounded-2xl bg-muted p-4" aria-label="Proyeksi pendapatan kebun">
                    <h3 class="flex items-center gap-2 text-xs font-medium text-muted-foreground"><TrendingUp :size="17" class="text-success" aria-hidden="true" />Proyeksi pendapatan kebun</h3>
                    <p class="mt-2 text-2xl font-semibold text-success">{{ rupiah(kebun.projectedRevenue) }}</p>
                    <p class="mt-1 text-[11px] text-muted-foreground">Estimasi demo · Panen {{ kebun.harvestAt }}</p>
                </section>
                <Link :href="`/kebun/detail?id=${encodeURIComponent(kebun.id)}`" class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground hover:brightness-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" @click="open = false">Detail kebun<ArrowRight :size="18" aria-hidden="true" /></Link>
            </div>
        </DialogContent>
    </Dialog>
</template>

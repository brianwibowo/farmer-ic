<script setup lang="ts">
import { LoaderCircle, Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { FarmLocationCandidate } from '@/services/farmGeocodingService';

defineProps<{
    loading: boolean;
    message: string;
    candidates: FarmLocationCandidate[];
    selected: FarmLocationCandidate | null;
    ready: boolean;
    disabled: boolean;
}>();
const emit = defineEmits<{
    retry: [];
    choose: [candidate: FarmLocationCandidate];
}>();
</script>

<template>
    <div class="space-y-3" :aria-busy="loading">
        <div
            v-if="selected?.approximate"
            role="status"
            class="space-y-1 rounded-xl border border-border bg-muted p-3 text-sm leading-6"
        >
            <p class="font-medium text-warning">Perkiraan lokasi</p>
            <p class="text-foreground">{{ selected.label }}</p>
            <p class="text-muted-foreground">
                {{
                    selected.search_stage === 'region'
                        ? 'Alamat lengkap belum ditemukan. Pin menunjukkan titik pusat wilayah yang ditemukan.'
                        : 'Pin menunjukkan perkiraan titik tempat yang ditemukan.'
                }}
                <template
                    v-if="!selected.matches_region && selected.can_auto_select"
                    >Kecamatan belum terverifikasi.</template
                >
                Geser pin ke lokasi lahan yang tepat.
            </p>
        </div>
        <ul
            v-if="candidates.length"
            aria-label="Hasil pencarian lokasi"
            class="divide-y divide-border rounded-xl border border-border"
        >
            <li
                v-for="candidate in candidates"
                :key="`${candidate.latitude},${candidate.longitude}`"
            >
                <button
                    type="button"
                    class="min-h-11 w-full rounded-xl px-4 py-3 text-left text-sm leading-6 text-foreground hover:bg-muted focus-visible:outline-2 focus-visible:outline-ring disabled:opacity-50"
                    :disabled="disabled"
                    @click="emit('choose', candidate)"
                >
                    <span class="block">{{ candidate.label }}</span>
                    <span class="block text-xs text-muted-foreground"
                        >{{ candidate.approximate ? 'Perkiraan lokasi · ' : ''
                        }}{{
                            candidate.matches_region
                                ? 'Sesuai wilayah terpilih'
                                : candidate.can_auto_select
                                  ? 'Kecamatan belum terverifikasi'
                                  : 'Periksa kembali kesesuaian wilayah'
                        }}</span
                    >
                </button>
            </li>
        </ul>
        <Button
            v-if="ready"
            type="button"
            variant="outline"
            size="sm"
            :disabled="disabled || loading"
            :aria-label="loading ? 'Mencari lokasi dari alamat…' : undefined"
            @click="emit('retry')"
            ><LoaderCircle
                v-if="loading"
                class="animate-spin"
                aria-hidden="true"
            /><Search v-else aria-hidden="true" />Cari ulang dari alamat</Button
        >
    </div>
</template>

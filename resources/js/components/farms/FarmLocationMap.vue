<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { ExternalLink, LoaderCircle, MapPin, RotateCcw, X } from '@lucide/vue';
import type { Map as LeafletMap, Marker, TileLayer } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../../../css/farm-map.css';
import { Button } from '@/components/ui/button';

const props = withDefaults(defineProps<{
    modelValue: [number, number] | null;
    editable?: boolean;
    disabled?: boolean;
    error?: string;
}>(), { editable: false, disabled: false, error: '' });
const emit = defineEmits<{ 'update:modelValue': [value: [number, number] | null] }>();
const mapElement = ref<HTMLDivElement>();
const loading = ref(true);
const ready = ref(false);
const mapError = ref('');
const hintId = useId();
const mapsUrl = computed(() => props.modelValue
    ? `https://www.google.com/maps/search/?api=1&query=${props.modelValue[0]},${props.modelValue[1]}`
    : null);
let leaflet: typeof import('leaflet') | undefined;
let map: LeafletMap | undefined;
let marker: Marker | undefined;
let tiles: TileLayer | undefined;
let observer: ResizeObserver | undefined;
let timeout: ReturnType<typeof setTimeout> | undefined;
let disposed = false;

function stopLoading(): void {
    loading.value = false;
    clearTimeout(timeout);
}

function startLoading(): void {
    loading.value = true;
    mapError.value = '';
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        mapError.value = 'Peta belum dapat dimuat. Periksa koneksi internet lalu coba lagi.';
        stopLoading();
    }, 12000);
}

function selectPoint(latitude: number, longitude: number): void {
    if (!props.editable || props.disabled) return;
    emit('update:modelValue', [Number(latitude.toFixed(7)), Number(longitude.toFixed(7))]);
}

function useCenter(): void {
    const center = map?.getCenter().wrap();
    if (center) selectPoint(center.lat, center.lng);
}

function syncMarker(): void {
    if (!leaflet || !map) return;
    if (!props.modelValue) {
        marker?.remove();
        marker = undefined;
        return;
    }
    if (!marker) {
        marker = leaflet.marker(props.modelValue, {
            title: 'Pin lokasi kebun',
            alt: 'Pin lokasi kebun',
            keyboard: true,
            draggable: props.editable && !props.disabled,
            icon: leaflet.divIcon({
                className: 'farm-location-pin',
                html: '<svg viewBox="0 0 32 40" width="32" height="40" aria-hidden="true"><path d="M16 38S2 24 2 16a14 14 0 0 1 28 0c0 8-14 22-14 22Z" fill="currentColor" stroke="var(--card)" stroke-width="2"/><circle cx="16" cy="16" r="5" fill="var(--card)"/></svg>',
                iconSize: [32, 40],
                iconAnchor: [16, 38],
            }),
        }).addTo(map);
        marker.on('dragend', () => {
            const point = marker?.getLatLng().wrap();
            if (point) selectPoint(point.lat, point.lng);
        });
    } else {
        marker.setLatLng(props.modelValue);
    }
    if (props.editable && !props.disabled) marker.dragging?.enable();
    else marker.dragging?.disable();
    if (!map.getBounds().contains(props.modelValue)) map.panTo(props.modelValue);
}

async function initialize(): Promise<void> {
    startLoading();
    try {
        leaflet = await import('leaflet');
        if (disposed) return;
        if (!mapElement.value) {
            stopLoading();
            return;
        }
        map = leaflet.map(mapElement.value, {
            scrollWheelZoom: false,
            worldCopyJump: true,
            maxZoom: 19,
        }).setView(props.modelValue ?? [-2.5, 118], props.modelValue ? 15 : 5);
        map.zoomControl.setPosition('bottomright');
        mapElement.value.setAttribute('aria-label', props.editable ? 'Pilih titik lokasi kebun di peta' : 'Peta lokasi kebun');
        mapElement.value.setAttribute('aria-describedby', hintId);
        map.on('click', (event) => {
            const point = event.latlng.wrap();
            selectPoint(point.lat, point.lng);
        });
        tiles = leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
        });
        tiles.on('load', stopLoading);
        tiles.on('tileerror', () => {
            mapError.value = 'Sebagian peta gagal dimuat. Periksa koneksi internet lalu coba lagi.';
            stopLoading();
        });
        tiles.addTo(map);
        syncMarker();
        ready.value = true;
        observer = new ResizeObserver(() => map?.invalidateSize({ pan: false }));
        observer.observe(mapElement.value);
    } catch {
        mapError.value = 'Peta belum dapat dibuka. Silakan coba lagi.';
        stopLoading();
    }
}

function retry(): void {
    if (tiles) {
        startLoading();
        tiles.redraw();
    } else {
        map?.remove();
        map = undefined;
        marker = undefined;
        ready.value = false;
        void initialize();
    }
}

watch(() => [props.modelValue, props.disabled, props.editable], syncMarker, { deep: true });
onMounted(() => void initialize());
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timeout);
    observer?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="min-w-0 space-y-3">
        <p :id="hintId" class="text-xs leading-5 text-muted-foreground">
            {{ editable ? 'Klik peta untuk menaruh pin, lalu geser pin untuk memperbaiki posisi. Dengan keyboard, geser peta menggunakan tombol panah lalu pilih Gunakan titik tengah peta.' : 'Pin menunjukkan titik lokasi kebun, bukan batas atau luas lahan.' }}
        </p>
        <div v-if="editable || modelValue" class="farm-map relative isolate h-72 overflow-hidden rounded-xl border bg-muted sm:h-80" :class="error ? 'border-error' : 'border-border'">
            <div ref="mapElement" class="absolute inset-0" :aria-busy="loading" />
            <div v-if="loading" role="status" class="pointer-events-none absolute inset-0 z-[1000] flex items-center justify-center gap-2 bg-card/80 text-sm text-muted-foreground"><LoaderCircle class="size-4 animate-spin" />Memuat peta…</div>
        </div>
        <div v-else class="rounded-xl border border-dashed border-border bg-muted/40 p-5 text-sm text-muted-foreground">Lokasi kebun belum dipilih. Lengkapi pada halaman ubah kebun.</div>
        <div v-if="mapError" role="status" class="flex flex-wrap items-center gap-2 rounded-xl bg-muted p-3 text-xs text-muted-foreground"><p class="flex-1">{{ mapError }}</p><Button type="button" variant="outline" size="sm" :disabled="disabled || loading" @click="retry"><RotateCcw />Coba lagi</Button></div>
        <div v-if="editable" class="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" :disabled="disabled || !ready" @click="useCenter"><MapPin />Gunakan titik tengah peta</Button>
            <Button v-if="modelValue" type="button" variant="ghost" size="sm" :disabled="disabled" @click="emit('update:modelValue', null)"><X />Hapus pin</Button>
        </div>
        <p v-if="editable" role="status" aria-live="polite" class="text-xs text-muted-foreground">{{ modelValue ? 'Pin lokasi sudah dipilih. Perubahan tersimpan setelah Simpan kebun.' : 'Belum ada pin. Lokasi boleh dikosongkan.' }}</p>
        <p v-if="error" role="alert" class="text-sm text-error">{{ error }}</p>
        <a v-if="mapsUrl" :href="mapsUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded text-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-ring">Buka di Google Maps<ExternalLink class="size-4" /></a>
    </div>
</template>

<style scoped>
:deep(.farm-location-pin) {
    color: var(--primary);
    background: transparent;
}
</style>

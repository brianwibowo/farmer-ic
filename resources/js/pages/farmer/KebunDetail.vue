<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    CalendarDays,
    CircleAlert,
    CircleCheck,
    CircleX,
    CloudSun,
    Maximize2,
    LandPlot,
    MapPin,
    Scale,
    Sprout,
    TrendingDown,
    TrendingUp,
    Wheat,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import FarmerMobileShell from '@/components/farmer/FarmerMobileShell.vue';
import { getKebunDetail, type MappedKebun } from '@/services/kebunService';

const page = usePage();
const kebunId = computed(
    () =>
        new URL(page.url, 'http://localhost').searchParams.get('id') ??
        'kebun-001',
);
const selectedKebun = ref<MappedKebun>();
const loading = ref(true);
const error = ref('');
let requestId = 0;

const detail = computed(() => {
    const item = selectedKebun.value;
    if (!item) return undefined;

    const growthCategory = item.growth >= 80 ? 4 : item.growth >= 60 ? 3 : 2;
    const growthCategoryLabel =
        growthCategory === 4
            ? 'Pembungaan'
            : growthCategory === 3
              ? 'Vegetatif aktif'
              : 'Bibit awal';

    return {
        ...item,
        location: item.city,
        district: item.district,
        address: item.address,
        crop: item.seedType,
        seed: `${item.seedType} ${item.seedTypeDetail}`,
        totalArea: `${new Intl.NumberFormat('id-ID').format(item.availableHectare)} ha`,
        plantedArea: `${new Intl.NumberFormat('id-ID').format(item.usedHectare)} ha`,
        weight: `${new Intl.NumberFormat('id-ID').format(Math.round(item.usedHectare * 0.14))} ton`,
        growthCategory,
        growthCategoryLabel,
        aiConfidence: 92,
        harvestStatus: item.healthStatus,
        weather: {
            condition: 'Cerah berawan',
            temperature: '26°C',
            humidity: '78%',
        },
    };
});

function healthTone(status: string): string {
    if (status === 'Kritis') return 'border-error/25 bg-error/10 text-error';
    if (status === 'Perlu Perhatian') return 'border-warning/30 bg-warning/15 text-warning  ';
    return 'border-success/25 bg-success/10 text-success';
}

function healthIcon(status: string) {
    if (status === 'Kritis') return CircleX;
    if (status === 'Perlu Perhatian') return CircleAlert;
    return CircleCheck;
}

async function loadDetail(): Promise<void> {
    const request = ++requestId;
    loading.value = true;
    error.value = '';
    selectedKebun.value = undefined;

    try {
        const kebun = await getKebunDetail(kebunId.value);
        if (request !== requestId) return;
        selectedKebun.value = kebun;
        if (!kebun) error.value = 'Kebun tidak ditemukan.';
    } catch {
        if (request === requestId) {
            error.value = 'Detail kebun belum dapat dimuat. Silakan coba lagi.';
        }
    } finally {
        if (request === requestId) loading.value = false;
    }
}

const growthStages = [
    {
        number: 1,
        label: 'Kecambah',
        range: '0–9 HST',
        percent: 20,
        image: '/images/farmer/jagung-berkecambah.jpg',
    },
    {
        number: 2,
        label: 'Bibit awal',
        range: '10–29 HST',
        percent: 40,
        image: '/images/farmer/jagung-tumbuh-fase-2.jpg',
    },
    {
        number: 3,
        label: 'Vegetatif aktif',
        range: '30–59 HST',
        percent: 60,
        image: '/images/farmer/corn-field.jpeg',
    },
    {
        number: 4,
        label: 'Pembungaan',
        range: '60–79 HST',
        percent: 80,
        image: '/images/farmer/jagung-tunas.jpeg',
    },
    {
        number: 5,
        label: 'Pengisian & panen',
        range: '80–99 HST',
        percent: 100,
        image: '/images/farmer/jagung-panen.jpg',
    },
] as const;

type ImagePreview = {
    src: string;
    alt: string;
    title: string;
    caption: string;
};
const selectedStageNumber = ref(3);
const selectedImage = ref<ImagePreview | null>(null);
const selectedGrowthStage = computed(
    () =>
        growthStages.find(
            (stage) => stage.number === selectedStageNumber.value,
        ) ?? growthStages[0],
);

watch(
    detail,
    (value) => {
        selectedStageNumber.value = value?.growthCategory ?? 3;
    },
    { immediate: true },
);

function openImage(image: ImagePreview): void {
    selectedImage.value = image;
}
function closeImage(): void {
    selectedImage.value = null;
}
function selectGrowthStage(stageNumber: number): void {
    selectedStageNumber.value = stageNumber;
}
function openGrowthStageImage(): void {
    const stage = selectedGrowthStage.value;
    openImage({
        src: stage.image,
        alt: `Referensi tanaman ${detail.value?.crop ?? 'kebun'} fase ${stage.label}`,
        title: `Fase ${stage.number}: ${stage.label}`,
        caption: `${stage.range}. Referensi untuk mencocokkan fase pertumbuhan tanaman.`,
    });
}
function formatRupiah(value: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

onMounted(() => void loadDetail());
watch(kebunId, () => void loadDetail());
</script>

<template>
    <Head title="Detail Kebun" />
    <FarmerMobileShell active-key="garden">
        <div
            v-if="loading"
            class="space-y-4 p-5"
            role="status"
            aria-label="Memuat detail kebun"
        >
            <div class="h-12 w-2/3 animate-pulse rounded-xl bg-muted" />
            <div class="h-32 animate-pulse rounded-2xl bg-muted" />
            <div class="h-48 animate-pulse rounded-2xl bg-muted" />
        </div>
        <div
            v-else-if="error || !detail"
            class="space-y-4 px-5 py-12 text-center"
            role="status"
        >
            <p class="text-sm text-muted-foreground">
                {{ error || 'Kebun tidak ditemukan.' }}
            </p>
            <button
                type="button"
                class="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground"
                @click="void loadDetail()"
            >
                Coba lagi
            </button>
            <Link
                href="/kebun"
                class="block text-sm font-medium text-secondary underline"
                >Kembali ke daftar kebun</Link
            >
        </div>
        <div v-else class="space-y-6 px-5 pt-8 pb-6">
            <header class="flex items-center gap-3">
                <Link
                    href="/kebun"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-border text-secondary transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Kembali ke daftar kebun"
                >
                    <ArrowLeft
                        :size="20"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </Link>
                <div>
                    <p class="text-sm font-medium text-muted-foreground">
                        Detail Kebun
                    </p>
                    <h1
                        class="text-2xl leading-8 font-semibold text-card-foreground"
                    >
                        {{ detail.name }}
                    </h1>
                </div>
            </header>

            <section class="flex gap-4" aria-labelledby="garden-summary-title">
                <button
                    type="button"
                    class="group relative h-38 w-36 shrink-0 self-stretch overflow-hidden rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    aria-label="Buka foto kebun"
                    @click="
                        openImage({
                            src: detail.image,
                            alt: `Tanaman ${detail.crop} di ${detail.name}`,
                            title: 'Foto kebun',
                            caption: 'Foto kebun dari data demo.',
                        })
                    "
                >
                    <img
                        :src="detail.image"
                        :alt="`Tanaman ${detail.crop} di ${detail.name}`"
                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />
                    <span
                        class="absolute right-2 bottom-2 flex h-7 w-7 items-center justify-center rounded-lg bg-card/95 text-secondary shadow-sm"
                        aria-hidden="true"
                        ><Maximize2 :size="15"
                    /></span>
                </button>
                <div class="min-w-0 flex-1">
                    <h2 id="garden-summary-title" class="sr-only">
                        Ringkasan kebun
                    </h2>
                    <div class="flex flex-col gap-2.5 text-sm">
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                            ><MapPin
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            /><span class="truncate"
                                >{{ detail.location }},
                                {{ detail.district }}</span
                            ></span
                        >
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                            ><MapPin
                                :size="17"
                                class="shrink-0 text-background"
                                aria-hidden="true"
                            /><span class="">{{ detail.address }}</span></span
                        >
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                            ><Sprout
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            /><span class="truncate">{{
                                detail.crop
                            }}</span></span
                        >
                        <span
                            class="flex min-w-0 items-center gap-2 text-card-foreground"
                            ><LandPlot
                                :size="17"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            /><span class="truncate"
                                >{{ detail.plantedArea }} /
                                {{ detail.totalArea }}</span
                            ></span
                        >
                        <span
                            :class="['inline-flex w-fit items-center gap-2 rounded-full border px-2.5 py-1 text-xs font-semibold', healthTone(detail.harvestStatus)]"
                            ><component
                                :is="healthIcon(detail.harvestStatus)"
                                :size="17"
                                class="shrink-0"
                                aria-hidden="true"
                            /><span class="truncate">{{
                                detail.harvestStatus
                            }}</span></span
                        >
                    </div>
                </div>
            </section>

            <!-- Comment top summary card, needed to verify whether this is needed or not -->
            <!-- <section class="rounded-2xl bg-muted p-4" aria-label="Kondisi dan proyeksi kebun">
                <h2 class="text-sm font-semibold text-card-foreground">Kondisi tanaman · {{ detail.healthStatus }}</h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ detail.plantCondition }}</p>
                <p class="mt-4 text-xs text-muted-foreground">Proyeksi pendapatan kebun</p><p class="mt-1 text-xl font-semibold text-success">{{ formatRupiah(detail.projectedRevenue) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Estimasi demo · Tanam {{ detail.plantedAt }}</p>
            </section> -->

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Detail budidaya"
            >
                <article
                    v-for="card in [
                        {
                            icon: Scale,
                            title: 'Total Panen',
                            value: detail.weight,
                        },
                        {
                            icon: CalendarDays,
                            title: 'Mulai tanam',
                            value: detail.plantedAt,
                        },
                        { icon: Wheat, title: 'Bibit', value: detail.seed },
                        {
                            icon: CloudSun,
                            title: 'Cuaca saat ini',
                            value: `${detail.weather.condition} · ${detail.weather.temperature}`,
                            subtext: `Kelembapan ${detail.weather.humidity}`,
                        },
                    ]"
                    :key="card.title"
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-secondary">
                        <component
                            :is="card.icon"
                            :size="18"
                            aria-hidden="true"
                        />
                        <h2 class="text-sm font-medium">{{ card.title }}</h2>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-card-foreground">
                        {{ card.value }}
                    </p>
                    <!-- Commented to turn off subtext at cards -->
                    <!-- <p class="mt-1 text-xs text-[#93a99f]">
                        {{ card.subtext }}
                    </p> -->
                </article>
            </section>

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Ringkasan keuangan"
            >
                <article
                    class="rounded-2xl border border-success/20 bg-success/10 p-3.5"
                >
                    <div class="flex items-center gap-2 text-success">
                        <TrendingUp :size="17" aria-hidden="true" />
                        <h2 class="text-xs font-medium">Pendapatan</h2>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-success">
                        {{ formatRupiah(detail.revenue) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-error/20 bg-error/10 p-3.5"
                >
                    <div class="flex items-center gap-2 text-error">
                        <TrendingDown :size="17" aria-hidden="true" />
                        <h2 class="text-xs font-medium">Pengeluaran</h2>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-error">
                        {{ formatRupiah(detail.expenses) }}
                    </p>
                </article>
            </section>

            <section class="space-y-3" aria-labelledby="plant-progress-title">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="plant-progress-title"
                        class="text-lg font-semibold text-card-foreground"
                    >
                        Perkembangan tanaman
                    </h2>
                    <span class="text-sm font-semibold text-secondary"
                        >{{ detail.growth }}%</span
                    >
                </div>
                <div class="rounded-2xl bg-muted p-4">
                    <div
                        class="flex items-center justify-between text-sm text-card-foreground"
                    >
                        <span class="flex items-center gap-2"
                            ><Activity
                                :size="18"
                                class="text-secondary"
                                aria-hidden="true"
                            />Fase pertumbuhan</span
                        ><span class="font-medium text-secondary">{{
                            detail.growthCategoryLabel
                        }}</span>
                    </div>
                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-card"
                        aria-label="Kemajuan pertumbuhan tanaman"
                    >
                        <div
                            class="h-full rounded-full bg-primary"
                            :style="{ width: `${detail.growth}%` }"
                        />
                    </div>
                    <div
                        class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span>Proyeksi Panen: {{ detail.harvestAt }}</span>
                    </div>
                </div>
                <div class="rounded-2xl bg-muted p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3
                                class="text-base font-semibold text-card-foreground"
                            >
                                Kategori pertumbuhan
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Berdasarkan analisis foto terakhir
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-card px-2.5 py-1 text-xs font-semibold text-secondary"
                            >Tahap {{ selectedGrowthStage.number }} dari 5</span
                        >
                    </div>
                    <div
                        class="mt-4 grid grid-cols-5 gap-1.5"
                        aria-label="Lima kategori pertumbuhan tanaman"
                    >
                        <button
                            v-for="stage in growthStages"
                            :key="stage.number"
                            type="button"
                            :class="[
                                'min-w-0 rounded-lg px-1 py-2 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                stage.number === selectedStageNumber
                                    ? 'bg-secondary text-secondary-foreground'
                                    : 'bg-card text-muted-foreground',
                            ]"
                            :aria-label="`Pilih fase ${stage.number}, ${stage.label}`"
                            :aria-pressed="selectedStageNumber === stage.number"
                            @click="selectGrowthStage(stage.number)"
                        >
                            <span
                                class="relative mb-1.5 block h-12 overflow-hidden rounded-md bg-muted"
                                ><img
                                    :src="stage.image"
                                    :alt="`Gambar fase ${stage.label}`"
                                    class="h-full w-full object-cover"
                            /></span>
                            <p class="text-sm font-semibold">
                                {{ stage.number }}
                            </p>
                            <p class="mt-0.5 text-[10px] font-semibold">
                                {{ stage.percent }}%
                            </p>
                            <p class="mt-1 text-[10px] leading-tight">
                                {{ stage.range }}
                            </p>
                        </button>
                    </div>
                    <button
                        type="button"
                        class="group mt-4 block w-full overflow-hidden rounded-xl bg-card text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Buka gambar fase pertumbuhan terpilih"
                        @click="openGrowthStageImage"
                    >
                        <span
                            class="relative block h-40 overflow-hidden rounded-xl bg-muted"
                            ><img
                                :key="selectedGrowthStage.number"
                                :src="selectedGrowthStage.image"
                                :alt="`Referensi fase ${selectedGrowthStage.label}`"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            /><span
                                class="absolute right-2 bottom-2 rounded-lg bg-card/95 px-2.5 py-1.5 text-xs font-semibold text-secondary shadow-sm"
                                >Lihat gambar fase</span
                            ></span
                        >
                    </button>
                    <p class="mt-4 text-sm leading-5 text-card-foreground">
                        Fase dipilih:
                        <strong>{{ selectedGrowthStage.label }}</strong> ({{
                            selectedGrowthStage.range
                        }}).<span
                            class="mt-1 block text-xs text-muted-foreground"
                            >Hasil AI: {{ detail.growthCategoryLabel }} ·
                            confidence {{ detail.aiConfidence }}%</span
                        >
                    </p>
                </div>
            </section>

            <section
                class="space-y-3 rounded-2xl border border-border bg-card p-4"
                aria-labelledby="ai-summary-title"
            >
                <div class="flex items-center gap-2">
                    <CircleCheck
                        :size="20"
                        class="text-primary"
                        aria-hidden="true"
                    />
                    <h2
                        id="ai-summary-title"
                        class="text-lg font-semibold text-card-foreground"
                    >
                        Analisa AI
                    </h2>
                    <span
                        class="ml-auto rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-secondary"
                        >{{ detail.aiConfidence }}% yakin</span
                    >
                </div>
                <p class="text-sm leading-6 text-card-foreground">
                    {{ detail.summary }}
                </p>
                <article class="rounded-xl border border-border bg-muted p-3.5">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-surface text-secondary"
                            ><TrendingUp :size="18" aria-hidden="true"
                        /></span>
                        <div>
                            <h3 class="text-sm font-semibold text-secondary">
                                Analisa perkembangan sampai panen
                            </h3>
                            <p
                                class="mt-1.5 text-sm leading-5 text-card-foreground"
                            >
                                Tanaman berada di fase
                                <strong>{{
                                    detail.growthCategoryLabel
                                }}</strong>
                                dan diproyeksikan siap panen pada
                                <strong>{{ detail.harvestAt }}</strong> jika
                                perawatan berjalan konsisten.
                            </p>
                        </div>
                    </div>
                </article>
                <article class="rounded-xl border border-border bg-muted p-3.5">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-warning/20 text-warning"
                            ><Activity :size="18" aria-hidden="true"
                        /></span>
                        <div>
                            <h3 class="text-sm font-semibold text-warning">
                                Next step
                            </h3>
                            <p
                                class="mt-1.5 text-sm leading-5 text-card-foreground"
                            >
                                Jadwalkan penyiraman rutin,
                                <strong
                                    >cek kondisi daun setiap 2–3 hari, dan
                                    tambahkan nutrisi</strong
                                >
                                sesuai rekomendasi petugas lapangan.
                            </p>
                        </div>
                    </div>
                </article>
                <article
                    class="rounded-xl border border-error/25 bg-error/5 p-3.5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error/10 text-error"
                            ><TrendingDown :size="18" aria-hidden="true"
                        /></span>
                        <div>
                            <h3 class="text-sm font-semibold text-error">
                                Risiko perkembangan
                            </h3>
                            <p
                                class="mt-1.5 text-sm leading-5 text-card-foreground"
                            >
                                <strong>{{
                                    detail.healthStatus === 'Sehat'
                                        ? 'Tetap pantau perubahan kondisi kebun.'
                                        : 'Waspadai perubahan kondisi tanaman.'
                                }}</strong>
                                Periksa genangan air dan perubahan warna daun
                                agar pertumbuhan tetap terjaga.
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <Link
                :href="`/aktivitas?kebun=${encodeURIComponent(detail.id)}`"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-primary px-4 text-base font-semibold text-primary-foreground transition-colors hover:bg-success focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >Aktivitas Kebun</Link
            >
        </div>

        <div
            v-if="selectedImage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-foreground/75 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="selectedImage.title"
            @click.self="closeImage"
        >
            <div
                class="w-full max-w-[380px] overflow-hidden rounded-2xl border border-border bg-card shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border px-4 py-3"
                >
                    <h2 class="text-sm font-semibold text-card-foreground">
                        {{ selectedImage.title }}
                    </h2>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-secondary transition-colors hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                        aria-label="Tutup pratinjau gambar"
                        @click="closeImage"
                    >
                        <X :size="20" aria-hidden="true" />
                    </button>
                </div>
                <div class="bg-muted p-3">
                    <img
                        :src="selectedImage.src"
                        :alt="selectedImage.alt"
                        class="aspect-[4/3] w-full rounded-xl object-cover"
                    />
                </div>
                <p class="px-4 py-3 text-xs leading-5 text-muted-foreground">
                    {{ selectedImage.caption }}
                </p>
            </div>
        </div>
    </FarmerMobileShell>
</template>

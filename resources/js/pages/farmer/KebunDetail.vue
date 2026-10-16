<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import {
    Activity,
    ArrowLeft,
    Camera,
    CalendarDays,
    CircleCheck,
    CloudSun,
    LandPlot,
    MapPin,
    Scale,
    Sprout,
    TrendingDown,
    TrendingUp,
    Wheat,
    X,
} from "@lucide/vue";
import FarmerMobileShell from "@/components/farmer/FarmerMobileShell.vue";
import { computed, ref } from "vue";

const detail = {
    name: "Detail Kebon A",
    location:
        "Jl. Raya Dieng KM 12, Desa Surengede, Kec. Kejajar, Kab. Wonosobo, Jawa Tengah",
    crop: "Jagung",
    cropType: "Jagung Hibrida",
    seed: "Jagung hibrida BISI-18",
    totalArea: "1.000 ha",
    plantedArea: "300 ha",
    weight: "42 ton",
    plantedAt: "11 Januari 2026",
    harvestAt: "11 Maret 2026",
    revenue: 50_000_000,
    expenses: 18_500_000,
    growth: 78,
    growthCategory: 3,
    growthCategoryLabel: "Vegetatif aktif",
    aiConfidence: 92,
    harvestStatus: "Tumbuh baik",
    weather: {
        condition: "Cerah berawan",
        temperature: "26°C",
        humidity: "78%",
    },
};

const growthStages = [
    {
        number: 1,
        label: "Kecambah",
        range: "0–9 HST",
        percent: 20,
        image: "/images/farmer/jagung-berkecambah.jpg",
    },
    {
        number: 2,
        label: "Bibit awal",
        range: "10–29 HST",
        percent: 40,
        image: "/images/farmer/jagung-tumbuh-fase-2.jpg",
    },
    {
        number: 3,
        label: "Vegetatif aktif",
        range: "30–59 HST",
        percent: 60,
        image: "/images/farmer/corn-field.jpeg",
    },
    {
        number: 4,
        label: "Pembungaan",
        range: "60–79 HST",
        percent: 80,
        image: "/images/farmer/jagung-tunas.jpeg",
    },
    {
        number: 5,
        label: "Pengisian & panen",
        range: "80–99 HST",
        percent: 100,
        image: "/images/farmer/jagung-panen.jpg",
    },
] as const;

type ImagePreview = {
    src: string;
    alt: string;
    title: string;
    caption: string;
};

const selectedStageNumber = ref(detail.growthCategory);
const selectedImage = ref<ImagePreview | null>(null);
const selectedGrowthStage = computed(
    () =>
        growthStages.find(
            (stage) => stage.number === selectedStageNumber.value,
        ) ?? growthStages[0],
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
        alt: `Referensi tanaman jagung fase ${stage.label}`,
        title: `Fase ${stage.number}: ${stage.label}`,
        caption: `${stage.range}. Tampilan ini membantu petugas mencocokkan hasil foto dengan fase pertumbuhan tanaman.`,
    });
}

const formatRupiah = (value: number): string =>
    new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
        maximumFractionDigits: 0,
    }).format(value);

function goBack(): void {
    void router.visit("/kebun");
}
</script>

<template>
    <Head title="Detail Kebun" />

    <FarmerMobileShell active-key="garden">
        <div class="space-y-6 px-5 pt-8 pb-6">
            <header class="flex items-center gap-3">
                <button
                    type="button"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#d9d9d9] text-[#1c5e21] transition-colors hover:bg-[#d9d9d9] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#68bb6c]"
                    aria-label="Kembali ke daftar kebun"
                    @click="goBack"
                >
                    <ArrowLeft
                        :size="20"
                        :stroke-width="1.8"
                        aria-hidden="true"
                    />
                </button>
                <div>
                    <p class="text-sm font-medium text-[#93a99f]">Kebun</p>
                    <h1 class="text-2xl leading-8 font-semibold text-[#070807]">
                        {{ detail.name }}
                    </h1>
                </div>
            </header>

            <section class="flex gap-4" aria-labelledby="garden-summary-title">
                <button
                    type="button"
                    class="group relative h-38 w-36 shrink-0 self-stretch overflow-hidden rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]"
                    aria-label="Buka foto tanaman jagung"
                    @click="
                        openImage({
                            src: '/images/farmer/corn-field.jpeg',
                            alt: 'Tanaman jagung di kebun',
                            title: 'Foto kebun',
                            caption:
                                'Foto terakhir yang diunggah petugas dari kebun.',
                        })
                    "
                >
                    <img
                        src="/images/farmer/corn-field.jpeg"
                        alt="Tanaman jagung di kebun"
                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                    />
                    <span
                        class="absolute right-2 bottom-2 flex h-7 w-7 items-center justify-center rounded-lg bg-white/95 text-[#1c5e21] shadow-sm"
                        aria-hidden="true"
                    >
                        <Camera :size="15" />
                    </span>
                </button>
                <div class="min-w-0 flex-1">
                    <h2 id="garden-summary-title" class="sr-only">
                        Ringkasan kebun
                    </h2>
                    <div class="flex flex-col gap-2.5 text-sm">
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                            :title="detail.location"
                        >
                            <MapPin
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.location }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <Sprout
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.crop }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <Wheat
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ detail.cropType }}</span>
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#070807]"
                        >
                            <LandPlot
                                :size="17"
                                class="shrink-0 text-[#68bb6c]"
                                aria-hidden="true"
                            />
                            <span class="truncate"
                                >{{ detail.plantedArea }} /
                                {{ detail.totalArea }}</span
                            >
                        </span>
                        <span
                            class="flex min-w-0 items-center gap-2 text-[#1c5e21]"
                        >
                            <CircleCheck
                                :size="17"
                                class="shrink-0"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{
                                detail.harvestStatus
                            }}</span>
                        </span>
                    </div>
                </div>
            </section>

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Detail budidaya"
            >
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <Scale :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Total Panen</h2>
                    </div>
                    <p class="mt-3 text-lg font-semibold text-[#070807]">
                        {{ detail.weight }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <CalendarDays :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Mulai tanam</h2>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-[#070807]">
                        {{ detail.plantedAt }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <Wheat :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Bibit</h2>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-[#070807]">
                        {{ detail.seed }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <CloudSun :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Cuaca saat ini</h2>
                    </div>
                    <p class="mt-3 text-sm font-semibold text-[#070807]">
                        {{ detail.weather.condition }} ·
                        {{ detail.weather.temperature }}
                    </p>
                    <p class="mt-1 text-xs text-[#93a99f]">
                        Kelembapan {{ detail.weather.humidity }}
                    </p>
                </article>
            </section>

            <section
                class="grid grid-cols-2 gap-3"
                aria-label="Ringkasan keuangan"
            >
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#1c5e21]">
                        <TrendingUp :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pendapatan</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-[#1c5e21]">
                        {{ formatRupiah(detail.revenue) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-[#bc0202]">
                        <TrendingDown :size="18" aria-hidden="true" />
                        <h2 class="text-sm font-medium">Pengeluaran</h2>
                    </div>
                    <p class="mt-3 text-xl font-semibold text-[#bc0202]">
                        {{ formatRupiah(detail.expenses) }}
                    </p>
                </article>
            </section>

            <section class="space-y-3" aria-labelledby="plant-progress-title">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        id="plant-progress-title"
                        class="text-lg font-semibold text-[#070807]"
                    >
                        Perkembangan tanaman
                    </h2>
                    <span class="text-sm font-semibold text-[#1c5e21]"
                        >{{ detail.growth }}%</span
                    >
                </div>
                <div
                    class="rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex items-center justify-between text-sm text-[#070807]"
                    >
                        <span class="flex items-center gap-2">
                            <Activity
                                :size="18"
                                class="text-[#1c5e21]"
                                aria-hidden="true"
                            />
                            Fase pertumbuhan
                        </span>
                        <span class="font-medium text-[#1c5e21]">{{
                            detail.growthCategoryLabel
                        }}</span>
                    </div>
                    <div
                        class="mt-4 h-3 overflow-hidden rounded-full bg-[#edf3ee]"
                        aria-label="Kemajuan pertumbuhan tanaman"
                    >
                        <div
                            class="h-full rounded-full bg-[#68bb6c]"
                            :style="{
                                width: `${detail.growth}%`,
                            }"
                        />
                    </div>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[#93a99f]"
                    >
                        <!-- <span
                            >AI confidence {{ detail.aiConfidence }}%</span
                        > -->
                        <span>Proyeksi Panen: {{ detail.harvestAt }}</span>
                    </div>
                </div>

                <div class="rounded-2xl bg-[#d9d9d9] p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-base font-semibold text-[#070807]">
                                Kategori pertumbuhan
                            </h3>
                            <p class="mt-1 text-xs text-[#5f766a]">
                                Berdasarkan analisis foto terakhir
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-[#1c5e21]"
                        >
                            Tahap {{ selectedGrowthStage.number }} dari 5
                        </span>
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
                                'min-w-0 rounded-lg px-1 py-2 text-center transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]',
                                stage.number === selectedStageNumber
                                    ? 'bg-[#1c5e21] text-white'
                                    : 'bg-white text-[#93a99f]',
                            ]"
                            :aria-label="`Pilih fase ${stage.number}, ${stage.label}`"
                            :aria-pressed="selectedStageNumber === stage.number"
                            @click="selectGrowthStage(stage.number)"
                        >
                            <span
                                class="relative mb-1.5 block h-12 overflow-hidden rounded-md bg-[#edf3ee]"
                            >
                                <img
                                    :src="stage.image"
                                    :alt="`Gambar fase ${stage.label}`"
                                    class="h-full w-full object-cover"
                                />
                            </span>
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
                        class="group mt-4 block w-full overflow-hidden rounded-xl bg-white text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]"
                        aria-label="Buka gambar fase pertumbuhan terpilih"
                        @click="openGrowthStageImage"
                    >
                        <span
                            class="relative block h-40 overflow-hidden rounded-xl bg-[#edf3ee]"
                        >
                            <img
                                :key="selectedGrowthStage.number"
                                :src="selectedGrowthStage.image"
                                :alt="`Referensi fase ${selectedGrowthStage.label}`"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            />
                            <span
                                class="absolute right-2 bottom-2 rounded-lg bg-white/95 px-2.5 py-1.5 text-xs font-semibold text-[#1c5e21] shadow-sm"
                            >
                                Lihat gambar fase
                            </span>
                        </span>
                    </button>
                    <p class="mt-4 text-sm leading-5 text-[#070807]">
                        Fase dipilih:
                        <strong>{{ selectedGrowthStage.label }}</strong>
                        ({{ selectedGrowthStage.range }}).
                        <span class="mt-1 block text-xs text-[#5f766a]">
                            Hasil AI: {{ detail.growthCategoryLabel }} ·
                            confidence {{ detail.aiConfidence }}%
                        </span>
                    </p>
                </div>
            </section>

            <section
                class="space-y-3 rounded-2xl border border-[#d9d9d9] bg-white p-4 shadow-sm"
                aria-labelledby="ai-summary-title"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <CircleCheck
                            :size="20"
                            class="text-[#68bb6c]"
                            aria-hidden="true"
                        />
                        <h2
                            id="ai-summary-title"
                            class="text-lg font-semibold text-[#070807]"
                        >
                            Analisa AI
                        </h2>
                    </div>
                    <!-- Badge Presentasi Ai -->
                    <span
                        class="rounded-full bg-[#edf3ee] px-2.5 py-1 text-xs font-medium text-[#1c5e21]"
                    >
                        {{ detail.aiConfidence }}% yakin
                    </span>
                </div>

                <article
                    class="rounded-xl border border-[#d9d9d9] bg-[#f7fbf7] p-3.5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#abe7b2] text-[#1c5e21]"
                        >
                            <TrendingUp :size="18" aria-hidden="true" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#1c5e21]">
                                Analisa perkembangan sampai panen
                            </h3>
                            <p class="mt-1.5 text-sm leading-5 text-[#070807]">
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

                <article
                    class="rounded-xl border border-[#d9d9d9] bg-[#fffdf4] p-3.5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#f5e7a8] text-[#7a6200]"
                        >
                            <Activity :size="18" aria-hidden="true" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#7a6200]">
                                Next step
                            </h3>
                            <p class="mt-1.5 text-sm leading-5 text-[#070807]">
                                Jadwalkan penyiraman rutin,
                                <strong
                                    >cek kondisi daun setiap 2–3 hari, dan
                                    tambahkan nutrisi
                                </strong>
                                sesuai rekomendasi petugas lapangan.
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-xl border border-[#f0d1d1] bg-[#fff8f8] p-3.5"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#f8dede] text-[#bc0202]"
                        >
                            <TrendingDown :size="18" aria-hidden="true" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-[#bc0202]">
                                Negatif: risiko perkembangan
                            </h3>
                            <p class="mt-1.5 text-sm leading-5 text-[#070807]">
                                <strong
                                    >Waspadai genangan air dan perubahan warna
                                    daun.</strong
                                >
                                Kondisi tersebut dapat memperlambat pertumbuhan
                                dan menurunkan hasil panen.
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <button
                type="button"
                class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#68bb6c] px-4 text-base font-semibold text-white transition-colors hover:bg-[#4a943e] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]"
            >
                Aktivitas Kebun
            </button>
        </div>

        <div
            v-if="selectedImage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#070807]/75 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="selectedImage.title"
            @click.self="closeImage"
        >
            <div
                class="w-full max-w-[380px] overflow-hidden rounded-2xl border border-[#d9d9d9] bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-[#d9d9d9] px-4 py-3"
                >
                    <h2 class="text-sm font-semibold text-[#070807]">
                        {{ selectedImage.title }}
                    </h2>
                    <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-lg text-[#1c5e21] transition-colors hover:bg-[#edf3ee] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1c5e21]"
                        aria-label="Tutup pratinjau gambar"
                        @click="closeImage"
                    >
                        <X :size="20" aria-hidden="true" />
                    </button>
                </div>
                <div class="bg-[#edf3ee] p-3">
                    <img
                        :src="selectedImage.src"
                        :alt="selectedImage.alt"
                        class="aspect-[4/3] w-full rounded-xl object-cover"
                    />
                </div>
                <p class="px-4 py-3 text-xs leading-5 text-[#5f766a]">
                    {{ selectedImage.caption }}
                </p>
            </div>
        </div>
    </FarmerMobileShell>
</template>

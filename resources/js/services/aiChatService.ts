export type AiPromptIntent =
    | 'agriculture'
    | 'data'
    | 'infographic'
    | 'general'
    | 'out_of_scope';

export type AiPromptClassification = {
    intent: AiPromptIntent;
    confidence: number;
    topic: string | null;
};

const agricultureTerms = [
    'kebun',
    'lahan',
    'tanaman',
    'padi',
    'jagung',
    'kedelai',
    'sayur',
    'panen',
    'benih',
    'pupuk',
    'irigasi',
    'pengairan',
    'tanah',
    'hama',
    'penyakit',
    'pestisida',
    'cuaca',
    'kelembapan',
    'pertanian',
    'petani',
    'komoditas',
    'bibit',
];

const dataTerms = [
    'data',
    'laporan',
    'aktivitas',
    'kondisi',
    'hari ini',
    'terakhir',
    'riwayat',
    'biaya',
    'pendapatan',
    'keuangan',
    'stok',
    'jadwal',
    'status',
    'berapa',
    'total',
    'rincian',
    'jumlah',
    'harga',
    'penjualan',
];

const visualTerms = [
    'infografis',
    'infographic',
    'diagram',
    'visual',
    'visualisasikan',
    'poster',
    'bagan',
    'gambar',
    'ilustrasi',
    'gambarkan',
    'tampilkan',
    'tunjukkan',
    'skema',
    'alur',
    'grafik',
    'chart',
    'peta',
    'ringkas jadi',
    'sajikan dalam bentuk',
    'buatkan visual',
    'buat visual',
    'dalam bentuk gambar',
];

const outOfScopeTerms = [
    'kode program',
    'coding',
    'python',
    'javascript',
    'html',
    'css',
    'sql',
    'resep masakan',
    'film',
    'musik',
    'lirik lagu',
    'politik',
    'presiden',
    'saham',
    'crypto',
    'berita artis',
    'sepak bola',
];

const greetingPattern = /^(halo|hai|hi|selamat pagi|selamat siang|selamat sore|selamat malam|terima kasih|makasih)\b/i;

function normalizePrompt(value: string): string {
    return value
        .normalize('NFKC')
        .toLocaleLowerCase('id-ID')
        .replace(/\s+/g, ' ')
        .trim();
}

function includesAny(value: string, terms: string[]): string | null {
    return terms.find((term) => {
        const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return new RegExp(`(?:^|\\s)${escaped}(?:$|\\s|[?!,.:;])`, 'i').test(value);
    }) ?? null;
}

export function classifyAiPrompt(
    value: string,
    hasAttachments = false,
): AiPromptClassification {
    const prompt = normalizePrompt(value);
    const topic = includesAny(prompt, agricultureTerms);
    const dataSignal = includesAny(prompt, dataTerms);
    const visualSignal = includesAny(prompt, visualTerms);
    const outOfScopeSignal = includesAny(prompt, outOfScopeTerms);
    const generationSignal = /\b(buat|buatkan|generate|visualisasikan|gambarkan|tampilkan|sajikan|ringkas)\b/i.test(prompt);

    if (visualSignal && (topic || dataSignal || hasAttachments || generationSignal)) {
        return { intent: 'infographic', confidence: 0.94, topic: topic ?? dataSignal };
    }
    if (outOfScopeSignal && !topic && !dataSignal && !hasAttachments) {
        return { intent: 'out_of_scope', confidence: 0.98, topic: null };
    }
    if (dataSignal && (topic || hasAttachments || prompt.length > 8)) {
        return { intent: 'data', confidence: 0.88, topic: topic ?? dataSignal };
    }
    if (topic || hasAttachments) {
        return { intent: 'agriculture', confidence: 0.82, topic };
    }
    if (greetingPattern.test(prompt) || prompt.length <= 12) {
        return { intent: 'general', confidence: 0.72, topic: null };
    }

    return { intent: 'general', confidence: 0.55, topic: null };
}

export function shouldRequestInfographic(
    value: string,
    hasAttachments = false,
): boolean {
    return classifyAiPrompt(value, hasAttachments).intent === 'infographic';
}

/** Remove transport noise while keeping the markdown structure for the safe renderer. */
export function normalizeAiResponse(value: string): string {
    return value
        .replace(/^\uFEFF/, '')
        .replace(/\r\n?/g, '\n')
        .replace(/^(?:assistant|ai)\s*:\s*/i, '')
        .replace(/\\([*_`~>#-])/g, '$1')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

/** Convert a model response to plain Indonesian text for speech synthesis. */
export function toSpeechText(value: string): string {
    return normalizeAiResponse(value)
        .replace(/!\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/^#{1,6}\s*/gm, '')
        .replace(/^>\s*/gm, '')
        .replace(/^\s*[-*+]\s+/gm, '')
        .replace(/^\s*\d+[.)]\s+/gm, '')
        .replace(/[*_`~]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

export function estimateTokens(value: string): number {
    return Math.max(1, Math.ceil(normalizeAiResponse(value).length / 4));
}

export function outOfScopeResponse(): string {
    return 'Topik itu belum tersedia di Asisten Kebun. Saya fokus pada data dan aktivitas yang tersedia di aplikasi, perawatan tanaman, serta infografis pertanian. Coba tanyakan kondisi kebun, aktivitas terakhir, atau minta ringkasan visual.';
}

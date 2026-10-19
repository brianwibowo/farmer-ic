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
    'sawah',
    'ladang',
    'tanaman',
    'padi',
    'jagung',
    'kedelai',
    'cabai',
    'tomat',
    'bawang',
    'tebu',
    'kopi',
    'kakao',
    'teh',
    'sayur',
    'sayuran',
    'buah',
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
    'smart farming',
    'smartfarm',
    'agritech',
    'pertanian cerdas',
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

const greetingPattern =
    /^(halo|hai|hi|selamat pagi|selamat siang|selamat sore|selamat malam|terima kasih|makasih)\b/i;

/**
 * A data intent is reserved for a known dashboard lookup. Generic words such
 * as "berapa", "kondisi", or "status" also occur in agronomy questions and
 * must not divert those questions to the local demo resolver.
 */
const dashboardDataPatterns = [
    /\b(?:data|laporan|riwayat)\s+(?:kebun|lahan|aktivitas|kegiatan|keuangan|pendapatan|transaksi)\b/i,
    /\b(?:data|laporan|riwayat)\s+(?:panen|pemupukan|pengairan|pestisida|penanaman)\b/i,
    /\b(?:aktivitas|kegiatan)\s+(?:terakhir|terbaru|hari ini|minggu ini)\b/i,
    /\b(?:kondisi|status|kesehatan)\s+(?:kebun|lahan)\b/i,
    /\b(?:total|jumlah)\s+(?:kebun|lahan|aktivitas|kegiatan|transaksi)\b/i,
    /\b(?:ringkasan|rincian)\s+(?:kebun|lahan|aktivitas|kegiatan|keuangan|pendapatan|transaksi)\b/i,
    /\b(?:pendapatan|pemasukan|omzet|transaksi)\s+(?:kebun|terakhir|terbaru|tahun|bulan)\b/i,
    /\b(?:biaya|pengeluaran)\s+(?:operasional|kebun|terakhir|terbaru|tahun|bulan)\b/i,
    /\b(?:keuangan|pendapatan|pemasukan|omzet|transaksi)\b/i,
];

const guidancePatterns = [
    /\b(?:cara|bagaimana|dosis|rekomendasi|panduan|tips?|langkah|mengatasi|mengendalikan|mencegah|menanam|memupuk|mengairi|merawat|jelaskan|apa itu)\b/i,
    /\b(?:harga|biaya)\s+(?:pupuk|benih|pestisida|irigasi|tanaman|jagung|padi|kedelai)\b/i,
];

/** Keep a single user turn bounded before it reaches the model. */
export const MAX_AI_PROMPT_CHARS = 6000;

function normalizePrompt(value: string): string {
    return value
        .normalize('NFKC')
        .toLocaleLowerCase('id-ID')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Compact user input without changing its meaning. The API already owns the
 * conversation history, so sending repeated whitespace and very long pasted
 * transcripts only increases context cost. A visible ellipsis marks input
 * that was bounded so the model does not receive a misleading sentence end.
 */
export function compactAiPrompt(value: string): string {
    const compact = value
        .normalize('NFKC')
        .replace(/\r\n?/g, '\n')
        .split('\n')
        .map((line) => line.replace(/[ \t]+/g, ' ').trim())
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

    if (compact.length <= MAX_AI_PROMPT_CHARS) return compact;
    return `${compact.slice(0, MAX_AI_PROMPT_CHARS - 1).trimEnd()}…`;
}

function includesAny(value: string, terms: string[]): string | null {
    return (
        terms.find((term) => {
            const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp(
                `(?:^|\\s)${escaped}(?:$|\\s|[?!,.:;])`,
                'i',
            ).test(value);
        }) ?? null
    );
}

export function isDashboardDataPrompt(value: string): boolean {
    const prompt = normalizePrompt(value);
    if (guidancePatterns.some((pattern) => pattern.test(prompt))) {
        // A direct dashboard phrase such as "bagaimana kondisi kebun" is a
        // lookup, while "bagaimana merawat tanaman" remains guidance.
        const isExplicitLookup =
            /\b(?:kondisi|status|kesehatan)\s+(?:kebun|lahan)\b/i.test(prompt);
        if (!isExplicitLookup) return false;
    }

    return dashboardDataPatterns.some((pattern) => pattern.test(prompt));
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
    const dashboardDataSignal = isDashboardDataPrompt(prompt);
    // Keep a clearly unsupported topic out of the visual/data branches. This
    // prevents requests such as "buat poster sepak bola" from becoming an
    // agricultural infographic merely because they contain a visual verb.
    if (outOfScopeSignal && !hasAttachments) {
        return { intent: 'out_of_scope', confidence: 0.98, topic: null };
    }

    const hasSupportedVisualContext = Boolean(
        topic || dashboardDataSignal || hasAttachments,
    );

    if (visualSignal && hasSupportedVisualContext) {
        return {
            intent: 'infographic',
            confidence: 0.94,
            topic: topic ?? dataSignal,
        };
    }
    if (visualSignal && !hasSupportedVisualContext) {
        return { intent: 'out_of_scope', confidence: 0.96, topic: null };
    }
    if (dashboardDataSignal && (topic || dataSignal || hasAttachments)) {
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

function removeControlChars(value: string): string {
    return Array.from(value)
        .filter((character) => {
            const code = character.charCodeAt(0);
            return code === 9 || code === 10 || code === 13 || code > 31;
        })
        .join('');
}

/** Remove transport noise while keeping the markdown structure for the safe renderer. */
export function normalizeAiResponse(value: string): string {
    const normalized = removeControlChars(value)
        .replace(/^\uFEFF/, '')
        .replace(/\r\n?/g, '\n')
        .replace(/^(?:assistant|ai)\s*:\s*/i, '')
        .replace(/\[DONE\]\s*$/i, '')
        .replace(/^\s*```(?:markdown|text)?\s*/i, '')
        .replace(/\s*```\s*$/i, '')
        .replace(/\\([*_`~>#-])/g, '$1')
        .replace(/[ \t]+\n/g, '\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();

    return softenAiCopy(normalized);
}

/** Reject transport-only or punctuation-only model output before it reaches UI. */
export function hasUsableAiResponse(value: string): boolean {
    const normalized = normalizeAiResponse(value);
    return normalized.length >= 2 && /[\p{L}\p{N}]/u.test(normalized);
}

/** Keep generated agronomy copy cautious until it is backed by a source. */
export function softenAiCopy(value: string): string {
    return value
        .replace(/\bhasil\s+maksimal\b/gi, 'hasil yang sesuai kondisi')
        .replace(/\bdosis\s+maksimal\b/gi, 'dosis sesuai label')
        .replace(/\b(?:paling\s+)?optimal(?:nya)?\b/gi, 'sesuai kondisi')
        .replace(/\bterbaik\b/gi, 'yang dapat dipertimbangkan')
        .replace(/\b(?:pasti|dijamin|terjamin)\b/gi, 'berpotensi')
        .replace(/\bsempurna\b/gi, 'lebih baik');
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
    const text = normalizeAiResponse(value)
        // Image URLs are transport metadata, not meaningful model text. They
        // can otherwise inflate the browser-side estimate by hundreds of
        // tokens when an infographic is attached to a response.
        .replace(/!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)/gi, '[gambar]')
        .replace(/https?:\/\/\S+/gi, '[tautan]')
        .replace(/\s+/g, ' ')
        .trim();
    if (!text) return 0;

    // This is intentionally labelled an estimate in the report. Indonesian
    // words and markdown punctuation tokenize a little more densely than the
    // common four-characters-per-token rule, so 3.7 is a conservative proxy.
    return Math.max(1, Math.ceil(text.length / 3.7));
}

export function outOfScopeResponse(): string {
    return 'Topik itu belum tersedia di Asisten Kebun. Saya fokus pada data dan aktivitas yang tersedia di aplikasi, perawatan tanaman, serta infografis pertanian. Coba tanyakan kondisi kebun, aktivitas terakhir, atau minta ringkasan visual.';
}

export type AiIntent =
    | 'data'
    | 'knowledge'
    | 'llm'
    | 'infographic'
    | 'out_of_scope';

export type AiDomain = 'finance' | 'activity' | 'garden' | 'agronomy' | null;

export type AiPeriod = 'current' | 'quarter' | 'historical' | 'unknown';

export type AiIntentRoute = {
    intent: AiIntent;
    confidence: number;
    topic: string | null;
    domain: AiDomain;
    period: AiPeriod;
    needsClarification: boolean;
    reason:
        | 'explicit_data_lookup'
        | 'finance_analysis'
        | 'current_finance_unavailable'
        | 'explicit_visual_request'
        | 'agriculture_guidance'
        | 'unsupported_topic'
        | 'general_question';
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
    'bibit',
    'pupuk',
    'pemupuk',
    'irigasi',
    'pengairan',
    'penyiraman',
    'penanaman',
    'perawatan',
    'pemeliharaan',
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
    'petani',
    'komoditas',
];

const financeTerms = [
    'keuangan',
    'finansial',
    'pendapatan',
    'pemasukan',
    'omzet',
    'transaksi',
    'laba',
    'profit',
    'saldo',
    'keuntungan',
    'pengeluaran',
    'biaya',
];

const activityTerms = [
    'aktivitas',
    'kegiatan',
    'panen',
    'pemupukan',
    'pengairan',
    'pestisida',
    'penanaman',
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
    'skema',
    'alur',
    'grafik',
    'chart',
    'peta',
];

const explicitDataTerms = [
    'data',
    'laporan',
    'riwayat',
    'ringkasan',
    'rincian',
    'jumlah',
    'total',
    'kondisi',
    'status',
    'terakhir',
    'terbaru',
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
    'buat kode',
    'tulis kode',
    'buat program',
];

const currentPeriodPattern =
    /\b(?:bulan ini|bulan sekarang|bulan berjalan|periode ini|periode berjalan|saat ini|sekarang|kini|hari ini|minggu ini)\b/i;
const quarterPattern = /\bq(?:uarter)?\s*[1-4]\b/i;
const historicalPattern =
    /\b(?:tahun lalu|tahun ini|bulan lalu|periode lalu|q[1-4]|quarter)\b/i;

function normalize(value: string): string {
    return value
        .normalize('NFKC')
        .toLocaleLowerCase('id-ID')
        .replace(/\s+/g, ' ')
        .trim();
}

function hasTerm(value: string, terms: string[]): boolean {
    return terms.some((term) => {
        const escaped = term.replace(/[.*+?^$()|[\]\\]/g, '\\$&');
        return new RegExp(
            '(?:^|\\s)' + escaped + '(?:nya|mu|ku)?(?:$|\\s|[?!,.:;()])',
            'i',
        ).test(value);
    });
}

function firstTerm(value: string, terms: string[]): string | null {
    return terms.find((term) => hasTerm(value, [term])) ?? null;
}

function detectDomain(value: string): AiDomain {
    if (hasTerm(value, financeTerms)) return 'finance';
    if (/\b(?:aktivitas|kegiatan)\b/i.test(value)) return 'activity';
    if (/\b(?:kebun|lahan|sawah|ladang)\b/i.test(value)) return 'garden';
    if (hasTerm(value, agricultureTerms)) return 'agronomy';
    if (hasTerm(value, activityTerms)) return 'activity';
    return null;
}

function detectPeriod(value: string): AiPeriod {
    if (currentPeriodPattern.test(value)) return 'current';
    if (quarterPattern.test(value)) return 'quarter';
    if (historicalPattern.test(value)) return 'historical';
    return 'unknown';
}

function isGreeting(value: string): boolean {
    return /^(?:halo|hai|hei|hi|pagi|siang|sore|malam|terima kasih|makasih)(?:[!.,\s]|$)/i.test(
        value,
    );
}

function hasExplicitDataLookup(value: string, domain: AiDomain): boolean {
    if (!domain || !hasTerm(value, explicitDataTerms)) return false;

    if (domain === 'finance') {
        return /\b(?:keuangan|finansial|pendapatan|pemasukan|omzet|transaksi|laba|profit|saldo|biaya|pengeluaran)\b/i.test(
            value,
        );
    }

    return true;
}

export function routeAiPrompt(
    value: string,
    hasAttachments = false,
): AiIntentRoute {
    const prompt = normalize(value);
    const domain = detectDomain(prompt);
    const period = detectPeriod(prompt);
    const visualSignal = hasTerm(prompt, visualTerms);
    const topic =
        firstTerm(prompt, agricultureTerms) ?? firstTerm(prompt, financeTerms);

    if (!prompt && hasAttachments) {
        return {
            intent: 'llm',
            confidence: 0.72,
            topic: null,
            domain: 'agronomy',
            period: 'unknown',
            needsClarification: false,
            reason: 'agriculture_guidance',
        };
    }

    if (hasTerm(prompt, outOfScopeTerms) && !hasAttachments) {
        return {
            intent: 'out_of_scope',
            confidence: 0.98,
            topic: null,
            domain: null,
            period,
            needsClarification: false,
            reason: 'unsupported_topic',
        };
    }

    // An explicit visual verb is a presentation request. “Gambarkan kondisi
    // lahan” must not silently become a data summary just because it contains
    // the word “kondisi”.
    if (visualSignal && Boolean(domain)) {
        return {
            intent: 'infographic',
            confidence: 0.94,
            topic,
            domain,
            period,
            needsClarification: false,
            reason: 'explicit_visual_request',
        };
    }

    if (domain === 'finance') {
        return {
            intent: 'llm',
            confidence: 0.9,
            topic,
            domain,
            period,
            needsClarification: false,
            reason: 'finance_analysis',
        };
    }

    // A concrete data lookup remains the default for “tampilkan kondisi
    // kebun” because “tampilkan” is intentionally not a visual trigger.
    if (hasExplicitDataLookup(prompt, domain)) {
        return {
            intent: 'data',
            confidence: 0.92,
            topic,
            domain,
            period,
            needsClarification: false,
            reason: 'explicit_data_lookup',
        };
    }

    if (domain === 'agronomy' || domain === 'garden' || domain === 'activity') {
        return {
            intent: 'llm',
            confidence: 0.82,
            topic,
            domain,
            period,
            needsClarification: false,
            reason: 'agriculture_guidance',
        };
    }

    return {
        intent: 'llm',
        confidence: 0.55,
        topic,
        domain,
        period,
        needsClarification:
            prompt.length > 0 && prompt.length < 14 && !isGreeting(prompt),
        reason: 'general_question',
    };
}

export function isDashboardDataPrompt(value: string): boolean {
    return routeAiPrompt(value).intent === 'data';
}

export function classifyAiPrompt(
    value: string,
    hasAttachments = false,
): AiIntentRoute {
    return routeAiPrompt(value, hasAttachments);
}

export function shouldRequestInfographic(
    value: string,
    hasAttachments = false,
): boolean {
    return routeAiPrompt(value, hasAttachments).intent === 'infographic';
}

export type AiPromptIntent = AiIntent;
export type AiPromptClassification = AiIntentRoute;

export const normalizeAiIntentPrompt = normalize;

import { aiKnowledgeChunks, type AiKnowledgeChunk } from '@/data/aiKnowledge';
import { routeAiPrompt } from '@/services/aiIntentRouter';

export type AiKnowledgeMatch = AiKnowledgeChunk & {
    score: number;
};

export type AiKnowledgeAnswer = {
    content: string;
    source: string;
    chunkIds: string[];
};

function wait(milliseconds: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

function normalize(value: string): string {
    return value
        .normalize('NFKC')
        .toLocaleLowerCase('id-ID')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function terms(value: string): Set<string> {
    return new Set(
        normalize(value)
            .split(' ')
            .filter((term) => term.length > 2),
    );
}

export function retrieveKnowledge(
    prompt: string,
    limit = 3,
): AiKnowledgeMatch[] {
    const promptTerms = terms(prompt);

    return aiKnowledgeChunks
        .map((chunk) => {
            const keywordTerms = terms(chunk.keywords.join(' '));
            const titleTerms = terms(chunk.title);
            const keywordMatches = [...promptTerms].filter((term) =>
                keywordTerms.has(term),
            ).length;
            const titleMatches = [...promptTerms].filter((term) =>
                titleTerms.has(term),
            ).length;

            return {
                ...chunk,
                score: keywordMatches + titleMatches * 2,
            };
        })
        .filter((chunk) => chunk.score > 0)
        .sort((left, right) => right.score - left.score)
        .slice(0, Math.max(1, limit));
}

export async function queryKnowledge(
    prompt: string,
): Promise<AiKnowledgeAnswer | null> {
    const route = routeAiPrompt(prompt);
    if (route.reason !== 'current_finance_unavailable') return null;

    const matches = retrieveKnowledge(prompt, 1);
    const match = matches[0];
    if (!match) return null;

    await wait(420);
    return {
        content: [
            'Fitur/halaman **finansial bulan ini** belum ada di informasi aplikasi Farmer IC yang saya punya, jadi saya belum bisa menarik atau menampilkan datanya otomatis.',
            '',
            'Biar saya bisa bantu rekap, Anda maunya yang mana?',
            '1) **Ringkasan pemasukan vs pengeluaran** bulan ini',
            '2) **Rincian biaya** (benih, pupuk, pestisida, tenaga kerja, irigasi, dll.)',
            '3) **Laba/Rugi per lahan/komoditas**',
            '',
            'Kalau Anda kirim **angka-angkanya** (atau **screenshot** halaman finansial/laporan yang Anda lihat), saya bisa bantu susun rekap + hitung total dan analisisnya.',
        ].join('\n'),
        source: match.source,
        chunkIds: [match.id],
    };
}

export type AiKnowledgeChunk = {
    id: string;
    title: string;
    content: string;
    keywords: string[];
    source: string;
};

/**
 * Small, versioned client knowledge index for facts that are intentionally
 * deterministic in the stakeholder prototype. Longer agronomy guidance still
 * belongs to the server LLM path until server-side retrieval is enabled.
 */
export const aiKnowledgeChunks: AiKnowledgeChunk[] = [
    {
        id: 'finance-current-unavailable-v1',
        title: 'Ketersediaan finansial bulan berjalan',
        content:
            'Data finansial bulan ini belum tersedia di informasi aplikasi Farmer IC yang dapat diakses Asisten Kebun. Asisten dapat membantu merekap angka atau screenshot yang dikirim pengguna, termasuk perbandingan pemasukan dan pengeluaran, rincian biaya, serta laba/rugi per lahan atau komoditas.',
        keywords: [
            'finansial',
            'keuangan',
            'pendapatan',
            'pemasukan',
            'pengeluaran',
            'biaya',
            'laba',
            'profit',
            'saldo',
            'bulan ini',
            'bulan sekarang',
            'periode ini',
        ],
        source: 'Knowledge base Farmer IC · ketersediaan data',
    },
    {
        id: 'assistant-scope-v1',
        title: 'Ruang lingkup Asisten Kebun',
        content:
            'Asisten Kebun membantu menjelaskan alur pemantauan pertanian, memahami data yang diberikan pengguna, dan memberi panduan umum pertanian dengan menyatakan asumsi serta keterbatasannya.',
        keywords: [
            'asisten',
            'farmer',
            'kebun',
            'pertanian',
            'data',
            'bantuan',
        ],
        source: 'Knowledge base Farmer IC · ruang lingkup aplikasi',
    },
];

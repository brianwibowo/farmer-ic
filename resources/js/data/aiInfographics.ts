export type AiInfographic = {
    id: string;
    title: string;
    description: string;
    bullets: string[];
    image: string;
    imageAlt: string;
    imageName: string;
};

/** Demo-only visual generation policy. The real provider can replace this service later. */
export const infographicGenerationConfig = {
    primaryModel: 'gpt-enterprise-high',
    fallbackModels: ['gpt-enterprise-standard', 'vision-fallback'],
    maxVisualsPerReply: 1,
};

const smartFarmingInfographic: AiInfographic = {
    id: 'smart-farming-overview',
    title: 'Panduan Kebun Pintar',
    description:
        'Infografis ringkas tentang bagian utama pemantauan kebun dan teknologi pertanian.',
    bullets: [
        'Pantau pertumbuhan tanaman dengan foto dan catatan lapangan.',
        'Gunakan data irigasi, tanah, dan cuaca sebagai bahan pertimbangan.',
        'Validasi temuan visual sebelum mengambil tindakan di lapangan.',
    ],
    image: '/images/ai-infographics/smart-farming-overview.png',
    imageAlt: 'Infografis Panduan Kebun Pintar',
    imageName: 'Infografis Panduan Kebun Pintar',
};

const agriculturalTopic =
    /\b(kebun|tanaman|padi|jagung|kedelai|irigasi|tanah|cuaca|pertanian|pupuk|hama)\b/i;
const visualIntent =
    /\b(gambar|foto|visual|infografis|diagram|ilustrasi|tunjukkan|lihat|seperti apa)\b/i;
const educationalIntent =
    /\b(apa itu|jelaskan|pengertian|mengenal|bagaimana cara)\b/i;

export function infographicForPrompt(prompt: string): AiInfographic | null {
    const value = prompt.trim();
    if (!value || !agriculturalTopic.test(value)) return null;
    if (!visualIntent.test(value) && !educationalIntent.test(value))
        return null;

    return smartFarmingInfographic;
}

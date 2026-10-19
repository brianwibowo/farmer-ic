export {
    routeAiPrompt,
    classifyAiPrompt,
    isDashboardDataPrompt,
    shouldRequestInfographic,
    normalizeAiIntentPrompt,
} from "@/services/aiIntentRouter";
export type {
    AiDomain,
    AiIntent,
    AiIntentRoute,
    AiPeriod,
    AiPromptClassification,
    AiPromptIntent,
} from "@/services/aiIntentRouter";

/** Keep a single user turn bounded before it reaches the model. */
export const MAX_AI_PROMPT_CHARS = 6000;

/**
 * Compact user input without changing its meaning. The API already owns the
 * conversation history, so sending repeated whitespace and very long pasted
 * transcripts only increases context cost. A visible ellipsis marks input
 * that was bounded so the model does not receive a misleading sentence end.
 */
export function compactAiPrompt(value: string): string {
    const compact = value
        .normalize("NFKC")
        .replace(/\r\n?/g, "\n")
        .split("\n")
        .map((line) => line.replace(/[ \t]+/g, " ").trim())
        .join("\n")
        .replace(/\n{3,}/g, "\n\n")
        .trim();

    if (compact.length <= MAX_AI_PROMPT_CHARS) return compact;
    return `${compact.slice(0, MAX_AI_PROMPT_CHARS - 1).trimEnd()}…`;
}

function removeControlChars(value: string): string {
    return Array.from(value)
        .filter((character) => {
            const code = character.charCodeAt(0);
            return code === 9 || code === 10 || code === 13 || code > 31;
        })
        .join("");
}

/** Remove transport noise while keeping the markdown structure for the safe renderer. */
export function normalizeAiResponse(value: string): string {
    const normalized = removeControlChars(value)
        .replace(/^\uFEFF/, "")
        .replace(/\r\n?/g, "\n")
        .replace(/^(?:assistant|ai)\s*:\s*/i, "")
        .replace(/\[DONE\]\s*$/i, "")
        .replace(/^\s*```(?:markdown|text)?\s*/i, "")
        .replace(/\s*```\s*$/i, "")
        .replace(/\\([*_`~>#-])/g, "$1")
        .replace(/[ \t]+\n/g, "\n")
        .replace(/\n{3,}/g, "\n\n")
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
        .replace(/\bhasil\s+maksimal\b/gi, "hasil yang sesuai kondisi")
        .replace(/\bdosis\s+maksimal\b/gi, "dosis sesuai label")
        .replace(/\b(?:paling\s+)?optimal(?:nya)?\b/gi, "sesuai kondisi")
        .replace(/\bterbaik\b/gi, "yang dapat dipertimbangkan")
        .replace(/\b(?:pasti|dijamin|terjamin)\b/gi, "berpotensi")
        .replace(/\bsempurna\b/gi, "lebih baik");
}

/** Convert a model response to plain Indonesian text for speech synthesis. */
export function toSpeechText(value: string): string {
    return normalizeAiResponse(value)
        .replace(/!\[([^\]]*)\]\([^)]*\)/g, "$1")
        .replace(/\[([^\]]+)\]\([^)]*\)/g, "$1")
        .replace(/^#{1,6}\s*/gm, "")
        .replace(/^>\s*/gm, "")
        .replace(/^\s*[-*+]\s+/gm, "")
        .replace(/^\s*\d+[.)]\s+/gm, "")
        .replace(/[*_`~]/g, "")
        .replace(/\s+/g, " ")
        .trim();
}

export function estimateTokens(value: string): number {
    const text = normalizeAiResponse(value)
        // Image URLs are transport metadata, not meaningful model text. They
        // can otherwise inflate the browser-side estimate by hundreds of
        // tokens when an infographic is attached to a response.
        .replace(/!\[[^\]]*\]\((?:https?:\/\/|\/)[^)]+\)/gi, "[gambar]")
        .replace(/https?:\/\/\S+/gi, "[tautan]")
        .replace(/\s+/g, " ")
        .trim();
    if (!text) return 0;

    // This is intentionally labelled an estimate in the report. Indonesian
    // words and markdown punctuation tokenize a little more densely than the
    // common four-characters-per-token rule, so 3.7 is a conservative proxy.
    return Math.max(1, Math.ceil(text.length / 3.7));
}

export function outOfScopeResponse(): string {
    return "Topik itu belum tersedia di Asisten Kebun. Saya fokus pada data dan aktivitas yang tersedia di aplikasi, perawatan tanaman, serta infografis pertanian. Coba tanyakan kondisi kebun, aktivitas terakhir, atau minta ringkasan visual.";
}

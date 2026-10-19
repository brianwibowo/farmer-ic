export type AiResponseValidation = {
    ok: boolean;
    content: string;
    issues: string[];
};

const forbiddenLeakPatterns = [
    /<\/?(?:system|assistant-policy|application-facts|published-team-knowledge)>/i,
    /DATA APLIKASI YANG HARUS DIPAKAI/i,
    /(?:system prompt|internal instructions|hidden metadata)/i,
];

function countImages(value: string): number {
    return Array.from(
        value.matchAll(
            /!\[[^\]]*\]\(((?:https?:\/\/|\/|data:image\/)[^)]+)\)/gi,
        ),
    ).length;
}

export function validateAiResponse(
    value: string,
    options: { maxChars?: number; allowImages?: boolean } = {},
): AiResponseValidation {
    const maxChars = options.maxChars ?? 16_000;
    const allowImages = options.allowImages ?? true;
    const content = value.trim();
    const issues: string[] = [];

    if (!content || !/[\p{L}\p{N}]/u.test(content)) {
        issues.push('empty_response');
    }
    if (content.length > maxChars) {
        issues.push('response_too_long');
    }
    if (forbiddenLeakPatterns.some((pattern) => pattern.test(content))) {
        issues.push('instruction_leak');
    }
    if (!allowImages && countImages(content) > 0) {
        issues.push('unexpected_image');
    }
    if (countImages(content) > 1) {
        issues.push('multiple_images');
    }

    return {
        ok: issues.length === 0,
        content,
        issues,
    };
}

import { normalizeAiResponse } from '@/services/aiChatService';
import type { ChatAttachment, ChatMessage, Conversation } from '@/types/aiChat';

export const MAX_ATTACHMENT_COUNT = 3;
export const MAX_ATTACHMENT_SIZE = 10 * 1024 * 1024;

const IMAGE_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
]);

export type StreamPayload = {
    delta?: string;
    deltaMode?: 'chunk' | 'snapshot';
    done?: boolean;
    conversationId?: string;
    message?: ChatMessage;
    error?: string;
};

export function id(prefix: string): string {
    return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

function stringValue(value: unknown, fallback: string): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : fallback;
}

function csrfToken(): string | null {
    const element = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    if (element?.content) return element.content;

    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

export function apiError(payload: unknown, fallback: string): string {
    if (typeof payload === 'string' && payload.trim()) return payload;
    if (payload && typeof payload === 'object') {
        const data = payload as { message?: unknown; error?: unknown };
        if (typeof data.message === 'string') return data.message;
        if (typeof data.error === 'string') return data.error;
    }
    return fallback;
}

export function parseConversation(value: unknown): Conversation | null {
    if (!value || typeof value !== 'object') return null;
    const item = value as Record<string, unknown>;
    const idValue = item.id ?? item.uuid;
    if (idValue === undefined || idValue === null) return null;
    return {
        id: stringValue(idValue, id('conversation')),
        title:
            typeof item.title === 'string' && item.title.trim()
                ? item.title
                : 'Percakapan baru',
        preview:
            typeof item.preview === 'string'
                ? item.preview
                : typeof item.last_message === 'string'
                  ? item.last_message
                  : undefined,
        updatedAt:
            typeof item.updated_at === 'string'
                ? item.updated_at
                : new Date().toISOString(),
    };
}

export function parseMessage(value: unknown): ChatMessage | null {
    if (!value || typeof value !== 'object') return null;
    const item = value as Record<string, unknown>;
    const role =
        item.role === 'user' || item.role === 'system'
            ? item.role
            : 'assistant';
    const text = item.content ?? item.text ?? item.message;
    const attachments = Array.isArray(item.attachments)
        ? (item.attachments as ChatAttachment[])
        : [];
    if (typeof text !== 'string' && attachments.length === 0) return null;
    return {
        id: stringValue(item.id, id('message')),
        role,
        content:
            role === 'assistant'
                ? normalizeAiResponse(typeof text === 'string' ? text : '')
                : typeof text === 'string'
                  ? text
                  : '',
        attachments,
        createdAt:
            typeof item.created_at === 'string'
                ? item.created_at
                : new Date().toISOString(),
        status: 'complete',
    };
}

export function headers(extra: Record<string, string> = {}): HeadersInit {
    const token = csrfToken();
    return {
        Accept: 'application/json, text/event-stream',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        ...extra,
    };
}

export function isSse(response: Response): boolean {
    return (
        response.headers.get('content-type')?.includes('text/event-stream') ??
        false
    );
}

export function extractDelta(payload: unknown): StreamPayload {
    if (payload === '[DONE]') return { done: true };
    if (typeof payload === 'string') return { delta: payload };
    if (!payload || typeof payload !== 'object') return {};
    const data = payload as Record<string, unknown>;
    const message = parseMessage(data.message ?? data.data);
    const hasChunk =
        typeof data.delta === 'string' || typeof data.token === 'string';
    const delta = data.delta ?? data.content ?? data.text ?? data.token;
    return {
        delta: typeof delta === 'string' ? delta : undefined,
        deltaMode:
            typeof delta === 'string' && !hasChunk ? 'snapshot' : 'chunk',
        done:
            data.done === true ||
            data.type === 'message.done' ||
            data.type === 'done',
        conversationId:
            data.conversation_id !== undefined
                ? stringValue(data.conversation_id, '')
                : undefined,
        message: message ?? undefined,
        error: typeof data.error === 'string' ? data.error : undefined,
    };
}

/** Merge both delta chunks and cumulative `content` snapshots safely. */
export function mergeResponseText(current: string, incoming: string): string {
    if (!incoming) return current;
    if (!current) return incoming;
    if (incoming === current) return current;
    if (incoming.startsWith(current)) return incoming;
    // A one-character delta can legitimately repeat (for example "aa").
    if (incoming.length > 1 && current.endsWith(incoming)) return current;

    const maxOverlap = Math.min(current.length, incoming.length);
    for (let size = maxOverlap; size > 0; size -= 1) {
        if (current.slice(-size) === incoming.slice(0, size)) {
            return current + incoming.slice(size);
        }
    }
    return current + incoming;
}

export async function readSse(
    response: Response,
    onData: (payload: unknown) => void,
): Promise<void> {
    if (!response.body) throw new Error('Stream tidak tersedia dari server.');
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const result = await reader.read();
        buffer += decoder.decode(result.value ?? new Uint8Array(), {
            stream: !result.done,
        });
        const blocks = buffer.split(/\r?\n\r?\n/);
        buffer = blocks.pop() ?? '';

        for (const block of blocks) {
            const data = block
                .split(/\r?\n/)
                .filter((line) => line.startsWith('data:'))
                .map((line) => line.slice(5).trimStart())
                .join('\n');
            if (!data) continue;
            try {
                onData(JSON.parse(data));
            } catch {
                onData(data);
            }
        }
        if (result.done) break;
    }

    const trailing = buffer
        .split(/\r?\n/)
        .filter((line) => line.startsWith('data:'))
        .map((line) => line.slice(5).trimStart())
        .join('\n');
    if (trailing) {
        try {
            onData(JSON.parse(trailing));
        } catch {
            onData(trailing);
        }
    }
}

export function validateAttachments(
    files: File[],
    existing = 0,
): string | null {
    if (existing + files.length > MAX_ATTACHMENT_COUNT) {
        return `Maksimal ${MAX_ATTACHMENT_COUNT} gambar per pesan.`;
    }
    for (const file of files) {
        if (!IMAGE_TYPES.has(file.type)) {
            return `${file.name} bukan format gambar yang didukung.`;
        }
        if (file.size > MAX_ATTACHMENT_SIZE) {
            return `${file.name} melebihi batas 10 MB.`;
        }
    }
    return null;
}

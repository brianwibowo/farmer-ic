import { computed, onBeforeUnmount, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Team } from '@/types/teams';

export type ChatRole = 'user' | 'assistant' | 'system';
export type MessageStatus = 'sending' | 'streaming' | 'complete' | 'error';

export type ChatAttachment = {
    id?: string | number;
    name: string;
    size: number;
    type: string;
    file?: File;
    url?: string;
    previewUrl?: string;
};

export type ChatMessage = {
    id: string;
    role: ChatRole;
    content: string;
    attachments: ChatAttachment[];
    createdAt: string;
    status: MessageStatus;
    error?: string;
};

export type Conversation = {
    id: string;
    title: string;
    updatedAt: string;
    preview?: string;
};

export type AiChatEndpoints = {
    base?: string;
    conversations?: string;
    messages?: string;
};

const MAX_ATTACHMENT_COUNT = 3;
const MAX_ATTACHMENT_SIZE = 10 * 1024 * 1024;
const IMAGE_TYPES = new Set([
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
]);

function id(prefix: string): string {
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
    if (element?.content) {
        return element.content;
    }

    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
}

function apiError(payload: unknown, fallback: string): string {
    if (typeof payload === 'string' && payload.trim()) {
        return payload;
    }
    if (payload && typeof payload === 'object') {
        const data = payload as { message?: unknown; error?: unknown };
        if (typeof data.message === 'string') return data.message;
        if (typeof data.error === 'string') return data.error;
    }
    return fallback;
}

function parseConversation(value: unknown): Conversation | null {
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

function parseMessage(value: unknown): ChatMessage | null {
    if (!value || typeof value !== 'object') return null;
    const item = value as Record<string, unknown>;
    const role =
        item.role === 'user' || item.role === 'system'
            ? item.role
            : 'assistant';
    const text = item.content ?? item.text ?? item.message;
    if (typeof text !== 'string') return null;
    return {
        id: stringValue(item.id, id('message')),
        role,
        content: text,
        attachments: Array.isArray(item.attachments)
            ? (item.attachments as ChatAttachment[])
            : [],
        createdAt:
            typeof item.created_at === 'string'
                ? item.created_at
                : new Date().toISOString(),
        status: 'complete',
    };
}

function headers(extra: Record<string, string> = {}): HeadersInit {
    const token = csrfToken();
    return {
        Accept: 'application/json, text/event-stream',
        'X-Requested-With': 'XMLHttpRequest',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        ...extra,
    };
}

function isSse(response: Response): boolean {
    return (
        response.headers.get('content-type')?.includes('text/event-stream') ??
        false
    );
}

function extractDelta(payload: unknown): {
    delta?: string;
    done?: boolean;
    conversationId?: string;
    message?: ChatMessage;
    error?: string;
} {
    if (payload === '[DONE]') return { done: true };
    if (typeof payload === 'string') return { delta: payload };
    if (!payload || typeof payload !== 'object') return {};
    const data = payload as Record<string, unknown>;
    const message = parseMessage(data.message ?? data.data);
    const delta = data.delta ?? data.content ?? data.text ?? data.token;
    return {
        delta: typeof delta === 'string' ? delta : undefined,
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

async function readSse(
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

export function useAiChat(endpoints: AiChatEndpoints = {}) {
    const page = usePage<{ currentTeam?: Team | null }>();
    const currentTeam = computed(() => page.props.currentTeam ?? null);
    const fallbackBase = computed(() => {
        const slug = currentTeam.value?.slug;
        return slug ? `/${encodeURIComponent(slug)}/ai` : '/ai';
    });

    const base = computed(() => endpoints.base ?? fallbackBase.value);
    const conversationsEndpoint = computed(
        () => endpoints.conversations ?? `${base.value}/conversations`,
    );
    const conversations = ref<Conversation[]>([]);
    const messages = ref<ChatMessage[]>([]);
    const conversationId = ref<string | null>(null);
    const isLoadingConversations = ref(false);
    const isSending = ref(false);
    const error = ref<string | null>(null);
    let controller: AbortController | null = null;

    const currentConversation = computed(() =>
        conversations.value.find((item) => item.id === conversationId.value),
    );

    async function loadConversations(): Promise<void> {
        isLoadingConversations.value = true;
        try {
            const response = await fetch(conversationsEndpoint.value, {
                headers: headers(),
            });
            if (!response.ok) return;
            const payload = (await response.json()) as unknown;
            const values = Array.isArray(payload)
                ? payload
                : payload &&
                    typeof payload === 'object' &&
                    Array.isArray((payload as { data?: unknown }).data)
                  ? (payload as { data: unknown[] }).data
                  : [];
            conversations.value = values
                .map(parseConversation)
                .filter((item): item is Conversation => item !== null);
        } catch {
            // The chat remains usable while the API is unavailable; history is optional.
        } finally {
            isLoadingConversations.value = false;
        }
    }

    async function loadConversation(idValue: string): Promise<void> {
        error.value = null;
        try {
            const response = await fetch(
                `${conversationsEndpoint.value}/${encodeURIComponent(idValue)}`,
                { headers: headers() },
            );
            if (!response.ok) throw new Error('Percakapan tidak dapat dimuat.');
            const payload = (await response.json()) as Record<string, unknown>;
            const values = Array.isArray(payload.messages)
                ? payload.messages
                : Array.isArray(payload.data)
                  ? payload.data
                  : [];
            messages.value = values
                .map(parseMessage)
                .filter((item): item is ChatMessage => item !== null);
            conversationId.value = idValue;
        } catch (cause) {
            error.value =
                cause instanceof Error
                    ? cause.message
                    : 'Percakapan tidak dapat dimuat.';
        }
    }

    function newConversation(): void {
        controller?.abort();
        conversationId.value = null;
        messages.value = [];
        error.value = null;
    }

    function addConversation(value: Conversation): void {
        conversations.value = [
            value,
            ...conversations.value.filter((item) => item.id !== value.id),
        ];
    }

    async function sendMessage(
        content: string,
        attachments: ChatAttachment[] = [],
    ): Promise<void> {
        const text = content.trim();
        if (!text && attachments.length === 0) return;
        if (isSending.value) return;

        error.value = null;
        isSending.value = true;
        const userMessage: ChatMessage = {
            id: id('user'),
            role: 'user',
            content: text,
            attachments,
            createdAt: new Date().toISOString(),
            status: 'complete',
        };
        const assistantMessage: ChatMessage = {
            id: id('assistant'),
            role: 'assistant',
            content: '',
            attachments: [],
            createdAt: new Date().toISOString(),
            status: 'streaming',
        };
        messages.value.push(userMessage, assistantMessage);
        controller = new AbortController();

        const endpoint =
            endpoints.messages ??
            (conversationId.value
                ? `${conversationsEndpoint.value}/${encodeURIComponent(conversationId.value)}/messages`
                : `${base.value}/messages`);
        const form = new FormData();
        form.append('message', text);
        if (conversationId.value)
            form.append('conversation_id', conversationId.value);
        for (const attachment of attachments) {
            if (attachment.file)
                form.append('attachments[]', attachment.file, attachment.name);
        }

        try {
            let receivedConversation: Conversation | null = null;
            const response = await fetch(endpoint, {
                method: 'POST',
                body: form,
                headers: headers(),
                signal: controller.signal,
            });
            if (!response.ok) {
                let payload: unknown = null;
                try {
                    payload = await response.json();
                } catch {
                    /* fall through to status text */
                }
                throw new Error(
                    apiError(payload, `Permintaan gagal (${response.status}).`),
                );
            }

            const apply = (payload: unknown): void => {
                const parsed = extractDelta(payload);
                if (payload && typeof payload === 'object') {
                    const value = payload as Record<string, unknown>;
                    receivedConversation =
                        parseConversation(value.conversation) ??
                        receivedConversation;
                }
                if (parsed.conversationId)
                    conversationId.value = parsed.conversationId;
                if (parsed.message && !assistantMessage.content) {
                    assistantMessage.content = parsed.message.content;
                }
                if (parsed.delta) assistantMessage.content += parsed.delta;
                if (parsed.error) {
                    assistantMessage.error = parsed.error;
                    assistantMessage.status = 'error';
                }
                if (parsed.done) assistantMessage.status = 'complete';
            };

            if (isSse(response)) {
                await readSse(response, apply);
            } else {
                const payload = (await response.json()) as Record<
                    string,
                    unknown
                >;
                const streamUrl =
                    typeof payload.stream_url === 'string'
                        ? payload.stream_url
                        : typeof payload.streamUrl === 'string'
                          ? payload.streamUrl
                          : null;
                if (streamUrl) {
                    const streamResponse = await fetch(streamUrl, {
                        headers: headers(),
                        signal: controller.signal,
                    });
                    if (!streamResponse.ok)
                        throw new Error('Stream jawaban tidak tersedia.');
                    await readSse(streamResponse, apply);
                } else {
                    apply(payload);
                    const assistant = parseMessage(
                        payload.message ?? payload.data ?? payload,
                    );
                    if (assistant?.content && !assistantMessage.content)
                        assistantMessage.content = assistant.content;
                }
            }

            if (assistantMessage.status === 'streaming') {
                assistantMessage.status = assistantMessage.content
                    ? 'complete'
                    : 'error';
            }
            if (assistantMessage.status === 'error' && !assistantMessage.error)
                assistantMessage.error = 'Jawaban kosong dari server.';
            if (receivedConversation) {
                addConversation(receivedConversation);
            } else if (conversationId.value) {
                addConversation({
                    id: conversationId.value,
                    title:
                        currentConversation.value?.title ??
                        (text.slice(0, 48) || 'Percakapan baru'),
                    preview: text || 'Lampiran gambar',
                    updatedAt: new Date().toISOString(),
                });
            }
        } catch (cause) {
            if (cause instanceof DOMException && cause.name === 'AbortError')
                return;
            assistantMessage.status = 'error';
            assistantMessage.error =
                cause instanceof Error
                    ? cause.message
                    : 'Terjadi kesalahan saat menghubungi AI.';
            error.value = assistantMessage.error;
        } finally {
            isSending.value = false;
            controller = null;
        }
    }

    function stopStreaming(): void {
        controller?.abort();
        controller = null;
        isSending.value = false;
        const current = messages.value.at(-1);
        if (current?.role === 'assistant' && current.status === 'streaming') {
            current.status = current.content ? 'complete' : 'error';
        }
    }

    onBeforeUnmount(() => controller?.abort());

    return {
        conversations,
        messages,
        conversationId,
        currentConversation,
        isLoadingConversations,
        isSending,
        error,
        loadConversations,
        loadConversation,
        newConversation,
        sendMessage,
        stopStreaming,
    };
}

export { MAX_ATTACHMENT_COUNT, MAX_ATTACHMENT_SIZE };

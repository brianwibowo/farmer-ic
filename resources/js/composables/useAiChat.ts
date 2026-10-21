import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { Team } from '@/types/teams';
import {
    compactAiPrompt,
    hasUsableAiResponse,
    normalizeAiResponse,
} from '@/services/aiChatService';
import {
    apiError,
    extractDelta,
    headers,
    id,
    isSse,
    mergeResponseText,
    parseConversation,
    parseMessage,
    readSse,
    validateAttachments,
    MAX_ATTACHMENT_COUNT,
    MAX_ATTACHMENT_SIZE,
} from '@/services/aiChatProtocol';
import { validateAiResponse } from '@/services/aiResponseValidator';
import type {
    AiChatEndpoints,
    AiRequestMetrics,
    ChatAttachment,
    ChatMessage,
    Conversation,
} from '@/types/aiChat';
export type {
    AiChatEndpoints,
    AiRequestMetrics,
    ChatAttachment,
    ChatMessage,
    ChatRole,
    Conversation,
    MessageStatus,
} from '@/types/aiChat';

type ActiveRequest = {
    id: number;
    controller: AbortController;
    startedAt: number;
    firstDeltaAt?: number;
    didTimeout: boolean;
    reconciled: boolean;
    assistantMessage: ChatMessage;
    cancelledByUser: boolean;
    serverProgress?: string;
};

export { MAX_ATTACHMENT_COUNT, MAX_ATTACHMENT_SIZE, validateAttachments };

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
    const lastRequestMetrics = ref<AiRequestMetrics | null>(null);
    let requestSequence = 0;
    let activeRequest: ActiveRequest | null = null;
    let conversationLoadSequence = 0;

    const currentConversation = computed(() =>
        conversations.value.find((item) => item.id === conversationId.value),
    );

    // A proxy or upstream stream can close without delivering a final SSE
    // event. Do not leave the composer idle while an assistant bubble still
    // claims to be streaming.
    watch(isSending, (sending) => {
        if (sending) return;
        for (const message of messages.value) {
            if (message.status !== 'streaming') continue;
            message.status = 'error';
            message.error =
                message.error || 'Koneksi ke layanan AI terputus. Coba lagi.';
        }
    });

    function isActiveRequest(requestId: number): boolean {
        return activeRequest?.id === requestId;
    }

    function validatedResponse(value: string) {
        return validateAiResponse(normalizeAiResponse(value));
    }

    function cancelActiveRequest(cancelledByUser = false): void {
        const request = activeRequest;
        if (!request) return;
        request.cancelledByUser = cancelledByUser;
        if (
            cancelledByUser &&
            request.assistantMessage.status === 'streaming'
        ) {
            request.assistantMessage.status = request.assistantMessage.content
                ? 'complete'
                : 'cancelled';
            if (!request.assistantMessage.content) {
                request.assistantMessage.error = 'Jawaban dihentikan.';
            }
        }
        requestSequence += 1;
        activeRequest = null;
        request.controller.abort();
        isSending.value = false;
    }

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
        const loadId = ++conversationLoadSequence;
        cancelActiveRequest();
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
            if (loadId !== conversationLoadSequence) return;
            messages.value = values
                .map(parseMessage)
                .filter((item): item is ChatMessage => item !== null);
            conversationId.value = idValue;
        } catch (cause) {
            if (loadId !== conversationLoadSequence) return;
            error.value =
                cause instanceof Error
                    ? cause.message
                    : 'Percakapan tidak dapat dimuat.';
        }
    }

    function newConversation(): void {
        conversationLoadSequence += 1;
        cancelActiveRequest();
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
        isInfographic = false,
    ): Promise<ChatMessage | null> {
        const text = compactAiPrompt(content);
        if (!text && attachments.length === 0) return null;
        if (isSending.value) return null;

        error.value = null;
        isSending.value = true;
        const startedAt = performance.now();
        lastRequestMetrics.value = null;
        const userMessage: ChatMessage = {
            id: id('user'),
            role: 'user',
            content: text,
            attachments,
            createdAt: new Date().toISOString(),
            status: 'complete',
        };
        // Streaming callbacks must mutate the reactive proxy, not the original
        // plain object inserted into the array. Raw mutations bypass Vue's
        // render tracking and leave the loading bubble visible until refresh.
        const assistantMessage = reactive<ChatMessage>({
            id: id('assistant'),
            role: 'assistant',
            content: '',
            attachments: [],
            createdAt: new Date().toISOString(),
            status: 'streaming',
            presentation: isInfographic ? 'infographic' : 'text',
            progress: isInfographic
                ? 'Menyiapkan infografis…'
                : 'Menghubungkan ke AI…',
        });
        messages.value.push(userMessage, assistantMessage);
        const requestId = ++requestSequence;
        const requestController = new AbortController();
        const request: ActiveRequest = {
            id: requestId,
            controller: requestController,
            startedAt,
            didTimeout: false,
            reconciled: false,
            assistantMessage,
            cancelledByUser: false,
        };
        activeRequest = request;
        const progressTimers = [
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content &&
                    !request.serverProgress
                ) {
                    assistantMessage.progress = isInfographic
                        ? 'Masih menyiapkan infografis…'
                        : 'Membaca konteks kebun…';
                }
            }, 1_800),
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content &&
                    !request.serverProgress
                ) {
                    assistantMessage.progress = 'Menganalisis data lapangan…';
                }
            }, 6_000),
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content &&
                    !request.serverProgress
                ) {
                    assistantMessage.progress =
                        'Menyusun kalkulasi & rekomendasi…';
                }
            }, 15_000),
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content &&
                    !request.serverProgress
                ) {
                    assistantMessage.progress =
                        'Model sedang menyusun jawaban berbasis konteks…';
                }
            }, 20_000),
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content
                ) {
                    assistantMessage.progress =
                        'Hampir selesai, memvalidasi respon…';
                }
            }, 30_000),
            window.setTimeout(() => {
                if (
                    isActiveRequest(requestId) &&
                    assistantMessage.status === 'streaming' &&
                    !assistantMessage.content
                ) {
                    assistantMessage.progress =
                        'Menunggu finalisasi respon dari server…';
                }
            }, 45_000),
        ];
        // A slow model can keep the connection open for tens of seconds
        // before its first token. Keep the status moving without claiming a
        // fake percentage: once generation is underway, show elapsed time so
        // the user can distinguish a working request from a stalled one.
        const progressTicker = window.setInterval(() => {
            if (
                !isActiveRequest(requestId) ||
                assistantMessage.status !== 'streaming'
            ) {
                return;
            }
            const elapsedSeconds = Math.floor(
                (performance.now() - startedAt) / 1000,
            );
            if (
                request.serverProgress &&
                !assistantMessage.content &&
                elapsedSeconds >= 30
            ) {
                assistantMessage.progress = `${request.serverProgress} · ${elapsedSeconds} detik`;
            } else if (assistantMessage.content && elapsedSeconds >= 20) {
                assistantMessage.progress = `AI sedang menulis… ${elapsedSeconds} detik`;
            } else if (!assistantMessage.content && elapsedSeconds >= 30) {
                assistantMessage.progress = `Masih menyusun jawaban… ${elapsedSeconds} detik`;
            }
        }, 2_000);
        const requestTimeout = window.setTimeout(() => {
            request.didTimeout = true;
            requestController.abort();
        }, 60_000);

        const reconcilePersistedReply = async (
            ignoreSignal = false,
        ): Promise<boolean> => {
            if (!isActiveRequest(requestId)) return false;
            try {
                let currentId = conversationId.value;
                // Some reverse proxies buffer the first SSE event. In that
                // case message.start has not populated conversationId yet;
                // resolve the newest conversation from the normal history API.
                if (!currentId) {
                    const conversationsResponse = await fetch(
                        conversationsEndpoint.value,
                        {
                            headers: headers(),
                            ...(ignoreSignal
                                ? {}
                                : { signal: requestController.signal }),
                        },
                    );
                    if (!conversationsResponse.ok) return false;
                    const conversationPayload =
                        (await conversationsResponse.json()) as unknown;
                    const values = Array.isArray(conversationPayload)
                        ? conversationPayload
                        : conversationPayload &&
                            typeof conversationPayload === 'object' &&
                            Array.isArray(
                                (conversationPayload as { data?: unknown })
                                    .data,
                            )
                          ? (conversationPayload as { data: unknown[] }).data
                          : [];
                    const latest = values
                        .map(parseConversation)
                        .find((item): item is Conversation => item !== null);
                    currentId = latest?.id ?? null;
                    if (currentId) conversationId.value = currentId;
                }
                if (!currentId || !isActiveRequest(requestId)) return false;
                const persistedResponse = await fetch(
                    `${conversationsEndpoint.value}/${encodeURIComponent(currentId)}`,
                    {
                        headers: headers(),
                        ...(ignoreSignal
                            ? {}
                            : { signal: requestController.signal }),
                    },
                );
                if (!persistedResponse.ok) return false;
                const payload = (await persistedResponse.json()) as Record<
                    string,
                    unknown
                >;
                const values = Array.isArray(payload.messages)
                    ? payload.messages
                    : Array.isArray(payload.data)
                      ? payload.data
                      : [];
                const persistedMessages = values
                    .map(parseMessage)
                    .filter((item): item is ChatMessage => item !== null);
                const persistedUserIndex = persistedMessages
                    .map(
                        (item) => item.role === 'user' && item.content === text,
                    )
                    .lastIndexOf(true);
                const persistedReply = persistedMessages
                    .slice(persistedUserIndex + 1)
                    .reverse()
                    .find(
                        (item) =>
                            item.role === 'assistant' &&
                            item.content.trim() !== '',
                    );
                if (!persistedReply || !isActiveRequest(requestId)) return false;
                const validation = validatedResponse(persistedReply.content);
                if (!validation.ok) return false;
                assistantMessage.content = validation.content;
                assistantMessage.attachments = persistedReply.attachments;
                assistantMessage.status = 'complete';
                assistantMessage.progress = undefined;
                request.reconciled = true;
                if (!ignoreSignal) {
                    request.controller.abort();
                }
                return true;
            } catch {
                return false;
            }
        };
        const endpoint =
            endpoints.messages ??
            (conversationId.value
                ? `${conversationsEndpoint.value}/${encodeURIComponent(conversationId.value)}/messages`
                : `${base.value}/messages`);
        const form = new FormData();
        form.append('message', text);
        if (conversationId.value)
            form.append('conversation_id', conversationId.value);
        if (isInfographic) form.append('is_infographic', '1');
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
                signal: requestController.signal,
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
                if (!isActiveRequest(requestId)) return;
                const parsed = extractDelta(payload);
                if (payload && typeof payload === 'object') {
                    const value = payload as Record<string, unknown>;
                    receivedConversation =
                        parseConversation(value.conversation) ??
                        receivedConversation;
                }
                if (parsed.conversationId)
                    conversationId.value = parsed.conversationId;
                // message.start is emitted after retrieval and before the
                // provider call. Preserve its trace immediately and move the
                // copy from retrieval to generation so a long model wait is
                // explained accurately in the assistant bubble.
                if (parsed.retrieval && !assistantMessage.content) {
                    request.serverProgress = isInfographic
                        ? 'Konteks siap. Menyiapkan infografis…'
                        : 'Konteks siap. Model sedang menyusun jawaban…';
                    assistantMessage.metadata = {
                        ...assistantMessage.metadata,
                        ...(parsed.route ? { route: parsed.route } : {}),
                        retrieval: parsed.retrieval,
                    };
                    assistantMessage.progress = request.serverProgress;
                }
                if (parsed.message) {
                    const validation = validatedResponse(
                        parsed.message.content,
                    );
                    assistantMessage.content = validation.content;
                    assistantMessage.progress = undefined;
                    assistantMessage.metadata = parsed.message.metadata;
                    if (!validation.ok) {
                        assistantMessage.status = 'error';
                        assistantMessage.error =
                            'Jawaban AI tidak lolos pemeriksaan keamanan dan format.';
                    }
                    if (parsed.message.attachments.length > 0) {
                        assistantMessage.attachments =
                            parsed.message.attachments;
                    }
                } else if (parsed.progress) {
                    request.serverProgress = parsed.progress;
                    if (!assistantMessage.content) {
                        assistantMessage.progress = parsed.progress;
                    }
                } else if (parsed.delta) {
                    // Image generation sends a synthetic progress sentence
                    // before the provider response. Keep it in the UI's
                    // loading state instead of persisting it as answer text.
                    if (/^🎨\s*Sedang merancang/i.test(parsed.delta.trim())) {
                        assistantMessage.progress =
                            'Sedang menyiapkan infografis…';
                        return;
                    }
                    if (request.firstDeltaAt === undefined)
                        request.firstDeltaAt = performance.now();
                    assistantMessage.progress = 'AI sedang menulis…';
                    assistantMessage.content =
                        parsed.deltaMode === 'snapshot'
                            ? mergeResponseText(
                                  assistantMessage.content,
                                  parsed.delta,
                              )
                            : assistantMessage.content + parsed.delta;
                }
                if (parsed.error) {
                    assistantMessage.error = parsed.error;
                    assistantMessage.status = 'error';
                    assistantMessage.progress = undefined;
                }
                if (parsed.done && assistantMessage.status !== 'error') {
                    const validation = validatedResponse(
                        assistantMessage.content,
                    );
                    assistantMessage.content = validation.content;
                    assistantMessage.status =
                        validation.ok &&
                        (hasUsableAiResponse(assistantMessage.content) ||
                            assistantMessage.attachments.length > 0)
                            ? 'complete'
                            : 'streaming';
                    if (!validation.ok) {
                        assistantMessage.status = 'error';
                        assistantMessage.error =
                            'Jawaban AI tidak lolos pemeriksaan keamanan dan format.';
                    }
                    if (assistantMessage.status === 'complete') {
                        assistantMessage.progress = undefined;
                    }
                }
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
                        signal: requestController.signal,
                    });
                    if (!streamResponse.ok)
                        throw new Error('Stream jawaban tidak tersedia.');
                    await readSse(streamResponse, apply);
                } else {
                    apply(payload);
                    const assistant = parseMessage(
                        payload.message ?? payload.data ?? payload,
                    );
                    if (
                        isActiveRequest(requestId) &&
                        assistant?.content &&
                        !assistantMessage.content
                    )
                        assistantMessage.content = assistant.content;
                }
            }

            if (!isActiveRequest(requestId)) return null;
            if (assistantMessage.status === 'streaming') {
                const validation = validatedResponse(assistantMessage.content);
                assistantMessage.content = validation.content;
                assistantMessage.status =
                    validation.ok &&
                    (hasUsableAiResponse(assistantMessage.content) ||
                        assistantMessage.attachments.length > 0)
                        ? 'complete'
                        : 'error';
                if (!validation.ok) {
                    assistantMessage.error =
                        'Jawaban AI tidak lolos pemeriksaan keamanan dan format.';
                }
            }
            if (assistantMessage.status === 'error' && !assistantMessage.error)
                assistantMessage.error = 'Jawaban kosong dari server.';
            if (assistantMessage.status === 'error')
                assistantMessage.progress = undefined;
            lastRequestMetrics.value = {
                latencyMs: Math.round(performance.now() - startedAt),
                firstTokenMs:
                    request.firstDeltaAt === undefined
                        ? undefined
                        : Math.round(request.firstDeltaAt - startedAt),
                status:
                    assistantMessage.status === 'error' ? 'error' : 'complete',
            };
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
            return assistantMessage;
        } catch (cause) {
            if (cause instanceof DOMException && cause.name === 'AbortError') {
                if (isActiveRequest(requestId) && request.reconciled) {
                    assistantMessage.status = 'complete';
                } else if (isActiveRequest(requestId) && request.didTimeout) {
                    assistantMessage.progress =
                        'Menyinkronkan jawaban dari server…';
                    const recovered = await reconcilePersistedReply(true);
                    if (recovered) {
                        assistantMessage.status = 'complete';
                        assistantMessage.progress = undefined;
                        return assistantMessage;
                    }

                    assistantMessage.status = 'error';
                    assistantMessage.progress = undefined;
                    assistantMessage.error =
                        'Jawaban memerlukan waktu lebih lama dari biasanya. Coba kirim ulang atau segarkan percakapan.';
                    error.value = assistantMessage.error;
                    lastRequestMetrics.value = {
                        latencyMs: Math.round(performance.now() - startedAt),
                        firstTokenMs:
                            request.firstDeltaAt === undefined
                                ? undefined
                                : Math.round(request.firstDeltaAt - startedAt),
                        status: 'error',
                    };
                } else if (isActiveRequest(requestId)) {
                    if (request.cancelledByUser) {
                        assistantMessage.status = assistantMessage.content
                            ? 'complete'
                            : 'cancelled';
                        assistantMessage.progress = undefined;
                        if (!assistantMessage.content) {
                            assistantMessage.error = 'Jawaban dihentikan.';
                        }
                    } else {
                        assistantMessage.status = 'error';
                        assistantMessage.progress = undefined;
                        assistantMessage.error =
                            'Koneksi ke layanan AI terputus. Coba lagi.';
                        error.value = assistantMessage.error;
                    }
                    lastRequestMetrics.value = {
                        latencyMs: Math.round(performance.now() - startedAt),
                        firstTokenMs:
                            request.firstDeltaAt === undefined
                                ? undefined
                                : Math.round(request.firstDeltaAt - startedAt),
                        status: request.cancelledByUser ? 'aborted' : 'error',
                    };
                }
                return assistantMessage;
            }
            if (isActiveRequest(requestId)) {
                assistantMessage.status = 'error';
                assistantMessage.progress = undefined;
                assistantMessage.error =
                    cause instanceof Error
                        ? cause.message
                        : 'Terjadi kesalahan saat menghubungi AI.';
                error.value = assistantMessage.error;
                lastRequestMetrics.value = {
                    latencyMs: Math.round(performance.now() - startedAt),
                    firstTokenMs:
                        request.firstDeltaAt === undefined
                            ? undefined
                            : Math.round(request.firstDeltaAt - startedAt),
                    status: 'error',
                };
                return assistantMessage;
            }
            return null;
        } finally {
            window.clearTimeout(requestTimeout);
            progressTimers.forEach((timer) => window.clearTimeout(timer));
            window.clearInterval(progressTicker);
            if (isActiveRequest(requestId)) {
                if (request.assistantMessage.status === 'streaming') {
                    request.assistantMessage.status = request.cancelledByUser
                        ? request.assistantMessage.content
                            ? 'complete'
                            : 'cancelled'
                        : 'error';
                    request.assistantMessage.progress = undefined;
                    if (!request.assistantMessage.content) {
                        request.assistantMessage.error = request.cancelledByUser
                            ? 'Jawaban dihentikan.'
                            : 'Koneksi ke layanan AI terputus. Coba lagi.';
                    }
                }
                activeRequest = null;
                isSending.value = false;
            }
        }
    }

    function stopStreaming(): void {
        const request = activeRequest;
        if (!request) return;
        request.cancelledByUser = true;
        const current = request.assistantMessage;
        if (current.status === 'streaming') {
            current.status = current.content ? 'complete' : 'cancelled';
            current.progress = undefined;
            if (!current.content) current.error = 'Jawaban dihentikan.';
        }
        lastRequestMetrics.value = {
            latencyMs: Math.round(performance.now() - request.startedAt),
            firstTokenMs:
                request.firstDeltaAt === undefined
                    ? undefined
                    : Math.round(request.firstDeltaAt - request.startedAt),
            status: 'aborted',
        };
        cancelActiveRequest(true);
    }

    onBeforeUnmount(() => cancelActiveRequest());

    return {
        conversations,
        messages,
        conversationId,
        currentConversation,
        isLoadingConversations,
        isSending,
        error,
        lastRequestMetrics,
        loadConversations,
        loadConversation,
        newConversation,
        sendMessage,
        stopStreaming,
    };
}

export type ChatRole = 'user' | 'assistant' | 'system';

export type MessageStatus =
    | 'sending'
    | 'streaming'
    | 'complete'
    | 'cancelled'
    | 'error';

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
    progress?: string;
    presentation?: 'text' | 'infographic';
    metadata?: Record<string, unknown>;
    error?: string;
    feedback?: 'up' | 'down';
};

export type AiRequestMetrics = {
    latencyMs: number;
    firstTokenMs?: number;
    status: 'complete' | 'error' | 'aborted';
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

import { describe, expect, it } from 'vitest';
import { extractDelta, parseMessage } from './aiChatProtocol';

describe('chat message presentation', () => {
    it('keeps retrieval progress visible before the first response delta', () => {
        expect(
            extractDelta({ progress: 'Mencari konteks yang relevan…' }).progress,
        ).toBe('Mencari konteks yang relevan…');
    });
    it('exposes retrieval trace from the stream start event', () => {
        expect(
            extractDelta({
                route: { intent: 'llm', domain: 'agronomy' },
                retrieval: {
                    count: 4,
                    retriever: 'qdrant',
                    evidence_ids: ['guide:chunk:0'],
                },
            }),
        ).toMatchObject({
            route: { intent: 'llm', domain: 'agronomy' },
            retrieval: {
                count: 4,
                retriever: 'qdrant',
                evidence_ids: ['guide:chunk:0'],
            },
        });
    });
    it('keeps structured data summaries in text mode', () => {
        const message = parseMessage({
            id: 'data-1',
            role: 'assistant',
            content: '### Kondisi Kebun\n1. Kebun A: Sehat',
            attachments: [],
        });

        expect(message?.presentation).toBe('text');
    });

    it('marks generated image attachments as infographic mode', () => {
        const message = parseMessage({
            id: 'visual-1',
            role: 'assistant',
            content: '![Infografis](/storage/ai/infographics/demo.png)',
            attachments: [
                {
                    id: 'asset-1',
                    name: 'demo.png',
                    size: 100,
                    type: 'image/png',
                    url: '/storage/ai/infographics/demo.png',
                },
            ],
        });

        expect(message?.presentation).toBe('infographic');
    });

    it('preserves server route and retrieval trace metadata', () => {
        const message = parseMessage({
            id: 'trace-1',
            role: 'assistant',
            content: 'Jawaban dengan sumber [S1].',
            metadata: {
                route: { intent: 'llm', domain: 'agronomy' },
                retrieval: { evidence_ids: ['doc-1:chunk:0'] },
            },
        });

        expect(message?.metadata).toMatchObject({
            route: { intent: 'llm', domain: 'agronomy' },
        });
    });
});

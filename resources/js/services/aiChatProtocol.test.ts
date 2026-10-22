import { describe, expect, it } from 'vitest';
import { extractDelta, parseMessage, sortChatMessages } from './aiChatProtocol';

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

    it('removes repeated markdown escaping when loading persisted replies', () => {
        const message = parseMessage({
            id: 'escaped-1',
            role: 'assistant',
            content: [
                '\\### 💰 Keuangan Maret 2026\\',
                '\\',
                '\\- \\*\\*Pemasukan: Rp16.000.000\\*\\*\\',
                '\\| Tanggal \\| Transaksi \\| Nominal \\|\\',
                '\\|---\\|---\\|---: \\|\\',
                '\\| 14 Maret \\| Penjualan \\| +Rp16.000.000 \\|',
                '',
                '![Lahan dan tanaman jagung]\\(/images/farmer/corn-field.jpeg\\)\\',
            ].join('\n'),
        });

        expect(message?.content).toContain('### 💰 Keuangan Maret 2026');
        expect(message?.content).toContain('- **Pemasukan: Rp16.000.000**');
        expect(message?.content).toContain('| Tanggal | Transaksi | Nominal |');
        expect(message?.content).toContain('![Lahan dan tanaman jagung](/images/farmer/corn-field.jpeg)');
        expect(message?.content).not.toContain('\\');
    });

    it('restores persisted messages to chronological order', () => {
        const messages = [
            parseMessage({
                id: '2',
                role: 'assistant',
                content: 'jawaban',
                created_at: '2026-03-10T10:01:00.000Z',
            }),
            parseMessage({
                id: '1',
                role: 'user',
                content: 'pertanyaan',
                created_at: '2026-03-10T10:00:00.000Z',
            }),
        ].filter((item): item is NonNullable<typeof item> => item !== null);

        expect(sortChatMessages(messages).map((item) => item.id)).toEqual(['1', '2']);
    });
});

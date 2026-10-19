import { describe, expect, it } from 'vitest';
import { parseMessage } from './aiChatProtocol';

describe('chat message presentation', () => {
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
});

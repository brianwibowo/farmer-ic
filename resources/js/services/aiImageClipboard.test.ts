import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    canCopyAiImage,
    copyAiImage,
    copyAiImageLink,
} from './aiImageClipboard';

class TestClipboardItem {
    static supports(type: string): boolean {
        return type === 'image/png';
    }
    readonly types: string[];
    constructor(private readonly data: Record<string, Blob | Promise<Blob>>) {
        this.types = Object.keys(data);
    }
    async getType(type: string): Promise<Blob> {
        return this.data[type]!;
    }
}

describe('AI image clipboard', () => {
    const writeText = vi.fn();
    const write = vi.fn();
    const fetchImage = vi.fn();

    beforeEach(() => {
        vi.resetAllMocks();
        write.mockImplementation(async (items: TestClipboardItem[]) => {
            await items[0]!.getType('image/png');
        });
        writeText.mockResolvedValue(undefined);
        vi.stubGlobal('navigator', { clipboard: { write, writeText } });
        vi.stubGlobal('window', {
            location: { href: 'http://localhost:8000/farm-demo/ai' },
        });
        vi.stubGlobal('ClipboardItem', TestClipboardItem);
        vi.stubGlobal('fetch', fetchImage);
    });
    afterEach(() => vi.unstubAllGlobals());

    it('copies PNG bitmap bytes instead of markdown or its URL', async () => {
        const png = new Blob(['png-bytes'], { type: 'image/png' });
        fetchImage.mockResolvedValue(new Response(png));

        await expect(
            copyAiImage('/storage/ai/infographics/qa.png'),
        ).resolves.toMatchObject({ kind: 'image' });

        expect(write).toHaveBeenCalledOnce();
        const item = write.mock.calls[0]![0][0] as TestClipboardItem;
        expect(item.types).toEqual(['image/png']);
        expect(await (await item.getType('image/png')).text()).toBe(
            'png-bytes',
        );
        expect(writeText).not.toHaveBeenCalled();
    });

    it('converts SVG into PNG on canvas before copying', async () => {
        fetchImage.mockResolvedValue(
            new Response(new Blob(['<svg />'], { type: 'image/svg+xml' })),
        );
        const revoke = vi.fn();
        vi.stubGlobal(
            'URL',
            Object.assign(class extends URL {}, {
                createObjectURL: vi.fn(() => 'blob:svg'),
                revokeObjectURL: revoke,
            }),
        );
        const drawImage = vi.fn();
        const toBlob = vi.fn((callback: BlobCallback) =>
            callback(new Blob(['converted-png'], { type: 'image/png' })),
        );
        const canvas = {
            width: 0,
            height: 0,
            getContext: () => ({ drawImage }),
            toBlob,
        };
        vi.stubGlobal('document', { createElement: vi.fn(() => canvas) });
        vi.stubGlobal(
            'Image',
            class {
                naturalWidth = 640;
                naturalHeight = 480;
                onload: (() => void) | null = null;
                onerror: (() => void) | null = null;
                set src(_value: string) {
                    this.onload?.();
                }
            },
        );

        await expect(copyAiImage('/qa.svg')).resolves.toMatchObject({
            kind: 'image',
        });
        expect(canvas.width).toBe(640);
        expect(canvas.height).toBe(480);
        expect(drawImage).toHaveBeenCalledOnce();
        expect(toBlob).toHaveBeenCalledWith(expect.any(Function), 'image/png');
        expect(revoke).toHaveBeenCalledWith('blob:svg');
    });

    it('copies an explicit absolute image link when bitmap clipboard is unsupported', async () => {
        vi.stubGlobal('ClipboardItem', undefined);

        expect(canCopyAiImage()).toBe(false);
        await expect(copyAiImage('/storage/qa.png')).resolves.toEqual({
            kind: 'link',
            message: 'Tautan gambar disalin.',
        });
        expect(writeText).toHaveBeenCalledWith(
            'http://localhost:8000/storage/qa.png',
        );
        expect(fetchImage).not.toHaveBeenCalled();
        expect(write).not.toHaveBeenCalled();
    });

    it('reports a failed image download without silently copying different content', async () => {
        fetchImage.mockResolvedValue(
            new Response('not found', { status: 404 }),
        );

        await expect(copyAiImage('/missing.png')).rejects.toThrow(
            'Gambar tidak dapat diambil',
        );
        expect(writeText).not.toHaveBeenCalled();
    });

    it('keeps an explicit image-copy permission error visible for the UI', async () => {
        fetchImage.mockResolvedValue(
            new Response(new Blob(['png'], { type: 'image/png' })),
        );
        write.mockRejectedValue(
            new DOMException('Permission denied', 'NotAllowedError'),
        );

        await expect(copyAiImage('/qa.png')).rejects.toThrow(
            'Izin clipboard ditolak',
        );
        expect(writeText).not.toHaveBeenCalled();
    });

    it('reports when neither image nor link clipboard access exists', async () => {
        vi.stubGlobal('navigator', {});

        await expect(copyAiImageLink('/qa.png')).rejects.toThrow(
            'Clipboard tidak tersedia',
        );
        expect(fetchImage).not.toHaveBeenCalled();
    });
});

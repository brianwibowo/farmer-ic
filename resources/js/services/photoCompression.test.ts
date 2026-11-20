import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderToString } from '@vue/server-renderer';
import { defineComponent, h, ref } from 'vue';
import { usePhotoCompression } from '@/composables/usePhotoCompression';
import { compressPhoto, validatePhoto } from './photoCompression';

const close = vi.fn();
const drawImage = vi.fn();
let bitmap: { width: number; height: number; close: typeof close };
let canvas: {
    width: number;
    height: number;
    getContext: ReturnType<typeof vi.fn>;
    toBlob: ReturnType<typeof vi.fn>;
};

beforeEach(() => {
    vi.clearAllMocks();
    bitmap = { width: 4000, height: 2000, close };
    canvas = {
        width: 0,
        height: 0,
        getContext: vi.fn(() => ({ drawImage })),
        toBlob: vi.fn((callback: BlobCallback, type: string) =>
            callback(new Blob([new Uint8Array(200_000)], { type })),
        ),
    };
    vi.stubGlobal(
        'createImageBitmap',
        vi.fn(async () => bitmap),
    );
    vi.stubGlobal('document', { createElement: vi.fn(() => canvas) });
});
afterEach(() => vi.unstubAllGlobals());

describe('phone photo preparation', () => {
    it('accepts exactly 15 MiB per photo', () => {
        const photo = new File(
            [new Uint8Array(15 * 1024 * 1024)],
            'field.jpg',
            { type: 'image/jpeg' },
        );
        expect(() => validatePhoto(photo)).not.toThrow();
    });
    it.each(['toBlob', 'File', 'context', 'encoding'])(
        'keeps a validated original ready for Laravel when %s is unavailable or fails',
        async (capability) => {
            const original = new File(
                [new Uint8Array(1_000_000)],
                'field.jpg',
                { type: 'image/jpeg' },
            );
            if (capability === 'toBlob')
                vi.stubGlobal('document', { createElement: () => ({}) });
            if (capability === 'File') vi.stubGlobal('File', undefined);
            if (capability === 'context')
                canvas.getContext.mockReturnValue(null);
            if (capability === 'encoding')
                canvas.toBlob.mockImplementation((callback: BlobCallback) =>
                    callback(null),
                );
            const selected = ref<File[]>([]);
            let upload!: ReturnType<typeof usePhotoCompression>;
            await renderToString(
                h(
                    defineComponent({
                        setup() {
                            upload = usePhotoCompression(() => selected.value);
                            return () => null;
                        },
                    }),
                ),
            );
            selected.value = await upload.preparePhotos([original]);
            expect(selected.value[0]).toBe(original);
            expect(upload.fallbackNotice.value).toContain('ukuran asli');
            expect(upload.fallbackNotice.value).toContain('Penggunaan data');
            expect(upload.compressing.value).toBe(false);
        },
    );

    it('detects missing encoding support before decoding a large image', async () => {
        vi.stubGlobal('document', { createElement: () => ({}) });
        await expect(
            compressPhoto(
                new File(['photo'], 'field.jpg', { type: 'image/jpeg' }),
            ),
        ).rejects.toThrow('kompresi');
        expect(createImageBitmap).not.toHaveBeenCalled();
    });

    it('detects an unavailable File constructor while keeping native input files usable for fallback', async () => {
        const original = new File(['photo'], 'field.jpg', {
            type: 'image/jpeg',
        });
        vi.stubGlobal('File', undefined);
        await expect(compressPhoto(original)).rejects.toThrow(
            'foto terkompresi',
        );
        expect(createImageBitmap).not.toHaveBeenCalled();
    });

    it('detects an unavailable canvas context before decoding', async () => {
        canvas.getContext.mockReturnValue(null);
        await expect(
            compressPhoto(
                new File(['photo'], 'field.jpg', { type: 'image/jpeg' }),
            ),
        ).rejects.toThrow('memproses');
        expect(createImageBitmap).not.toHaveBeenCalled();
    });

    it('still compresses through an image element when bitmap decoding is unavailable', async () => {
        vi.stubGlobal('createImageBitmap', undefined);
        const revoke = vi.fn();
        vi.stubGlobal('URL', {
            createObjectURL: () => 'blob:photo',
            revokeObjectURL: revoke,
        });
        vi.stubGlobal(
            'Image',
            class {
                naturalWidth = 4000;
                naturalHeight = 2000;
                onload: (() => void) | null = null;
                set src(value: string) {
                    if (value) queueMicrotask(() => this.onload?.());
                }
            },
        );
        const output = await compressPhoto(
            new File([new Uint8Array(1_000_000)], 'field.jpg', {
                type: 'image/jpeg',
            }),
        );
        expect(output.size).toBe(200_000);
        expect(output.type).toBe('image/jpeg');
        expect(revoke).toHaveBeenCalledWith('blob:photo');
        expect([canvas.width, canvas.height]).toEqual([0, 0]);
    });

    it('reduces transmitted bytes and dimensions while preserving proportions and EXIF-aware decoding', async () => {
        const original = new File([new Uint8Array(5_600_000)], 'field.jpeg', {
            type: 'image/jpeg',
        });
        const output = await compressPhoto(original);
        expect(output.size).toBeLessThan(original.size);
        expect(output.type).toBe('image/jpeg');
        expect(output.name).toBe('field.jpg');
        expect(drawImage).toHaveBeenCalledWith(bitmap, 0, 0, 2048, 1024);
        expect(createImageBitmap).toHaveBeenCalledWith(original, {
            imageOrientation: 'from-image',
        });
        expect(close).toHaveBeenCalledOnce();
        expect([canvas.width, canvas.height]).toEqual([0, 0]);
    });

    it('keeps an already-small image unchanged and never upscales', async () => {
        bitmap.width = 200;
        bitmap.height = 100;
        const original = new File(['small'], 'field.png', {
            type: 'image/png',
        });
        expect(await compressPhoto(original)).toBe(original);
        expect(canvas.toBlob).not.toHaveBeenCalled();
        expect(close).toHaveBeenCalledOnce();
    });

    it('accepts a 48 MP selection under 15 MB and keeps the compressed output limit', async () => {
        bitmap.width = 6048;
        bitmap.height = 8064;
        const original = new File(
            [new Uint8Array(8.5 * 1024 * 1024)],
            '48mp.jpg',
            { type: 'image/jpeg' },
        );
        const result = await compressPhoto(original);
        expect(drawImage).toHaveBeenCalledWith(bitmap, 0, 0, 1536, 2048);
        expect(result.size).toBeLessThan(original.size);
    });

    it('bounds encoding work even when the target size cannot be achieved', async () => {
        canvas.toBlob.mockImplementation(
            (callback: BlobCallback, type: string) =>
                callback(new Blob([new Uint8Array(600_000)], { type })),
        );
        const output = await compressPhoto(
            new File([new Uint8Array(1_000_000)], 'field.jpg', {
                type: 'image/jpeg',
            }),
        );
        expect(canvas.toBlob).toHaveBeenCalledTimes(3);
        expect(output.size).toBe(600_000);
    });

    it('uses an alpha-capable format for PNG instead of flattening onto JPEG', async () => {
        const output = await compressPhoto(
            new File(['png'], 'field.png', { type: 'image/png' }),
        );
        expect(canvas.toBlob).toHaveBeenCalledWith(
            expect.any(Function),
            'image/webp',
            0.82,
        );
        expect(output.type).toBe('image/webp');
        expect(output.name).toBe('field.webp');
    });

    it('uses the actual returned type when a browser falls back to PNG', async () => {
        canvas.toBlob.mockImplementation((callback: BlobCallback) =>
            callback(new Blob(['png'], { type: 'image/png' })),
        );
        const output = await compressPhoto(
            new File(['webp'], 'field.webp', { type: 'image/webp' }),
        );
        expect(output.type).toBe('image/png');
        expect(output.name).toBe('field.png');
        expect(canvas.toBlob).toHaveBeenCalledOnce();
    });

    it('rejects an oversized PNG fallback before attempting to upload it', async () => {
        canvas.toBlob.mockImplementation((callback: BlobCallback) =>
            callback(
                new Blob([new Uint8Array(16 * 1024 * 1024)], {
                    type: 'image/png',
                }),
            ),
        );
        await expect(
            compressPhoto(
                new File(['webp'], 'field.webp', { type: 'image/webp' }),
            ),
        ).rejects.toThrow('masih melebihi 15 MB');
        expect(close).toHaveBeenCalledOnce();
    });

    it('rejects invalid or oversized selections before decoding', async () => {
        for (const original of [
            new File(['pdf'], 'document.pdf', { type: 'application/pdf' }),
            new File([new Uint8Array(15 * 1024 * 1024 + 1)], 'large.jpg', {
                type: 'image/jpeg',
            }),
        ])
            await expect(compressPhoto(original)).rejects.toThrow('15 MB');
        expect(createImageBitmap).not.toHaveBeenCalled();
    });

    it('reports encoding failure without silently sending the large original and releases memory', async () => {
        canvas.toBlob.mockImplementation((callback: BlobCallback) =>
            callback(null),
        );
        await expect(
            compressPhoto(
                new File(['jpg'], 'field.jpg', { type: 'image/jpeg' }),
            ),
        ).rejects.toThrow('Pilih ulang foto');
        expect(close).toHaveBeenCalledOnce();
        expect([canvas.width, canvas.height]).toEqual([0, 0]);
    });
});

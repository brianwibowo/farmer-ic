import { renderToString } from '@vue/server-renderer';
import { defineComponent, h, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { compressPhoto, validatePhoto } from '@/services/photoCompression';
import { usePhotoCompression } from './usePhotoCompression';

vi.mock('@/services/photoCompression', () => ({
    compressPhoto: vi.fn(),
    validatePhoto: vi.fn(),
}));

async function uploader(selectedPhotos: () => readonly File[] = () => []) {
    let result!: ReturnType<typeof usePhotoCompression>;
    await renderToString(
        h(
            defineComponent({
                setup() {
                    result = usePhotoCompression(selectedPhotos);
                    return () => null;
                },
            }),
        ),
    );
    return result;
}

beforeEach(() => vi.resetAllMocks());

describe('photo selection preparation', () => {
    it('prepares five photos sequentially rather than decoding them all in parallel', async () => {
        let active = 0;
        let peak = 0;
        vi.mocked(compressPhoto).mockImplementation(async (file) => {
            active++;
            peak = Math.max(active, peak);
            await Promise.resolve();
            active--;
            return file;
        });
        const upload = await uploader();
        const files = Array.from(
            { length: 5 },
            (_, index) => new File(['photo'], `${index}.jpg`),
        );
        expect(await upload.preparePhotos(files)).toEqual(files);
        expect(peak).toBe(1);
        expect(upload.compressing.value).toBe(false);
        expect(upload.compressionStatus.value).toBe('Foto siap diunggah.');
    });

    it('blocks overlapping selection work until preparation has finished', async () => {
        let release!: (file: File) => void;
        vi.mocked(compressPhoto).mockReturnValue(
            new Promise<File>((resolve) => {
                release = resolve;
            }),
        );
        const upload = await uploader();
        const file = new File(['photo'], 'field.jpg');
        const first = upload.preparePhotos([file]);
        expect(upload.compressing.value).toBe(true);
        await expect(upload.preparePhotos([file])).rejects.toThrow('Tunggu');
        expect(upload.compressing.value).toBe(true);
        release(file);
        await first;
        expect(upload.compressing.value).toBe(false);
    });

    it('rejects invalid selections before processing or allowing original-file fallback', async () => {
        const upload = await uploader();
        const file = new File(['photo'], 'field.jpg');
        vi.mocked(validatePhoto).mockImplementation(() => {
            throw new Error('Invalid photo');
        });
        await expect(upload.preparePhotos([file])).rejects.toThrow(
            'Invalid photo',
        );
        expect(compressPhoto).not.toHaveBeenCalled();
        expect(upload.compressing.value).toBe(false);
        expect(upload.compressionStatus.value).toBe('');
    });

    it('keeps mixed compressed and original files and explains extra data use', async () => {
        const selected = ref<File[]>([]);
        const upload = await uploader(() => selected.value);
        const original = new File(['original photo'], 'field.jpg');
        const another = new File(['another original'], 'other.jpg');
        const compressed = new File(['small'], 'other.jpg');
        vi.mocked(compressPhoto)
            .mockRejectedValueOnce(new Error('Decode failed'))
            .mockResolvedValueOnce(compressed);
        selected.value = await upload.preparePhotos([original, another]);
        expect(selected.value).toEqual([original, compressed]);
        expect(upload.compressing.value).toBe(false);
        expect(upload.fallbackNotice.value).toContain('1 foto');
        expect(upload.fallbackNotice.value).toContain('ukuran asli');
        expect(upload.fallbackNotice.value).toContain('Penggunaan data');

        // Later selections must not hide the notice for an original still present.
        vi.mocked(compressPhoto).mockResolvedValue(compressed);
        selected.value.push(...(await upload.preparePhotos([another])));
        expect(upload.fallbackNotice.value).toContain('1 foto');
        selected.value.splice(0, 1);
        expect(upload.fallbackNotice.value).toBe('');
    });

    it('allows all five photos to use server compression if browser processing fails', async () => {
        const selected = ref<File[]>([]);
        const upload = await uploader(() => selected.value);
        const files = Array.from(
            { length: 5 },
            (_, index) => new File(['original'], `${index}.jpg`),
        );
        vi.mocked(compressPhoto).mockRejectedValue(
            new Error('Canvas unsupported'),
        );
        selected.value = await upload.preparePhotos(files);
        expect(selected.value).toEqual(files);
        expect(upload.fallbackNotice.value).toContain('5 foto');
        expect(upload.compressing.value).toBe(false);
    });
});

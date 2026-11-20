const MAX_DIMENSION = 2048;
const TARGET_BYTES = 512 * 1024;
const MAX_INPUT_BYTES = 10 * 1024 * 1024;
const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export function validatePhoto(file: File): void {
    if (!TYPES.includes(file.type) || file.size > MAX_INPUT_BYTES) {
        throw new Error('Pilih foto JPEG, PNG, atau WebP maksimal 10 MB.');
    }
}

async function decode(file: File): Promise<{
    image: CanvasImageSource;
    width: number;
    height: number;
    release: () => void;
}> {
    // Native decoding respects EXIF orientation, including mirrored phone photos.
    if (typeof createImageBitmap === 'function') {
        try {
            const image = await createImageBitmap(file, {
                imageOrientation: 'from-image',
            });
            return {
                image,
                width: image.width,
                height: image.height,
                release: () => image.close(),
            };
        } catch {
            // Some mobile browsers cannot decode every accepted format as a bitmap.
        }
    }
    const url = URL.createObjectURL(file);
    const image = new Image();
    try {
        await new Promise<void>((resolve, reject) => {
            image.onload = () => resolve();
            image.onerror = () =>
                reject(new Error('Foto tidak dapat dibaca. Pilih foto lain.'));
            image.src = url;
        });
        return {
            image,
            width: image.naturalWidth,
            height: image.naturalHeight,
            release: () => {
                image.src = '';
                URL.revokeObjectURL(url);
            },
        };
    } catch (error) {
        URL.revokeObjectURL(url);
        throw error;
    }
}

function encode(
    canvas: HTMLCanvasElement,
    type: string,
    quality: number,
): Promise<Blob> {
    return new Promise((resolve, reject) =>
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(
                          new Error('Foto gagal diproses. Pilih ulang foto.'),
                      ),
            type,
            quality,
        ),
    );
}

export async function compressPhoto(file: File): Promise<File> {
    validatePhoto(file);
    if (typeof File !== 'function') {
        throw new Error('Peramban tidak mendukung pembuatan foto terkompresi.');
    }
    const canvas = document.createElement('canvas');
    if (typeof canvas.toBlob !== 'function') {
        throw new Error('Peramban tidak mendukung kompresi foto.');
    }
    const context = canvas.getContext('2d');
    if (!context) {
        throw new Error('Peramban tidak dapat memproses foto.');
    }
    const decoded = await decode(file);
    try {
        if (!decoded.width || !decoded.height)
            throw new Error('Ukuran foto tidak valid.');
        const scale = Math.min(
            1,
            MAX_DIMENSION / Math.max(decoded.width, decoded.height),
        );
        if (scale === 1 && file.size <= TARGET_BYTES) return file;
        canvas.width = Math.max(1, Math.round(decoded.width * scale));
        canvas.height = Math.max(1, Math.round(decoded.height * scale));
        context.drawImage(decoded.image, 0, 0, canvas.width, canvas.height);
        // WebP preserves PNG/WebP alpha without an expensive transparency scan.
        const type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
        let best: Blob | null = null;
        for (const quality of [0.82, 0.72, 0.62]) {
            const candidate = await encode(canvas, type, quality);
            if (!best || candidate.size < best.size) best = candidate;
            if (best.size <= TARGET_BYTES || candidate.type === 'image/png')
                break;
        }
        if (!best) throw new Error('Foto gagal diproses. Pilih ulang foto.');
        if (best.size > MAX_INPUT_BYTES)
            throw new Error(
                'Foto hasil pemrosesan masih melebihi 10 MB. Pilih foto dengan resolusi lebih kecil.',
            );
        if (scale === 1 && best.size >= file.size) return file;
        const extension =
            best.type === 'image/jpeg'
                ? 'jpg'
                : best.type === 'image/webp'
                  ? 'webp'
                  : 'png';
        return new File(
            [best],
            `${file.name.replace(/\.[^.]+$/, '')}.${extension}`,
            {
                type: best.type,
                lastModified: file.lastModified,
            },
        );
    } finally {
        decoded.release();
        // Release backing pixels promptly on phones; five canvases must not accumulate.
        canvas.width = 0;
        canvas.height = 0;
    }
}

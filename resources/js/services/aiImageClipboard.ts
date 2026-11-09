export type AiImageCopyResult = {
    kind: 'image' | 'link';
    message: string;
};

export function canCopyAiImage(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.clipboard?.write === 'function' &&
        typeof ClipboardItem !== 'undefined' &&
        (typeof ClipboardItem.supports !== 'function' ||
            ClipboardItem.supports('image/png'))
    );
}

export function canCopyAiImageLink(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        typeof navigator.clipboard?.writeText === 'function'
    );
}

export async function copyAiImageLink(
    source: string,
): Promise<AiImageCopyResult> {
    if (!canCopyAiImageLink()) {
        throw new Error('Clipboard tidak tersedia di browser ini.');
    }
    const url = new URL(source, window.location.href).href;
    try {
        await navigator.clipboard.writeText(url);
    } catch {
        throw new Error(
            'Tautan gambar tidak dapat disalin. Periksa izin clipboard browser.',
        );
    }
    return { kind: 'link', message: 'Tautan gambar disalin.' };
}

async function imageAsPng(source: string): Promise<Blob> {
    let blob: Blob;
    try {
        const response = await fetch(source, { credentials: 'same-origin' });
        if (!response.ok) throw new Error();
        blob = await response.blob();
    } catch {
        throw new Error('Gambar tidak dapat diambil untuk disalin. Coba lagi.');
    }
    if (blob.type === 'image/png') return blob;
    if (!blob.type.startsWith('image/')) {
        throw new Error(
            'Format gambar tidak dapat disalin. Coba salin tautannya.',
        );
    }

    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(blob);
        const image = new Image();
        const fail = (): void => {
            URL.revokeObjectURL(url);
            reject(
                new Error(
                    'Gambar tidak dapat dikonversi untuk clipboard. Coba salin tautannya.',
                ),
            );
        };
        image.onerror = fail;
        image.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                const context = canvas.getContext('2d');
                if (!context || !canvas.width || !canvas.height) {
                    fail();
                    return;
                }
                context.drawImage(image, 0, 0);
                canvas.toBlob((png) => {
                    if (!png) {
                        fail();
                        return;
                    }
                    URL.revokeObjectURL(url);
                    resolve(png);
                }, 'image/png');
            } catch {
                fail();
            }
        };
        image.src = url;
    });
}

/** Call clipboard.write during the click gesture, even while the image loads. */
export async function copyAiImage(source: string): Promise<AiImageCopyResult> {
    if (!canCopyAiImage()) return copyAiImageLink(source);

    const png = imageAsPng(source);
    // A permission rejection can happen before loading finishes. Mark the
    // promise handled while still letting ClipboardItem receive its rejection.
    void png.catch(() => undefined);
    try {
        await navigator.clipboard.write([
            new ClipboardItem({ 'image/png': png }),
        ]);
    } catch (cause) {
        if (cause instanceof DOMException && cause.name === 'NotAllowedError') {
            throw new Error(
                'Izin clipboard ditolak. Coba salin tautan gambar.',
            );
        }
        throw cause instanceof Error
            ? cause
            : new Error('Gambar tidak dapat disalin. Coba salin tautannya.');
    }
    return { kind: 'image', message: 'Gambar berhasil disalin.' };
}

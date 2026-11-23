import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { compressPhoto, validatePhoto } from '@/services/photoCompression';

export function usePhotoCompression(
    selectedPhotos: () => readonly File[] = () => [],
) {
    const compressing = ref(false);
    const compressionStatus = ref('');
    const originals = reactive(new WeakSet<File>());
    const fallbackNotice = computed(() => {
        const count = selectedPhotos().filter((file) =>
            originals.has(file),
        ).length;
        return count
            ? `${count} foto akan diunggah dalam ukuran asli. Server akan mencoba mengompresnya; jika tidak dapat diproses dengan aman, foto asli akan disimpan. Penggunaan data dan waktu unggah dapat meningkat.`
            : '';
    });
    let disposed = false;
    onBeforeUnmount(() => {
        disposed = true;
    });

    async function preparePhotos(files: File[]): Promise<File[]> {
        if (compressing.value)
            throw new Error('Tunggu hingga foto selesai disiapkan.');
        // Validate the whole selection before any decoding or network transfer.
        files.forEach(validatePhoto);
        compressing.value = true;
        const result: File[] = [];
        try {
            for (const [index, file] of files.entries()) {
                if (disposed) throw new Error('Form sudah ditutup.');
                compressionStatus.value = `Menyiapkan foto ${index + 1}/${files.length}…`;
                try {
                    result.push(await compressPhoto(file));
                } catch {
                    // Selection validation has already passed. Laravel remains the
                    // authority for file validity and compression before storage.
                    originals.add(file);
                    result.push(file);
                }
            }
            if (disposed) throw new Error('Form sudah ditutup.');
            const before = files.reduce((sum, file) => sum + file.size, 0);
            const after = result.reduce((sum, file) => sum + file.size, 0);
            compressionStatus.value =
                before > after
                    ? `Foto siap. Ukuran unggahan berkurang ${Math.round((1 - after / before) * 100)}%.`
                    : 'Foto siap diunggah.';
            return result;
        } catch (error) {
            compressionStatus.value = '';
            throw error;
        } finally {
            compressing.value = false;
        }
    }

    return { compressing, compressionStatus, fallbackNotice, preparePhotos };
}

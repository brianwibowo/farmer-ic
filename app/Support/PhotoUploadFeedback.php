<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoUploadFeedback
{
    /** @param array<array-key, mixed> $files
     * @return array<string, string>
     */
    public static function messages(array $files): array
    {
        $messages = [];
        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile || $file->getError() === UPLOAD_ERR_OK) {
                continue;
            }
            $messages['photos.'.$index.'.uploaded'] = match ($file->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Foto melebihi batas unggahan server ('.ini_get('upload_max_filesize').' per file). Hubungi pengelola jika ukuran foto masih di bawah 10 MB.',
                UPLOAD_ERR_PARTIAL => 'Foto hanya terunggah sebagian. Pilih ulang foto dan coba lagi.',
                UPLOAD_ERR_NO_TMP_DIR => 'Foto gagal diunggah: folder sementara PHP tidak tersedia. Pengelola perlu mengatur upload_tmp_dir dan memulai ulang server.',
                UPLOAD_ERR_CANT_WRITE => 'Foto gagal diunggah: PHP tidak dapat menulis ke folder sementara. Pengelola perlu memeriksa izin dan ruang penyimpanan server.',
                UPLOAD_ERR_EXTENSION => 'Foto ditolak oleh ekstensi PHP pada server. Hubungi pengelola.',
                default => 'Foto gagal diunggah. Pilih ulang foto dan coba lagi.',
            };
        }

        return $messages;
    }
}

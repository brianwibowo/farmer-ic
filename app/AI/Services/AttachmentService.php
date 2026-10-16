<?php

namespace App\AI\Services;

use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, AiAttachment>
     */
    public function store(AiConversation $conversation, User $user, array $files): array
    {
        $attachments = [];
        $diskName = (string) config('ai.attachments.disk', 'local');
        $disk = Storage::disk($diskName);

        foreach ($files as $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages(['attachments' => 'Lampiran tidak valid.']);
            }

            $path = $file->getRealPath();
            $mime = $path ? (new \finfo(FILEINFO_MIME_TYPE))->file($path) : false;
            $mime = is_string($mime) ? $mime : $file->getMimeType();
            $allowed = config('ai.attachments.allowed_mimes', []);
            $maxSize = (int) config('ai.attachments.max_size_kb', 10240) * 1024;

            if (! is_string($mime) || ! in_array($mime, $allowed, true)) {
                throw ValidationException::withMessages(['attachments' => "Format {$file->getClientOriginalName()} tidak didukung."]);
            }

            $size = $file->getSize() ?: 0;
            if ($size > $maxSize) {
                throw ValidationException::withMessages(['attachments' => "Ukuran {$file->getClientOriginalName()} terlalu besar."]);
            }

            $sha256 = $path ? hash_file('sha256', $path) : false;
            $storedPath = $disk->putFile('ai/attachments', $file);

            if (! is_string($storedPath)) {
                throw ValidationException::withMessages(['attachments' => 'Lampiran tidak dapat disimpan.']);
            }

            $metadata = [];
            if ($path && str_starts_with($mime, 'image/')) {
                $dimensions = @getimagesize($path);
                if (is_array($dimensions)) {
                    $metadata = ['width' => $dimensions[0], 'height' => $dimensions[1]];
                }
            }

            $attachments[] = AiAttachment::create([
                'ai_conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'disk' => $diskName,
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size' => $size,
                'sha256' => is_string($sha256) ? $sha256 : hash('sha256', $storedPath),
                'kind' => 'image',
                'status' => 'ready',
                'metadata' => $metadata ?: null,
            ]);
        }

        return $attachments;
    }
}

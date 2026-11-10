<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\FarmLegalityDocument;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FarmLegalityService
{
    /** @return array{max_files: int, max_file_size_mb: float, max_upload_size_mb: float} */
    public static function limits(): array
    {
        $upload = self::iniBytes((string) ini_get('upload_max_filesize'));
        $post = self::iniBytes((string) ini_get('post_max_size'));

        return [
            'max_files' => 5,
            'max_file_size_mb' => min(10, $upload > 0 ? $upload / 1048576 : 10),
            'max_upload_size_mb' => min(50, $post > 0 ? max(0, $post / 1048576 - 1) : 50),
        ];
    }

    private static function iniBytes(string $value): int
    {
        $value = trim($value);
        $factor = match (strtolower(substr($value, -1))) {
            'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1
        };

        return (int) ((float) $value * $factor);
    }

    public function visibleFarm(User $actor, Team $team, Farm $farm): Farm
    {
        return Farm::visibleTo($actor, $team)->where('team_id', $team->id)->findOrFail($farm->id);
    }

    public function record(User $actor, Team $team, Farm $farm): ?FarmLegality
    {
        $farm = $this->visibleFarm($actor, $team, $farm);

        return FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->first();
    }

    /** @return Collection<int, FarmLegalityDocument> */
    public function documents(User $actor, Team $team, FarmLegality $legality): Collection
    {
        $legality = FarmLegality::visibleTo($actor, $team)->findOrFail($legality->id);

        return FarmLegalityDocument::visibleTo($actor, $team)->where('farm_legality_id', $legality->id)->orderBy('created_at')->orderBy('id')->get();
    }

    /** @return array<string, mixed>|null */
    public function payload(User $actor, Team $team, Farm $farm, ?FarmLegality $record = null): ?array
    {
        $record ??= $this->record($actor, $team, $farm);
        if ($record === null) {
            return null;
        }
        $record = FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->findOrFail($record->id);

        return [
            ...$record->only(['id', 'institution', 'summary']),
            'pks_started_at' => $record->pks_started_at?->toDateString(),
            'pks_ends_at' => $record->pks_ends_at?->toDateString(),
            'status' => $this->status($record, $farm),
            'documents' => $this->documents($actor, $team, $record)->map(fn (FarmLegalityDocument $document): array => [
                'id' => $document->id, 'original_name' => $document->original_name,
                'size_bytes' => $document->size_bytes,
                'download_url' => route('farm-legality-documents.download', $document, false),
            ])->all(),
        ];
    }

    private function status(FarmLegality $legality, Farm $farm): string
    {
        if ($legality->pks_started_at === null && $legality->pks_ends_at === null && $farm->land_tenure === 'owned') {
            return 'not_applicable';
        }
        if ($legality->pks_started_at === null || $legality->pks_ends_at === null) {
            return 'incomplete';
        }
        $today = CarbonImmutable::today('Asia/Jakarta')->toDateString();
        if ($today < $legality->pks_started_at->toDateString()) {
            return 'upcoming';
        }

        return $today > $legality->pks_ends_at->toDateString() ? 'expired' : 'active';
    }

    /** @param array<string, mixed> $data */
    public function save(User $actor, Team $team, Farm $farm, ?FarmLegality $legality, array $data): FarmLegality
    {
        $newPaths = [];
        try {
            return DB::transaction(function () use ($actor, $team, $farm, $legality, $data, &$newPaths): FarmLegality {
                $farm = Farm::visibleTo($actor, $team)->where('team_id', $team->id)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
                if ($legality !== null) {
                    $legality = FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($legality->id)->lockForUpdate()->firstOrFail();
                } elseif (FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->exists()) {
                    throw ValidationException::withMessages(['legality' => 'Legalitas sudah tersedia. Muat ulang lalu gunakan Ubah legalitas.']);
                }
                $existing = $legality ? $this->documents($actor, $team, $legality) : new Collection;
                if ($legality !== null) {
                    $this->assertCompleteDocuments($legality, $existing);
                }
                $remove = $data['remove_document_ids'] ?? [];
                if (array_diff($remove, $existing->modelKeys()) !== []) {
                    throw ValidationException::withMessages(['remove_document_ids' => 'Draft yang dipilih tidak tersedia pada legalitas ini.']);
                }
                $files = $data['documents'] ?? [];
                $limits = self::limits();
                if ($existing->count() - count($remove) + count($files) > $limits['max_files']) {
                    throw ValidationException::withMessages(['documents' => 'Maksimal lima draft PDF untuk satu legalitas. Hapus salah satu draft sebelum menambah berkas baru.']);
                }
                if (array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $files)) > $limits['max_upload_size_mb'] * 1048576) {
                    throw ValidationException::withMessages(['documents' => 'Total ukuran unggahan melebihi '.$limits['max_upload_size_mb'].' MB.']);
                }
                $legality ??= new FarmLegality(['farm_id' => $farm->id]);
                $legality->fill(collect($data)->only(['pks_started_at', 'pks_ends_at', 'institution', 'summary'])->all())->save();
                foreach ($files as $file) {
                    $path = 'farms/'.$farm->id.'/'.Str::uuid().'.pdf';
                    $newPaths[] = $path;
                    if (Storage::disk('legality')->putFileAs(dirname($path), $file, basename($path)) !== $path) {
                        throw new RuntimeException('Draft PDF belum berhasil disimpan.');
                    }
                    $original = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $original = preg_replace('/[\x00-\x1F\x7F]/u', '', $original) ?: 'draft-legalitas.pdf';
                    $document = new FarmLegalityDocument;
                    $document->fill([
                        'farm_legality_id' => $legality->id, 'disk' => 'legality', 'path' => $path,
                        'original_name' => mb_substr($original, 0, 255), 'mime_type' => 'application/pdf',
                        'size_bytes' => $file->getSize(),
                    ])->save();
                }
                $removed = $existing->whereIn('id', $remove);
                foreach ($removed as $document) {
                    $document->delete();
                }
                $removedPaths = array_values($removed->map(fn (FarmLegalityDocument $document): string => $document->path)->all());
                $this->cleanupAfterCommit($removedPaths);

                return $legality;
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($newPaths);
            throw $exception;
        }
    }

    public function delete(User $actor, Team $team, Farm $farm, FarmLegality $legality): void
    {
        DB::transaction(function () use ($actor, $team, $farm, $legality): void {
            Farm::visibleTo($actor, $team)->where('team_id', $team->id)->whereKey($farm->id)->lockForUpdate()->firstOrFail();
            $legality = FarmLegality::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($legality->id)->lockForUpdate()->firstOrFail();
            $documents = $this->documents($actor, $team, $legality);
            $this->assertCompleteDocuments($legality, $documents);
            $paths = array_values($documents->map(fn (FarmLegalityDocument $document): string => $document->path)->all());
            $legality->delete();
            $this->cleanupAfterCommit($paths);
        });
    }

    /**
     * Avoid cascading or counting past child rows omitted by the scoped read.
     *
     * @param  Collection<int, FarmLegalityDocument>  $documents
     */
    private function assertCompleteDocuments(FarmLegality $legality, Collection $documents): void
    {
        // Integrity-only existence check: never serialize or reuse a hidden child.
        if (FarmLegalityDocument::query()->where('farm_legality_id', $legality->id)->whereNotIn('id', $documents->modelKeys())->exists()) {
            throw ValidationException::withMessages(['legality' => 'Seluruh draft harus dapat dibaca sebelum legalitas diubah atau dihapus.']);
        }
    }

    /** @param list<string> $paths */
    public function cleanupAfterCommit(array $paths): void
    {
        if ($paths !== []) {
            DB::afterCommit(fn () => $this->deleteFiles($paths));
        }
    }

    /** @param list<string> $paths */
    private function deleteFiles(array $paths): void
    {
        try {
            Storage::disk('legality')->delete($paths);
        } catch (Throwable $exception) {
            report($exception); // Committed metadata is not rolled back by a cleanup failure.
        }
    }
}

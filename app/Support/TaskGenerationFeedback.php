<?php

namespace App\Support;

class TaskGenerationFeedback
{
    /** @param array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null} $result */
    public static function message(array $result): string
    {
        return match ($result['status']) {
            'generated' => $result['created'].' tugas SOP berhasil dibuat.',
            'already_generated' => $result['rescheduled'].' tugas pending dijadwalkan ulang; tugas tidak digandakan.',
            'missing_anchor' => 'Tugas belum dibuat: tanggal rencana maupun aktual tanam belum diisi.',
            'missing_sop' => 'Tugas belum dibuat: SOP default kebun belum diatur.',
            'inactive_sop' => 'Tugas belum dibuat: SOP default tidak aktif atau tidak dapat diakses.',
            'missing_published_version' => 'Tugas belum dibuat: versi SOP published belum tersedia atau versi tersimpan tidak valid.',
            'invalid_sop_tenant' => 'Tugas belum dibuat: SOP tidak sesuai tenant kebun. Hubungi admin.',
            'commodity_mismatch' => 'Tugas belum dibuat: komoditas varietas tidak sesuai SOP default. Hubungi admin.',
            'no_tasks' => 'SOP tidak memiliki tugas lanjutan untuk dibuat.',
            'closed_cycle' => 'Subblok sudah ditutup; tugas tidak diubah.',
            default => 'Tugas belum dapat disiapkan. Periksa versi SOP dan tahap subblok bersama admin.',
        };
    }
}

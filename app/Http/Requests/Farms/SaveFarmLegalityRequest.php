<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use App\Models\FarmLegality;
use App\Models\Team;
use App\Services\FarmLegalityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveFarmLegalityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reject a forbidden farm before PDF/date validation can disclose its state.
        // The controller still authorizes explicitly at the HTTP action boundary.
        $farm = $this->route('farm');
        $legality = $this->route('legality');
        $team = $this->user()?->currentTeam;

        return $farm instanceof Farm && $team instanceof Team && (int) $farm->team_id === (int) $team->id
            && ($legality instanceof FarmLegality
                ? Gate::allows('update', $legality)
                : Gate::allows('create', [FarmLegality::class, $farm]));
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $farm = $this->route('farm');
        $requiresPks = $farm instanceof Farm && $farm->land_tenure !== 'owned';
        $limits = FarmLegalityService::limits();

        return [
            'pks_started_at' => [Rule::requiredIf($requiresPks), 'nullable', 'date_format:Y-m-d', 'required_with:pks_ends_at'],
            'pks_ends_at' => [Rule::requiredIf($requiresPks), 'nullable', 'date_format:Y-m-d', 'required_with:pks_started_at', 'after_or_equal:pks_started_at'],
            'institution' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:10000'],
            'documents' => ['sometimes', 'array', 'max:'.$limits['max_files']],
            'documents.*' => ['required', File::types(['pdf'])->max((int) ($limits['max_file_size_mb'] * 1024)), 'extensions:pdf'],
            'remove_document_ids' => ['sometimes', 'array', 'max:'.$limits['max_files']],
            'remove_document_ids.*' => ['required', 'uuid', 'distinct'],
            'farm_id' => ['prohibited'], 'farm_legality_id' => ['prohibited'],
            'team_id' => ['prohibited'], 'disk' => ['prohibited'], 'path' => ['prohibited'],
            'id' => ['prohibited'], 'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'pks_started_at.required' => 'Isi tanggal mulai PKS.',
            'pks_ends_at.required' => 'Isi tanggal berakhir PKS.',
            'pks_started_at.required_with' => 'Lengkapi tanggal mulai PKS.',
            'pks_ends_at.required_with' => 'Lengkapi tanggal berakhir PKS.',
            'pks_ends_at.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai PKS.',
            'institution.required' => 'Isi instansi terkait.',
            'summary.required' => 'Isi ringkasan legalitas.',
            'documents.*.mimes' => 'Draft legalitas harus berupa berkas PDF.',
            'documents.*.extensions' => 'Gunakan berkas dengan ekstensi .pdf.',
            'documents.*.max' => 'Ukuran PDF melebihi batas unggahan per berkas.',
            'documents.max' => 'Maksimal lima draft PDF untuk satu legalitas.',
        ];
    }
}

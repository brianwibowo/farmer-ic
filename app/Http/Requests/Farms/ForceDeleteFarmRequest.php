<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ForceDeleteFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Reject forbidden actors before validating confirmations about a hidden action.
        $farm = $this->route('farm');

        return $farm instanceof Farm && Gate::allows('forceDelete', $farm);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $farm = $this->route('farm');

        return [
            'confirmation_name' => ['required', 'string', Rule::in([$farm instanceof Farm ? $farm->name : null])],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'summary_token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirmation_name.required' => 'Ketik nama kebun untuk mengonfirmasi.',
            'confirmation_name.in' => 'Nama konfirmasi harus sama persis dengan nama kebun.',
            'reason.required' => 'Alasan penghapusan wajib diisi.',
            'reason.min' => 'Jelaskan alasan penghapusan, minimal 5 karakter.',
            'reason.max' => 'Alasan maksimal 2.000 karakter.',
            'summary_token.required' => 'Muat ulang ringkasan sebelum menghapus.',
            'summary_token.regex' => 'Ringkasan tidak valid. Muat ulang halaman.',
        ];
    }
}

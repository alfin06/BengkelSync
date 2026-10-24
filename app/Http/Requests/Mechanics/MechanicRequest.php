<?php

namespace App\Http\Requests\Mechanics;

use App\Models\Mechanic;
use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Shared rules for adding and editing a mechanic. NIK and KTP photo are only accepted from
 * users with mechanic.view_identity; for everyone else they are prohibited fields.
 */
abstract class MechanicRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $identity = $this->canManageIdentity()
            ? [
                'nik' => ['nullable', 'string', 'regex:/^\d{16}$/'],
                'ktp' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'remove_ktp' => ['boolean'],
            ]
            : [
                'nik' => ['prohibited'],
                'ktp' => ['prohibited'],
                'remove_ktp' => ['prohibited'],
            ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            ...$identity,
        ];
    }

    /**
     * Strip spaces and dots people type in a NIK ("3374 0101 0101 0001").
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('nik')) {
            $this->merge(['nik' => preg_replace('/\D/', '', $this->string('nik')->toString())]);
        }
    }

    public function canManageIdentity(): bool
    {
        return $this->user()?->can('viewIdentity', Mechanic::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function mechanicData(): array
    {
        return collect($this->validated())->except(['ktp', 'remove_ktp'])->all();
    }

    public function ktp(): ?UploadedFile
    {
        $file = $this->file('ktp');

        return $file instanceof UploadedFile ? $file : null;
    }
}

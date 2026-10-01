<?php

declare(strict_types=1);

namespace App\Http\Requests\Regency;

use Illuminate\Foundation\Http\FormRequest;

final class RegencyLogoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->is_regency_user || $user->is_superadmin);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDashboardSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'refresh_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'scroll_speed' => ['required', 'integer', 'min:20', 'max:200'],
        ];
    }
}

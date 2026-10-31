<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\BrandFont;
use App\Enums\BrandThemeMode;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return $this->user()?->can('manageWorkspace', $organization) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $image = ['nullable', 'file', 'max:2048', 'mimes:png,jpg,jpeg,webp,ico'];

        return [
            'display_name' => ['nullable', 'string', 'max:160'],
            'primary_color' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'theme_mode' => ['nullable', Rule::enum(BrandThemeMode::class)],
            'font_family' => ['nullable', Rule::enum(BrandFont::class)],
            'login_heading' => ['nullable', 'string', 'max:160'],
            'login_message' => ['nullable', 'string', 'max:500'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:32'],
            'footer_text' => ['nullable', 'string', 'max:255'],
            'logo' => $image,
            'logo_light' => $image,
            'logo_dark' => $image,
            'favicon' => $image,
            'login_image' => $image,
        ];
    }
}

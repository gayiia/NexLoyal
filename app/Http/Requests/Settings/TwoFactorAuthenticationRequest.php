<?php

// This request gates access to two-factor settings based on Fortify features.
namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\Features;
use Laravel\Fortify\InteractsWithTwoFactorState;

// This class integrates Fortify's two-factor state checks.
class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // This allows the request only when 2FA is enabled in Fortify.
        return Features::enabled(Features::twoFactorAuthentication());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // No input fields are validated for this request.
        return [];
    }
}

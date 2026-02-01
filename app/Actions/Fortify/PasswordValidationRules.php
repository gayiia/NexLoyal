<?php

// This trait provides shared password validation rules for Fortify actions.
namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

// This trait keeps password rules consistent across auth actions.
trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        // These rules enforce a strong, confirmed password.
        return ['required', 'string', Password::default(), 'confirmed'];
    }
}

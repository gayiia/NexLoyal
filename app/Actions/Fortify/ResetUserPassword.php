<?php

// This action validates and resets a user's password after a reset request.
namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

// This class applies password reset updates for Fortify.
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        // This validates the new password using shared rules.
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // This updates the stored password hash for the user.
        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}

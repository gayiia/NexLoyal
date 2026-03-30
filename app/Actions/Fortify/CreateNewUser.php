<?php

// This action validates and creates a new user during registration.
namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

// This class encapsulates user creation logic for Fortify.
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // These validations enforce required fields and unique email.
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        // This creates and returns the new user record.
        return User::create([
            'name' => $input['name'],
            'email' => strtolower($input['email']),
            'password' => $input['password'],
        ]);
    }
}

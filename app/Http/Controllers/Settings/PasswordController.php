<?php

// This controller manages password update settings for authenticated users.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

// This class renders the password form and applies updates.
class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): View
    {
        // This shows the password update form.
        return view('settings.password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        // This validates the current password and the new password rules.
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // This updates the stored password hash for the user.
        $request->user()->update([
            'password' => $validated['password'],
        ]);

        return back();
    }
}

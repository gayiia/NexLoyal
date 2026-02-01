<?php

// This controller manages the authenticated user's profile settings.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// This class renders and updates profile data for the settings area.
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): View
    {
        // This indicates whether email verification is required and passes any status message.
        return view('settings.profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // This fills the user model with validated profile data.
        $request->user()->fill($request->validated());

        // Changing email resets verification so the user must re-verify.
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // This confirms the current password before deleting the account.
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // This logs the user out before deleting their record.
        Auth::logout();

        $user->delete();

        // This clears the session to prevent reuse.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

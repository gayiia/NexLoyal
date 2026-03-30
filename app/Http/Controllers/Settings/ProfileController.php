<?php

// This controller manages the authenticated user's profile settings.
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
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
            'users' => User::query()->orderBy('name')->orderBy('email')->get(),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // This fills the user model with validated profile data.
        $request->user()->fill($request->validated());

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

    /**
     * Update the password for a managed user from the profile screen.
     */
    public function updateManagedUserPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validateWithBag('managedUserPassword', [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update([
            'password' => $validated['password'],
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('status', 'user-password-updated');
    }

    /**
     * Delete a managed user from the profile screen.
     */
    public function destroyManagedUser(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return redirect()
                ->route('profile.edit')
                ->withErrors(['managed_user' => 'Use the delete account section to remove your own account.']);
        }

        if (User::query()->count() <= 1) {
            return redirect()
                ->route('profile.edit')
                ->withErrors(['managed_user' => 'You cannot delete the last remaining user account.']);
        }

        $user->delete();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'user-deleted');
    }
}

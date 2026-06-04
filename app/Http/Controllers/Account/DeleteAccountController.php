<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\DeleteUserAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeleteAccountController extends Controller
{
    public function destroy(Request $request, DeleteUserAccount $deleteUserAccount): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirmation' => ['required', 'in:DELETE'],
        ]);

        if ($request->session()->has('impersonating')) {
            return back()->withErrors([
                'password' => 'You cannot delete an account while impersonating another user.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        $deleteUserAccount->handle($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
}

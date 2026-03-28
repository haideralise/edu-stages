<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebLoginRequest;
use App\Models\WpUser;
use App\Support\PasswordCheck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('web')->check()) {
            $role = Auth::guard('web')->user()->resolveRole();

            return redirect()->route($role === 'coach' ? 'coach.results' : 'account.mybmi');
        }

        return view('auth.login');
    }

    public function login(WebLoginRequest $request): RedirectResponse
    {
        $user = WpUser::where('user_login', $request->input('user_login'))
            ->orWhere('user_email', $request->input('user_login'))
            ->first();

        if (! $user || ! PasswordCheck::verify($request->input('password'), $user->user_pass)) {
            return back()->withErrors(['user_login' => 'Invalid username or password.'])->withInput(['user_login' => $request->input('user_login')]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $role = $user->resolveRole();
        $default = route($role === 'coach' ? 'coach.results' : 'account.mybmi');

        return redirect()->intended($default);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

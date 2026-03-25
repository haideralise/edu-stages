<?php

namespace App\Http\Controllers;

use App\Models\WpUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'user_login' => 'required|string',
            'password'   => 'required|string',
        ]);

        $user = WpUser::where('user_login', $request->input('user_login'))
            ->orWhere('user_email', $request->input('user_login'))
            ->first();

        if (! $user || ! $this->checkPassword($request->input('password'), $user->user_pass)) {
            return back()->withErrors(['user_login' => 'Invalid username or password.'])->withInput(['user_login' => $request->input('user_login')]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/account/mybmi');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function checkPassword(string $password, string $hash): bool
    {
        if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$')) {
            return Hash::check($password, $hash);
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            return $this->verifyPhpass($password, $hash);
        }

        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            return hash_equals($hash, md5($password));
        }

        return false;
    }

    private function verifyPhpass(string $password, string $stored): bool
    {
        $itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

        $countLog2 = strpos($itoa64, $stored[3]);
        $count = 1 << $countLog2;
        $salt  = substr($stored, 4, 8);

        $hash = md5($salt . $password, true);
        do {
            $hash = md5($hash . $password, true);
        } while (--$count);

        $encoded = substr($stored, 0, 12);
        $i = 0;
        $len = 16;

        do {
            $value = ord($hash[$i++]);
            $encoded .= $itoa64[$value & 0x3f];
            if ($i < $len) {
                $value |= ord($hash[$i]) << 8;
            }
            $encoded .= $itoa64[($value >> 6) & 0x3f];
            if ($i++ >= $len) break;
            if ($i < $len) {
                $value |= ord($hash[$i]) << 16;
            }
            $encoded .= $itoa64[($value >> 12) & 0x3f];
            if ($i++ >= $len) break;
            $encoded .= $itoa64[($value >> 18) & 0x3f];
        } while ($i < $len);

        return hash_equals($stored, $encoded);
    }
}

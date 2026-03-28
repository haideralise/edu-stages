<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use App\Models\WpUserMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('meta');

        return view('account.info', compact('user'));
    }

    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $userId = $request->user()->ID;

        WpUserMeta::updateOrCreate(
            ['user_id' => $userId, 'meta_key' => 'billing_birthdate'],
            ['meta_value' => $request->input('birthdate')],
        );

        WpUserMeta::updateOrCreate(
            ['user_id' => $userId, 'meta_key' => 'billing_gender'],
            ['meta_value' => $request->input('gender')],
        );

        return redirect()->route('account.info')->with('success', 'Account info updated.');
    }
}

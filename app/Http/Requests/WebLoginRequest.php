<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WebLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}

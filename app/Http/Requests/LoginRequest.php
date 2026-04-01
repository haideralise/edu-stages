<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SchemeD422;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    use SchemeD422;

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

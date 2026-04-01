<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'birthdate' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
        ];
    }
}

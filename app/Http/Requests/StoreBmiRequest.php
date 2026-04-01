<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SchemeD422;
use Illuminate\Foundation\Http\FormRequest;

class StoreBmiRequest extends FormRequest
{
    use SchemeD422;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,ID'],
            'date' => ['required'],
            'height' => ['required', 'numeric', 'min:30', 'max:250'],
            'weight' => ['required', 'numeric', 'min:1', 'max:300'],
            'hc' => ['nullable', 'numeric', 'min:20', 'max:100'],
        ];
    }
}

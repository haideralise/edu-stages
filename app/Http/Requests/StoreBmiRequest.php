<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBmiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'   => ['required', 'integer'],
            'height' => ['required', 'numeric', 'min:30', 'max:250'],
            'weight' => ['required', 'numeric', 'min:1', 'max:300'],
            'hc'     => ['nullable', 'numeric', 'min:20', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed',
            'errors'  => $validator->errors()->toArray(),
        ], 422));
    }
}

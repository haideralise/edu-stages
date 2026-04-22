<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            'month' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'order_date' => ['required', 'date_format:Y-m-d'],
            'class_year' => ['required', 'string', 'max:10'],
            'gateway' => ['required', 'string'],
        ];
    }
}

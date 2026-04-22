<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRefundRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'min:1'],
            'refund_fee' => ['required', 'numeric', 'min:0'],
            'refund_date' => ['required', 'date_format:Y-m-d'],
            'refund_reason' => ['nullable', 'string', 'max:255', 'required_without:refund_reason2'],
            'refund_reason2' => ['nullable', 'string', 'max:255', 'required_without:refund_reason'],
        ];
    }
}

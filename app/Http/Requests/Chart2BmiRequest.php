<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\SchemeD422;
use Illuminate\Foundation\Http\FormRequest;

class Chart2BmiRequest extends FormRequest
{
    use SchemeD422;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'in:height,weight,bmi,hc'],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'class_id' => $this->class_id,
            'exam_id' => $this->exam_id,
            'exam_name' => $this->exam_name,
            'exam_type' => $this->exam_type,
            'exam_data' => $this->exam_data,
            'exam_date' => $this->exam_date,
            'class_year' => $this->class_year,
            'class_month' => $this->class_month,
            'status' => $this->status,
        ];
    }
}

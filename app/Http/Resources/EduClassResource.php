<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EduClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'class_id'     => $this->class_id,
            'class_name'   => $this->class_name,
            'district_id'  => $this->district_id,
            'product_id'   => $this->product_id,
            'product_name' => $this->product_name,
            'date_time'    => $this->date_time,
            'date_month'   => $this->date_month,
            'class_date'   => $this->class_date,
            'class_exam'   => $this->class_exam,
            'lv3'          => $this->lv3,
            'class_year'   => $this->class_year,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BmiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'height' => $this->height,
            'weight' => $this->weight,
            'hc' => $this->hc,
            'date' => $this->date,
            'date_formatted' => date('Y-m-d', $this->date),
            'bmi' => $this->bmi,
        ];
    }
}

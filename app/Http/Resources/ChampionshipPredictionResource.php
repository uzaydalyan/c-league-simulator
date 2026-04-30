<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChampionshipPredictionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'team'       => new TeamBasicResource($this->resource->team),
            'percentage' => round($this->resource->percentage, 2),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParseRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reviews_fetched' => $this->reviews_fetched,
            'reviews_created' => $this->reviews_created,
            'reviews_updated' => $this->reviews_updated,
            'failure_reason' => $this->failure_reason,
            'error_message' => $this->error_message,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
        ];
    }
}

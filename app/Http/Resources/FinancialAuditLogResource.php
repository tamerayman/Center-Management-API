<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialAuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'action' => $this->action,
            'auditable' => [
                'type' => $this->auditable_type,
                'id' => $this->auditable_id,
            ],
            'changes' => $this->changes,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}

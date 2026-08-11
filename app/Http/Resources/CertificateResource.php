<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Certificate
 */
class CertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'recipient_name' => $this->recipient_name,
            'recipient_email' => $this->recipient_email,
            'description' => $this->description,
            'date_of_issue' => $this->date_of_issue?->toDateString(),
            'date_of_expiry' => $this->date_of_expiry?->toDateString(),
            'status' => $this->displayStatus(),
            'verification_code' => $this->verification_code,
            'verify_url' => $this->getVerifyUrlAttribute(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

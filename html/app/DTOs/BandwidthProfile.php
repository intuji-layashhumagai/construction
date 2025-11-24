<?php

namespace App\DTOs;

use App\Enums\NetworkQuality;

class BandwidthProfile
{
    public int $availableBandwidth = 1000000; // 1MB/s default

    public NetworkQuality $networkQuality = NetworkQuality::UNKNOWN;

    public function toArray(): array
    {
        return [
            'availableBandwidth' => $this->availableBandwidth,
            'networkQuality' => $this->networkQuality->value,
        ];
    }

    public static function fromArray(array $data): self
    {
        $profile = new self;
        $profile->availableBandwidth = $data['availableBandwidth'] ?? 1000000;

        $quality = $data['networkQuality'] ?? 'UNKNOWN';
        $profile->networkQuality = match ($quality) {
            'EXCELLENT' => NetworkQuality::EXCELLENT,
            'GOOD' => NetworkQuality::GOOD,
            'FAIR' => NetworkQuality::FAIR,
            'POOR' => NetworkQuality::POOR,
            default => NetworkQuality::UNKNOWN,
        };

        return $profile;
    }
}

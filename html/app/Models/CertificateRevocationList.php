<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateRevocationList extends Model
{
    protected $fillable = [
        'version',
        'revoked_serials',
        'issued_at',
        'next_update',
        'signature',
    ];

    protected function casts(): array
    {
        return [
            'revoked_serials' => 'array',
            'issued_at' => 'datetime',
            'next_update' => 'datetime',
        ];
    }

    public function isRevoked(string $serial): bool
    {
        return in_array($serial, $this->revoked_serials);
    }

    public static function latest(): ?self
    {
        return self::orderBy('version', 'desc')->first();
    }

    public function verifySignature(): bool
    {
        // todo:  Implement signature verification logic
        // For now, return true
        return true;
    }
}

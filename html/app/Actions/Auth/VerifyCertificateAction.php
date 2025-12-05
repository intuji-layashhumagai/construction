<?php

namespace App\Actions\Auth;

use App\Models\CertificateRevocationList;
use App\Models\Worker;
use Illuminate\Support\Facades\Cache;

final class VerifyCertificateAction
{
    /**
     * Create a new class instance.
     */
    public static function handle(string $certificatePem, ?string $deviceId = null): array
    {
        $result = [
            'valid' => false,
            'reason' => '',
            'certificate_info' => null,
        ];

        // Parse certificate
        $certInfo = openssl_x509_parse($certificatePem);

        if (! $certInfo) {
            $result['reason'] = 'Invalid certificate format';

            return $result;
        }

        $result['certificate_info'] = $certInfo;

        // Check if worker exists in database
        $workerId = $certInfo['subject']['OU'];
        if (! Worker::where('id', $workerId)->exists()) {
            $result['reason'] = 'Worker not found';

            return $result;
        }

        // Check expiration with grace period
        $now = time();
        $expiryTime = $certInfo['validTo_time_t'];
        $gracePeriodHours = self::getGracePeriodHours($certInfo);

        if ($expiryTime < $now) {
            // Check if within grace period
            $graceExpiry = $expiryTime + ($gracePeriodHours * 3600);
            if ($now <= $graceExpiry) {
                $result['grace_period_active'] = true;
                $result['grace_period_remaining_hours'] = round(($graceExpiry - $now) / 3600, 1);
            } else {
                $result['reason'] = 'Certificate expired';

                return $result;
            }
        }

        // Check if not yet valid
        if ($certInfo['validFrom_time_t'] > $now) {
            $result['reason'] = 'Certificate not yet valid';

            return $result;
        }

        // Verify signature against CA
        $caCert = config('auth.ca_certificate');
        if (! openssl_x509_verify($certificatePem, $caCert)) {
            $result['reason'] = 'Invalid certificate signature';

            return $result;
        }

        // Check revocation status
        $serial = strtoupper($certInfo['serialNumberHex']);
        if (self::isRevoked($serial)) {
            $result['reason'] = 'Certificate revoked';

            return $result;
        }

        $result['valid'] = true;

        return $result;
    }

    private static function getGracePeriodHours(array $certInfo): int
    {
        // Emergency certificates get longer grace period
        $id = $certInfo['subject']['OU'];
        $worker = Worker::where('id', $id)->first();

        if ($worker && $worker->has_emergency_access) {
            return 168; // 7 days for emergency certs
        }

        return 72; // 3 days for regular certs
    }

    /**
     * Check if certificate is revoked using cached CRL
     */
    private static function isRevoked(string $serial): bool
    {
        $crl = self::getCachedRevocationList();

        return $crl && $crl->isRevoked($serial);
    }

    /**
     * Get the latest cached revocation list
     */
    private static function getCachedRevocationList(): ?CertificateRevocationList
    {
        return Cache::remember('crl_latest', 3600, function () {
            return CertificateRevocationList::latest();
        });
    }

    public static function checkEmergencyAccess(string $workerId): bool
    {

        $worker = Worker::where('id', $workerId)->first();

        if ($worker && $worker->has_emergency_access) {
            return true;
        }

        return false;
    }
}

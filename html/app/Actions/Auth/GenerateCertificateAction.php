<?php

namespace App\Actions\Auth;

use App\Models\Worker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Generate a certificate for a worker using the CA credentials.
 */
final class GenerateCertificateAction
{
    public static function handle(Worker $worker): array
    {
        // Load CA credentials
        $caCert = file_get_contents(config('project.auth.certificates.cert_path'));
        $caKey = file_get_contents(config('project.auth.certificates.key_path'));
        $passphrase = config('project.auth.certificates.pass_phrase');
        $validityDays = config('project.auth.certificates.certification_valid_day');

        $caPrivateKey = openssl_pkey_get_private($caKey, $passphrase);

        if (! $caPrivateKey) {
            Log::error('Failed to load CA private key');
            throw new \Exception('Failed to load CA private key');
        }

        // Generate worker's key pair
        $workerKey = openssl_pkey_new([
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($workerKey, $workerPrivateKey);
        $workerDetails = openssl_pkey_get_details($workerKey);
        $workerPublicKey = $workerDetails['key'];

        // Prepare certificate subject
        $subject = [
            'countryName' => config('project.country_code', 'NP'),
            'organizationName' => config('project.organization_name', 'Construction Co.'),
            'organizationalUnitName' => $worker->id,
            'commonName' => $worker->employee_id,
            'emailAddress' => $worker->email,
        ];

        $csr = openssl_csr_new($subject, $workerKey);
        $microTime = microtime(true);
        $serial = (int) self::generateSerial($worker->id, $microTime);

        // Sign the certificate
        $cert = openssl_csr_sign($csr, $caCert, $caPrivateKey, $validityDays, null, $serial);

        openssl_x509_export($cert, $certificate);

        $certInfo = openssl_x509_parse($cert);
        $expiresAt = Carbon::createFromTimestamp($certInfo['validTo_time_t']);

        return [
            'certificate_pem' => $certificate,
            'private_key_pem' => $workerPrivateKey,
            'public_key_pem' => $workerPublicKey,
            'serial_number' => $certInfo['serialNumber'],
            'serial_number_hex' => $certInfo['serialNumberHex'],
            'expires_at' => $expiresAt->toDateTimeString(),
            'time' => $microTime,
        ];
    }

    private static function generateSerial($userId, $time): string
    {
        $data = $userId.'_'.$time;

        // create hash and convert to integer
        $hash = hash('sha256', $data);
        // take first 15 characters of the hash and convert from hexadecimal  to decimal
        $serial = base_convert(substr($hash, 0, 15), 16, 10);

        // Ensure positive integer and within reasonable bounds
        return abs((int) $serial) % PHP_INT_MAX;
    }
}

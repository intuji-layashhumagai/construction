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
        $caCert = file_get_contents(config('project.auth.cert_path'));
        $caKey = file_get_contents(config('project.auth.key_path'));
        $passphrase = config('project.auth.pass_phrase');
        $validityDays = config('project.auth.certification_valid_day');

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

        // Sign the certificate
        $cert = openssl_csr_sign($csr, $caCert, $caPrivateKey, $validityDays);

        openssl_x509_export($cert, $certificate);

        $certInfo = openssl_x509_parse($cert);
        $expiresAt = Carbon::createFromTimestamp($certInfo['validTo_time_t']);

        return [
            'certificate_pem' => $certificate,
            'private_key_pem' => $workerPrivateKey,
            'public_key_pem' => $workerPublicKey,
            'serial_number' => $certInfo['serialNumber'],
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }
}

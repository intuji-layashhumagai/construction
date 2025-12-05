<?php

namespace App\Http\Middleware;

use App\Actions\Auth\DetectCredentialSharingAction;
use App\Actions\Auth\TrackCertificateUsageAction;
use App\Actions\Auth\TrackDeviceUsageAction;
use App\Actions\Auth\VerifyCertificateAction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CertificateAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $certificatePem = $request->header('X-Client-Certificate');
        $deviceId = $request->header('X-Device-ID');

        if (! $certificatePem || ! $deviceId) {
            return response()->json([
                'error' => 'Certificate and device ID required',
                'emergency_access_available' => true,
            ], 401);
        }
        $cleanPemString = str_replace('\n', "\n", $certificatePem);

        // Check for emergency recovery certificate
        if ($this->isEmergencyCertificate($cleanPemString)) {
            return $this->handleEmergencyAccess($request, $deviceId, $cleanPemString, $next);
        }

        // Verify certificate
        $verification = VerifyCertificateAction::handle($cleanPemString, $deviceId);
        $workerId = $verification['certificate_info']['subject']['OU'];
        $serial = strtoupper($verification['certificate_info']['serialNumber']);

        if (! $verification['valid']) {
            // Check if emergency access is allowed
            $emergencyAllowed = VerifyCertificateAction::checkEmergencyAccess($workerId);

            return response()->json([
                'error' => 'Certificate verification failed',
                'reason' => $verification['reason'],
                'emergency_access_available' => $emergencyAllowed,
            ], 401);
        }

        // Track certificate usage for sharing prevention
        TrackCertificateUsageAction::handle(
            $serial,
            $deviceId,
            $request->ip()
        );

        // Track device usage for multi-device monitoring
        TrackDeviceUsageAction::handle(
            $workerId,
            $deviceId,
            $serial
        );

        // Check for suspicious activity
        $sharingDetection = DetectCredentialSharingAction::handle($serial);
        if ($sharingDetection['suspicious_activity']) {
            // todo: display it in the dash board for suspicious activity
            Log::warning('Suspicious certificate usage detected', [
                'serial' => $serial,
                'device_id' => $deviceId,
                'risk_level' => $sharingDetection['risk_level'],
                'issues' => $sharingDetection['issues'],
            ]);
        }

        $request->merge(['worker_id' => $workerId]);

        return $next($request);
    }

    /**
     * Check if the certificate is an emergency recovery certificate
     */
    private function isEmergencyCertificate(string $certificatePem): bool
    {
        return str_starts_with($certificatePem, 'EMERGENCY:');
    }

    /**
     * Handle emergency access with recovery certificate
     */
    private function handleEmergencyAccess(Request $request, string $deviceId, string $certificatePem, Closure $next): Response
    {
        // Parse emergency certificate format: EMERGENCY:workerId:recoveryCode:timestamp
        $parts = explode(':', $certificatePem);
        if (count($parts) !== 4) {
            return response()->json([
                'error' => 'Invalid emergency certificate format',
                'emergency_access_available' => false,
            ], 401);
        }

        [, $workerId, $recoveryCode, $timestamp] = $parts;

        // Validate timestamp (emergency certs expire after 1 hour)
        if (time() - (int) $timestamp > 3600) {
            return response()->json([
                'error' => 'Emergency certificate expired',
                'emergency_access_available' => false,
            ], 401);
        }

        // Validate recovery code (in production, this would check against a database)
        if ($recoveryCode !== 'emergency-code') {
            return response()->json([
                'error' => 'Invalid recovery code',
                'emergency_access_available' => false,
            ], 401);
        }

        // Check if emergency access is allowed for this worker
        $emergencyAllowed = VerifyCertificateAction::checkEmergencyAccess($workerId);

        if (! $emergencyAllowed) {
            return response()->json([
                'error' => 'Emergency access not allowed for this worker',
                'emergency_access_available' => false,
            ], 401);
        }

        // Log emergency access
        Log::warning('Emergency certificate access granted', [
            'worker_id' => $workerId,
            'device_id' => $deviceId,
            'recovery_code' => $recoveryCode,
            'timestamp' => $timestamp,
            'ip' => $request->ip(),
        ]);

        // Track emergency access
        TrackDeviceUsageAction::handle(
            $workerId,
            $deviceId,
            'EMERGENCY-'.$recoveryCode
        );

        // Mark request as emergency access
        $request->merge([
            'worker_id' => $workerId,
            'emergency_access' => true,
            'emergency_reason' => 'forgotten_credentials',
        ]);

        return $next($request);
    }
}

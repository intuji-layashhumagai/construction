<?php

namespace App\Http\Middleware;

use App\Actions\Auth\TrackCertificateUsageAction;
use App\Actions\Auth\TrackDeviceUsageAction;
use App\Actions\Auth\VerifyCertificateAction;
use Closure;
use Illuminate\Http\Request;
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

        return $next($request);
    }
}

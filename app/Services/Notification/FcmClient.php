<?php

namespace App\Services\Notification;

use App\Models\UserDevice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmClient
{
    /**
     * Send a push notification to a single device.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, status: string, error: ?string, invalid_token: bool, response: array<string, mixed>}
     */
    public function send(UserDevice $device, string $title, string $body, array $data = []): array
    {
        $mode = $this->resolveMode();

        return match ($mode) {
            'v1' => $this->sendViaHttpV1($device, $title, $body, $data),
            'legacy' => $this->sendViaLegacy($device, $title, $body, $data),
            default => $this->sendViaMock($device, $title, $body, $data),
        };
    }

    /**
     * Determine the FCM transport mode.
     */
    private function resolveMode(): string
    {
        if (config('services.fcm.credentials_path') && config('services.fcm.project_id')) {
            return 'v1';
        }

        if (config('services.fcm.key')) {
            return 'legacy';
        }

        return 'mock';
    }

    /**
     * FCM HTTP v1 API (modern, uses service account JSON).
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, status: string, error: ?string, invalid_token: bool, response: array<string, mixed>}
     */
    private function sendViaHttpV1(UserDevice $device, string $title, string $body, array $data): array
    {
        $projectId = config('services.fcm.project_id');
        $accessToken = $this->getOAuth2Token();

        if (! $accessToken) {
            return $this->result(false, 'auth_failed', 'Failed to obtain OAuth2 access token', false);
        }

        $payload = [
            'message' => [
                'token' => $device->device_token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->normalizeData($data),
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                return $this->result(true, 'sent', null, false, $response->json() ?? []);
            }

            $invalidToken = $this->isInvalidToken($response->status(), $response->body());

            return $this->result(false, 'failed', $response->body(), $invalidToken, $response->json() ?? []);
        } catch (\Throwable $e) {
            Log::error("FCM v1 send error: {$e->getMessage()}", ['device_id' => $device->device_id]);

            return $this->result(false, 'exception', $e->getMessage(), false);
        }
    }

    /**
     * FCM Legacy HTTP API (uses server key).
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, status: string, error: ?string, invalid_token: bool, response: array<string, mixed>}
     */
    private function sendViaLegacy(UserDevice $device, string $title, string $body, array $data): array
    {
        $fcmKey = config('services.fcm.key');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$fcmKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $device->device_token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
            ]);

            if ($response->successful()) {
                $json = $response->json() ?? [];
                $hasFailure = ($json['failure'] ?? 0) > 0;

                if ($hasFailure) {
                    $results = $json['results'] ?? [];
                    $error = $results[0]['error'] ?? 'unknown';
                    $invalidToken = in_array($error, ['NotRegistered', 'InvalidRegistration', 'MismatchSenderId']);

                    return $this->result(false, 'failed', $error, $invalidToken, $json);
                }

                return $this->result(true, 'sent', null, false, $json);
            }

            $invalidToken = $this->isInvalidToken($response->status(), $response->body());

            return $this->result(false, 'failed', $response->body(), $invalidToken, $response->json() ?? []);
        } catch (\Throwable $e) {
            Log::error("FCM legacy send error: {$e->getMessage()}", ['device_id' => $device->device_id]);

            return $this->result(false, 'exception', $e->getMessage(), false);
        }
    }

    /**
     * Mock/dry-run mode when no FCM credentials are configured.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, status: string, error: ?string, invalid_token: bool, response: array<string, mixed>}
     */
    private function sendViaMock(UserDevice $device, string $title, string $body, array $data): array
    {
        Log::info('FCM mock send', [
            'device_id' => $device->device_id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return $this->result(true, 'mock', null, false, ['mock' => true]);
    }

    /**
     * Obtain a short-lived OAuth2 access token using the service account JSON.
     */
    private function getOAuth2Token(): ?string
    {
        $credentialsPath = config('services.fcm.credentials_path');

        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            return null;
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            $now = time();

            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claim = base64_encode(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign("{$header}.{$claim}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$claim}.".base64_encode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::error("FCM OAuth2 token error: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Check if the error indicates an invalid/unregistered token.
     */
    private function isInvalidToken(int $httpStatus, string $responseBody): bool
    {
        if (in_array($httpStatus, [404, 410])) {
            return true;
        }

        $invalidIndicators = ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT', 'NotRegistered'];

        foreach ($invalidIndicators as $indicator) {
            if (str_contains($responseBody, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize data values to strings (FCM data payload requires string values).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function normalizeData(array $data): array
    {
        return array_map(fn (mixed $v): string => is_string($v) ? $v : json_encode($v), $data);
    }

    /**
     * Build a standardized result array.
     *
     * @param  array<string, mixed>  $response
     * @return array{success: bool, status: string, error: ?string, invalid_token: bool, response: array<string, mixed>}
     */
    private function result(bool $success, string $status, ?string $error, bool $invalidToken, array $response = []): array
    {
        return [
            'success' => $success,
            'status' => $status,
            'error' => $error,
            'invalid_token' => $invalidToken,
            'response' => $response,
        ];
    }
}

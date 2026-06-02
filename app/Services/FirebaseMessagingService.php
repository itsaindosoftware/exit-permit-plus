<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseMessagingService
{
    private string $serviceAccountPath;
    private array $serviceAccount;
    private ?string $accessToken = null;
    private ?int $accessTokenExpiry = null;

    public function __construct()
    {
        $this->serviceAccountPath = config('services.firebase.service_account')
            ?: env('FIREBASE_SERVICE_ACCOUNT')
            ?: env('GOOGLE_APPLICATION_CREDENTIALS')
            ?: getenv('FIREBASE_SERVICE_ACCOUNT')
            ?: ($_ENV['FIREBASE_SERVICE_ACCOUNT'] ?? $_SERVER['FIREBASE_SERVICE_ACCOUNT'] ?? null);

        if (!$this->serviceAccountPath || !file_exists($this->serviceAccountPath)) {
            throw new \RuntimeException('Firebase service account JSON not configured or file not found. Set FIREBASE_SERVICE_ACCOUNT in .env');
        }

        $json = file_get_contents($this->serviceAccountPath);
        $this->serviceAccount = json_decode($json, true);

        if (!is_array($this->serviceAccount) || empty($this->serviceAccount['client_email']) || empty($this->serviceAccount['private_key'])) {
            throw new \RuntimeException('Invalid Firebase service account JSON.');
        }
    }

    /**
     * Send a notification to a single device token using FCM HTTP v1
     *
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public function sendToToken(string $deviceToken, string $title, string $body, array $data = []): ?array
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            Log::error('Failed to obtain FCM access token');
            return null;
        }

        $projectId = $this->serviceAccount['project_id'] ?? null;

        if (!$projectId) {
            Log::error('Service account JSON missing project_id');
            return null;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        if (!empty($data)) {
            $payload['message']['data'] = array_map(fn($v) => is_scalar($v) ? (string) $v : json_encode($v), $data);
        }

        $response = $this->http()
            ->withToken($accessToken)
            ->acceptJson()
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error('FCM send failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        return $response->json();
    }

    private function getAccessToken(): ?string
    {
        // Cache token in memory until expiry
        if ($this->accessToken && $this->accessTokenExpiry && time() < $this->accessTokenExpiry - 60) {
            return $this->accessToken;
        }

        $now = time();
        $privateKey = $this->serviceAccount['private_key'];
        $clientEmail = $this->serviceAccount['client_email'];

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $scope = 'https://www.googleapis.com/auth/firebase.messaging';

        $claimSet = [
            'iss' => $clientEmail,
            'scope' => $scope,
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $jwt = $this->encodeJwt($header, $claimSet, $privateKey);

        $response = $this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($response->failed()) {
            Log::error('Failed to exchange JWT for access token', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            Log::error('No access_token in OAuth response', $data);
            return null;
        }

        $this->accessToken = $data['access_token'];
        $this->accessTokenExpiry = $now + (int) ($data['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function http()
    {
        $options = [];

        if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            $options['curl'][CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        return Http::withOptions($options);
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function encodeJwt(array $header, array $payload, string $privateKey): string
    {
        $segments = [];
        $segments[] = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $segments[] = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));

        $signingInput = implode('.', $segments);

        $signature = null;
        $success = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success || $signature === null) {
            throw new \RuntimeException('Failed to sign JWT with private key.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }
}

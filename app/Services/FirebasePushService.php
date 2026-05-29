<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $serverKey = trim((string) config('services.firebase.server_key'));

        if ($serverKey === '' || trim($token) === '') {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'key=' . $serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'priority' => 'high',
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'sound' => 'default',
                    ],
                    'data' => $this->normalizeDataPayload($data),
                ]);

        if ($response->successful()) {
            return true;
        }

        Log::warning('Failed to send Firebase push notification.', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);

        return false;
    }

    private function normalizeDataPayload(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $normalized[(string) $key] = $value === null ? '' : (string) $value;
                continue;
            }

            $normalized[(string) $key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $normalized;
    }
}

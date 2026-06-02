<?php

namespace App\Notifications\Channels;

use App\Services\FirebaseMessagingService;

class FirebasePushChannel
{
    public function __construct(private readonly FirebaseMessagingService $firebaseMessagingService)
    {
    }

    public function send(object $notifiable, object $notification): void
    {
        if (!method_exists($notification, 'toFcm')) {
            return;
        }

        $token = method_exists($notifiable, 'routeNotificationForFcm')
            ? $notifiable->routeNotificationForFcm()
            : null;

        if (!is_string($token) || trim($token) === '') {
            return;
        }

        $payload = (array) $notification->toFcm($notifiable);
        $title = trim((string) ($payload['title'] ?? 'Notification'));
        $body = trim((string) ($payload['body'] ?? 'You have a new notification.'));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Use HTTP v1 FirebaseMessagingService which requires a service account JSON
        // The service will log on failure; we don't need the return value here.
        try {
            $this->firebaseMessagingService->sendToToken($token, $title, $body, $data);
        } catch (\Throwable $e) {
            // Let the service log errors; swallow to avoid breaking notification flow
        }
    }
}

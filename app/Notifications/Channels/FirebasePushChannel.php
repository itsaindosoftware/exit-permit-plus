<?php

namespace App\Notifications\Channels;

use App\Services\FirebasePushService;

class FirebasePushChannel
{
    public function __construct(private readonly FirebasePushService $firebasePushService)
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

        $this->firebasePushService->sendToToken($token, $title, $body, $data);
    }
}

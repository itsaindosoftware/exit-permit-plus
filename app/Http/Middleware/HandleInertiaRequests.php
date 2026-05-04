<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->load('role:id,code,name'),
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
            ],
            'notifications' => [
                'unread' => fn() => $request->user()
                    ? $request->user()->unreadNotifications()
                        ->latest()
                        ->limit(5)
                        ->get()
                        ->map(fn($notification) => [
                            'id' => $notification->id,
                            'title' => $notification->data['title'] ?? 'Notifikasi',
                            'message' => $notification->data['message'] ?? '',
                            'created_at' => optional($notification->created_at)->toDateTimeString(),
                        ])
                        ->values()
                        ->all()
                    : [],
                'unread_count' => fn() => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
            ],
        ];
    }
}

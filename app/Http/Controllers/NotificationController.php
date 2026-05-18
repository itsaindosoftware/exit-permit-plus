<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    /**
     * Mark a notification as read for the authenticated user.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $notification = $user->unreadNotifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return redirect()->back();
    }
}

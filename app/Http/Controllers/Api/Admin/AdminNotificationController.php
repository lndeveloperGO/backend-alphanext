<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AdminNotificationController extends Controller
{
    /**
     * Send a notification to users.
     */
    public function send(Request $request)
    {
        $request->validate([
            'target' => 'required|in:all,specific',
            'user_ids' => 'required_if:target,specific|array',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $title = $request->title;
        $message = $request->message;
        $data = ['type' => 'update'];

        if ($request->target === 'all') {
            $users = User::all();
            Notification::send($users, new GeneralNotification($title, $message, $data));
        } else {
            $users = User::whereIn('id', $request->user_ids)->get();
            Notification::send($users, new GeneralNotification($title, $message, $data));
        }

        return response()->json([
            'message' => 'Notification sent successfully',
            'count' => $users->count()
        ]);
    }
}

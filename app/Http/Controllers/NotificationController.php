<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $notifications = Notification::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function ($n) {
                return [
                    'id'   => $n->id,
                    'text' => $n->message,
                    'time' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'notifications' => $notifications
        ]);
    }
}

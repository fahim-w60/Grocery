<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    public function getNotifications()
    {
        $user = auth()->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($notifications as $notification) {
            $notification->image = $notification->image ? asset($notification->image) : null;
        }

        return response()->json([
            'status' => true,
            'notifications' => $notifications,
            'icon' => asset('uploads/notification/notification.jpeg'),
        ]);
    }

    public function getNotification($id)
    {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (!$notification) {
            return response()->json(['status' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->image = $notification->image ? asset($notification->image) : null;

        return response()->json([
            'status' => true,
            'notification' => $notification,
        ]);
    }

    public function readNotification($id)
    {
        $user = auth()->user();
       // dd($user);
        $notification = Notification::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

           // dd($notification);

        if (!$notification) {
            return response()->json(['status' => false, 'message' => 'Notification not found'], 404);
        }

        $notification->read_at = now();
        $notification->save();

        $notification->image = $notification->image ? asset($notification->image) : null;

        return response()->json([
            'status' => true,
            'message' => 'Notification marked as read',
            'notification' => $notification,
        ]);
    }


    public function createNotification(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string',
            'message' => 'nullable|string',
            'type' => 'required|string|in:shopper arrive,order picked up,normal notification',
            'order_id' => 'nullable|integer',
            'shopper_id' => 'nullable|integer|min:1',
        ]);

        $user = auth()->user();

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('media/notification');
            if (!\File::exists($destinationPath)) {
                \File::makeDirectory($destinationPath, 0755, true);
            }
            $image->move($destinationPath, $imageName);
            $validated['image'] = 'media/notification/' . $imageName;
        } else {
            $validated['image'] = null;
        }

        $notificationData = [
            'user_id' => $user->id,
            'title' => $validated['title'] ?? null,
            'message' => $validated['message'] ?? null,
            'image' => $validated['image'],
            'type' => $validated['type'],
        ];

        if ($validated['type'] === 'shopper arrive') {
            if (empty($validated['shopper_id'])) {
                return response()->json(['status' => false, 'message' => 'shopper_id is required for shopper arrive type'], 422);
            }
            $notificationData['shopper_id'] = $validated['shopper_id'];
        } elseif ($validated['type'] === 'order picked up') {
            if (empty($validated['order_id'])) {
                return response()->json(['status' => false, 'message' => 'order_id is required for order picked up type'], 422);
            }
            $notificationData['order_id'] = $validated['order_id'];
        }

        $notification = Notification::create($notificationData);

        // Return full image URL
        $notification->image = $notification->image ? asset($notification->image) : null;

        return response()->json([
            'status' => true,
            'message' => 'Notification created successfully',
            'notification' => $notification,
        ]);
    }

   public function totalNotification()
   {
        $user = auth()->user();
        $notification = Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
            
        return response()->json([
            'status' => true,
            'total' => $notification,
        ]);
   }
}

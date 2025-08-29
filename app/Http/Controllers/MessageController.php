<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    private function buildImageUrl($photo)
    {
        if (!empty($photo) && !filter_var($photo, FILTER_VALIDATE_URL)) {
            return asset($photo);
        }
        if (!empty($photo)) {
            return $photo;
        }
        return asset('uploads/profiles/no_image.jpeg');
    }

    public function message($id)
    {
        $authId = Auth::id();

        $messages = Message::with(['sender:id,name', 'receiver:id,name'])
            ->where(function($q) use ($authId, $id) {
                $q->where('sender_id', $authId)
                  ->where('receiver_id', $id);
            })
            ->orWhere(function($q) use ($authId, $id) {
                $q->where('sender_id', $id)
                  ->where('receiver_id', $authId);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $messages = $messages->map(function ($message) {
            $senderPhoto = $message->sender ? $message->sender->photo : null;
            $message->image = $this->buildImageUrl($senderPhoto);
            return $message;
        });

        $user = Auth::user();
        $image = $this->buildImageUrl($user ? $user->photo : null);

        return response()->json([
            'success' => true,
            'messages' => $messages
            
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);
        $authId = Auth::id();
        $message = Message::create([
            'sender_id' => $authId,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message,
            'message_type' => $request->message_type ?? 'text',
            'is_read' => $request->is_read ?? false,
            'created_at' => $request->created_at ?? now(),
            'delivery_status' => $request->delivery_status ?? 'sent'
        ]);

        // Load sender information
        $sender = User::find($authId);
        
        // Prepare response data
        $responseData = [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'receiver_id' => $message->receiver_id,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'created_at' => $message->created_at->toISOString(),
            'is_read' => $message->is_read,
            'delivery_status' => $message->delivery_status,
            'sender' => [
                'id' => $sender->id,
                'name' => $sender->name,
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $responseData
        ], 201);
    

    }
    

    public function sentMessages()
    {
        $authId = Auth::id();

        $messages = Message::with(['receiver:id,name'])
            ->where('sender_id', $authId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    public function receivedMessages()
    {
        $authId = Auth::id();

        $messages = Message::with(['sender:id,name'])
            ->where('receiver_id', $authId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }

    public function unreadCount()
    {
        $count = Message::where('receiver_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }

}
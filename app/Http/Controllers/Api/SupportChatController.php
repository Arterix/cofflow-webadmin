<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $messages = SupportMessage::where('customer_id', $userId)
            ->when($request->query('after_id'), fn ($q, $id) => $q->where('id', '>', (int) $id))
            ->orderBy('id')
            ->limit(200)
            ->get(['id', 'from_customer', 'body', 'read_at', 'created_at']);

        // Balasan CS dianggap terbaca saat customer membuka chat.
        SupportMessage::where('customer_id', $userId)
            ->where('from_customer', false)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success($messages);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $message = SupportMessage::create([
            'customer_id' => $user->id,
            'sender_id' => $user->id,
            'from_customer' => true,
            'body' => trim($data['body']),
        ]);

        return $this->success(
            $message->only(['id', 'from_customer', 'body', 'read_at', 'created_at']),
            'Pesan terkirim',
            201
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = SupportMessage::where('customer_id', $request->user()->id)
            ->where('from_customer', false)
            ->whereNull('read_at')
            ->count();

        return $this->success(['unread_count' => $count]);
    }
}

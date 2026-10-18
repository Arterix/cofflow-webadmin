<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportChatController extends Controller
{
    public function index(Request $request): View
    {
        $threads = $this->threads();
        $active = null;
        $messages = collect();

        if ($id = $request->query('customer')) {
            $active = User::findOrFail($id);
            $messages = $this->openThread($active);
        }

        return view('admin.support.index', compact('threads', 'active', 'messages'));
    }

    public function reply(Request $request, User $customer): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        SupportMessage::create([
            'customer_id' => $customer->id,
            'sender_id' => $request->user()->id,
            'from_customer' => false,
            'body' => trim($data['body']),
        ]);

        return redirect()->route('admin.support.index', ['customer' => $customer->id]);
    }

    private function threads()
    {
        $latest = SupportMessage::select('customer_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('customer_id');

        $unread = SupportMessage::select('customer_id', DB::raw('COUNT(*) as unread'))
            ->where('from_customer', true)
            ->whereNull('read_at')
            ->groupBy('customer_id');

        return SupportMessage::query()
            ->joinSub($latest, 'l', 'support_messages.id', '=', 'l.last_id')
            ->leftJoinSub($unread, 'u', 'support_messages.customer_id', '=', 'u.customer_id')
            ->with('customer:id,name,email')
            ->select('support_messages.*', DB::raw('COALESCE(u.unread, 0) as unread'))
            ->orderByDesc('support_messages.id')
            ->get();
    }

    private function openThread(User $customer)
    {
        SupportMessage::where('customer_id', $customer->id)
            ->where('from_customer', true)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return SupportMessage::where('customer_id', $customer->id)
            ->with('sender:id,name')
            ->orderBy('id')
            ->get();
    }
}

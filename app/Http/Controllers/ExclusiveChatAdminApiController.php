<?php

namespace App\Http\Controllers;

use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatPollVote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExclusiveChatAdminApiController extends Controller
{
    public function analytics(ChatPoll $poll): Response
    {
        $options = $poll->options()->withCount('votes')->orderBy('sort_order')->get();
        $totalVotes = $options->sum('votes_count');

        $payload = [
            'total_votes' => $totalVotes,
            'options' => $options->map(function ($option) use ($totalVotes) {
                $count = (int) $option->votes_count;
                $percent = $totalVotes ? round(($count / $totalVotes) * 100) : 0;
                return [
                    'option_id' => $option->id,
                    'label' => $option->label,
                    'count' => $count,
                    'percent' => $percent,
                ];
            }),
        ];

        return response($payload);
    }

    public function voters(Request $request, ChatPoll $poll, ChatPollOption $option): Response
    {
        if ((int) $option->chat_poll_id !== (int) $poll->id) {
            return response(['message' => 'Option mismatch.'], 400);
        }

        $search = trim((string) $request->query('search', ''));

        $query = ChatPollVote::query()
            ->where('chat_poll_id', $poll->id)
            ->where('option_id', $option->id)
            ->with(['customer.tier'])
            ->orderByDesc('voted_at');

        if ($search !== '') {
            $query->whereHas('customer', function ($customerQuery) use ($search) {
                $customerQuery
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $votes = $query->paginate(12)->withQueryString();

        $data = $votes->map(function ($vote) {
            $customer = $vote->customer;
            $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
            $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');
            return [
                'name' => $name,
                'email' => $customer?->email ?? '',
                'tier' => $customer?->tier?->title ?? '—',
                'voted_at' => optional($vote->voted_at)->toIso8601String(),
            ];
        });

        return response([
            'data' => $data,
            'meta' => [
                'current_page' => $votes->currentPage(),
                'last_page' => $votes->lastPage(),
                'per_page' => $votes->perPage(),
                'total' => $votes->total(),
            ],
        ]);
    }
}

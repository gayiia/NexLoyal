<?php

// This controller provides admin API endpoints for chat analytics.
namespace App\Http\Controllers;

use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatPollVote;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// This class returns poll analytics and voter lists for the admin UI.
class ExclusiveChatAdminApiController extends Controller
{
    // This returns aggregate vote counts for a poll.
    public function analytics(ChatPoll $poll): Response
    {
        // This loads options with vote counts to avoid N+1 queries.
        $options = $poll->options()->withCount('votes')->orderBy('sort_order')->get();
        $totalVotes = $options->sum('votes_count');

        // This formats a lightweight analytics payload for the UI.
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

    // This returns a paginated list of voters for a specific poll option.
    public function voters(Request $request, ChatPoll $poll, ChatPollOption $option): Response
    {
        // This prevents mismatched poll/option IDs from leaking data.
        if ((int) $option->chat_poll_id !== (int) $poll->id) {
            return response(['message' => 'Option mismatch.'], 400);
        }

        // This optional search filters voters by customer identity fields.
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

        // This paginates results for the admin panel.
        $votes = $query->paginate(12)->withQueryString();

        // This maps voter records into a UI-friendly structure.
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

        // This includes pagination metadata expected by the front-end.
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

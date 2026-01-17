<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatSetting;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExclusiveChatController extends Controller
{
    public function index(Request $request)
    {
        $settings = $this->getSettings();
        $tiers = Tier::query()->orderBy('min_points')->get();

        $messages = ChatMessage::query()
            ->with(['attachments', 'poll.options'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('exclusive-chat.index', [
            'settings' => $settings,
            'tiers' => $tiers,
            'messages' => $messages,
        ]);
    }

    public function settings()
    {
        $settings = $this->getSettings();
        $tiers = Tier::query()->orderBy('min_points')->get();

        return view('exclusive-chat.settings', [
            'settings' => $settings,
            'tiers' => $tiers,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allowed_tiers' => ['nullable', 'array'],
            'allowed_tiers.*' => ['integer', 'exists:tiers,id'],
        ]);

        $settings = $this->getSettings();
        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'allowed_tiers' => array_values(array_map('intval', $validated['allowed_tiers'] ?? [])),
        ]);

        return redirect()->route('exclusive-chat.settings');
    }

    public function storeMessage(Request $request)
    {
        $validated = $request->validate([
            'type' => ['required', 'in:TEXT,POLL'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'tier_visibility' => ['nullable', 'array'],
            'tier_visibility.*' => ['integer', 'exists:tiers,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'image', 'max:5120'],
            'poll_options' => ['nullable', 'array'],
            'poll_options.*' => ['nullable', 'string', 'max:255'],
            'closes_at' => ['nullable', 'date'],
        ]);

        $pollOptions = [];
        if ($validated['type'] === 'POLL') {
            $pollOptions = array_values(array_filter(array_map(function ($option) {
                return $option ? trim($option) : null;
            }, $validated['poll_options'] ?? [])));

            if (count($pollOptions) < 2 || count($pollOptions) > 6) {
                return back()->withErrors(['poll_options' => 'Polls need 2 to 6 options.']);
            }

            if (!count($request->file('attachments', []))) {
                return back()->withErrors(['attachments' => 'Please upload at least one image for the poll.']);
            }
        }

        return DB::transaction(function () use ($validated, $request, $pollOptions) {
            $message = ChatMessage::create([
                'store_id' => null,
                'type' => $validated['type'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
                'tier_visibility' => array_values(array_map('intval', $validated['tier_visibility'] ?? [])),
                'sent_at' => now(),
            ]);

            $files = $request->file('attachments', []);
            $attachments = [];
            foreach ($files as $index => $file) {
                if (!$file) {
                    continue;
                }
                $path = $file->storePublicly('chat', ['disk' => 'public']);
                $attachments[] = [
                    'chat_message_id' => $message->id,
                    'file_url' => Storage::disk('public')->url($path),
                    'file_type' => 'IMAGE',
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($attachments) {
                ChatAttachment::insert($attachments);
            }

            if ($message->type === 'POLL') {
                $poll = ChatPoll::create([
                    'chat_message_id' => $message->id,
                    'allow_multiple' => false,
                    'closes_at' => $validated['closes_at'] ?? null,
                ]);

                $optionRows = [];
                foreach ($pollOptions as $index => $label) {
                    $optionRows[] = [
                        'chat_poll_id' => $poll->id,
                        'label' => Str::limit($label, 255, ''),
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                ChatPollOption::insert($optionRows);
            }

            return redirect()->route('exclusive-chat');
        });
    }

    public function view(ChatMessage $message)
    {
        $message->load(['attachments', 'poll.options.votes']);
        $poll = $message->poll;

        if (!$poll) {
            return redirect()->route('exclusive-chat');
        }

        $totalVotes = $poll->options->sum(function ($option) {
            return $option->votes->count();
        });

        $options = $poll->options->map(function ($option) use ($totalVotes) {
            $count = $option->votes->count();
            $percent = $totalVotes ? round(($count / $totalVotes) * 100) : 0;
            return [
                'id' => $option->id,
                'label' => $option->label,
                'count' => $count,
                'percent' => $percent,
            ];
        });

        return view('exclusive-chat.view', [
            'message' => $message,
            'poll' => $poll,
            'options' => $options,
            'totalVotes' => $totalVotes,
        ]);
    }

    public function destroy(ChatMessage $message)
    {
        $message->delete();

        return redirect()->route('exclusive-chat');
    }

    private function getSettings(): ChatSetting
    {
        return ChatSetting::firstOrCreate(
            ['store_id' => null],
            ['enabled' => false, 'allowed_tiers' => []]
        );
    }
}

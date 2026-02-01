<?php

// This controller manages the exclusive chat feature for admin users.
namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatPollVote;
use App\Models\ChatSetting;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// This class handles chat message creation, settings, and exports.
class ExclusiveChatController extends Controller
{
    // This lists chat messages and loads settings for the main chat page.
    public function index(Request $request)
    {
        // This loads the current feature settings and available tiers.
        $settings = $this->getSettings();
        $tiers = Tier::query()->orderBy('min_points')->get();

        // This fetches recent messages with related attachments and polls.
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

    // This shows the exclusive chat settings page.
    public function settings()
    {
        $settings = $this->getSettings();
        $tiers = Tier::query()->orderBy('min_points')->get();

        return view('exclusive-chat.settings', [
            'settings' => $settings,
            'tiers' => $tiers,
        ]);
    }

    // This updates whether chat is enabled and which tiers are allowed.
    public function updateSettings(Request $request)
    {
        // These validations ensure tiers exist and enabled is a boolean.
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'allowed_tiers' => ['nullable', 'array'],
            'allowed_tiers.*' => ['integer', 'exists:tiers,id'],
        ]);

        // This persists the settings for the global store scope.
        $settings = $this->getSettings();
        $settings->update([
            'enabled' => $request->boolean('enabled'),
            'allowed_tiers' => array_values(array_map('intval', $validated['allowed_tiers'] ?? [])),
        ]);

        return redirect()->route('exclusive-chat.settings');
    }

    // This stores a new chat message, including poll setup and attachments.
    public function storeMessage(Request $request)
    {
        // These validations enforce message types and attachment limits.
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

        // Polls require 2-6 options and at least one image attachment.
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

        // This keeps message, attachments, and poll creation in a single transaction.
        return DB::transaction(function () use ($validated, $request, $pollOptions) {
            // This creates the chat message record first.
            $message = ChatMessage::create([
                'store_id' => null,
                'type' => $validated['type'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'],
                'tier_visibility' => array_values(array_map('intval', $validated['tier_visibility'] ?? [])),
                'sent_at' => now(),
            ]);

            // This stores any uploaded images and links them to the message.
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

            // This creates poll records only for poll messages.
            if ($message->type === 'POLL') {
                $poll = ChatPoll::create([
                    'chat_message_id' => $message->id,
                    'allow_multiple' => false,
                    'closes_at' => $validated['closes_at'] ?? null,
                ]);

                // This inserts poll options in their display order.
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

    // This shows a single poll message and its aggregated results.
    public function view(ChatMessage $message)
    {
        $message->load(['attachments', 'poll.options.votes']);
        $poll = $message->poll;

        if (!$poll) {
            return redirect()->route('exclusive-chat');
        }

        // This counts total votes across all options.
        $totalVotes = $poll->options->sum(function ($option) {
            return $option->votes->count();
        });

        // This prepares per-option counts and percentages for display.
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

    // This exports all chat messages to CSV, including attachments and poll data.
    public function exportMessages()
    {
        $tiers = Tier::query()->orderBy('min_points')->get()->keyBy('id');

        $fileName = 'exclusive_chat_messages_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $query = ChatMessage::query()
            ->with(['attachments', 'poll.options'])
            ->orderByDesc('sent_at')
            ->orderByDesc('id');

        // This streams results in chunks to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query, $tiers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Type',
                'Title',
                'Body',
                'Tier Visibility',
                'Sent At',
                'Attachments',
                'Poll Options',
                'Poll Closes At',
            ]);

            $query->chunk(200, function ($messages) use ($handle, $tiers) {
                foreach ($messages as $message) {
                    // This resolves tier IDs into display labels for export.
                    $tierIds = collect($message->tier_visibility ?? [])->map(fn ($id) => (int) $id)->all();
                    $tierLabels = $tierIds
                        ? collect($tierIds)->map(fn ($id) => $tiers->get($id)?->title)->filter()->values()->all()
                        : ['Default tiers'];

                    // This includes attachment URLs in a single cell.
                    $attachments = $message->attachments
                        ? $message->attachments->map(fn ($attachment) => $attachment->resolved_url ?: $attachment->file_url)->filter()->values()->all()
                        : [];

                    $pollOptions = [];
                    $pollClosesAt = null;
                    if ($message->poll) {
                        // This adds poll metadata when the message is a poll.
                        $pollOptions = $message->poll->options
                            ? $message->poll->options->pluck('label')->filter()->values()->all()
                            : [];
                        $pollClosesAt = optional($message->poll->closes_at)->toIso8601String();
                    }

                    fputcsv($handle, [
                        $message->id,
                        $message->type,
                        $message->title,
                        $message->body,
                        implode(', ', $tierLabels),
                        optional($message->sent_at)->toIso8601String(),
                        implode(' | ', $attachments),
                        implode(' | ', $pollOptions),
                        $pollClosesAt,
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This exports votes for a single poll message to CSV.
    public function exportPoll(ChatMessage $message)
    {
        $message->load(['poll.options']);
        $poll = $message->poll;

        if (!$poll) {
            return redirect()->route('exclusive-chat');
        }

        // This builds a timestamped filename for the export.
        $fileName = 'exclusive_chat_poll_' . $poll->id . '_' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $query = ChatPollVote::query()
            ->with(['option', 'customer.tier'])
            ->where('chat_poll_id', $poll->id)
            ->orderByDesc('voted_at');

        // This streams results in chunks to avoid loading all rows into memory.
        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Option',
                'Customer Name',
                'Customer Email',
                'Tier',
                'Voted At',
            ]);

            $query->chunk(500, function ($votes) use ($handle) {
                foreach ($votes as $vote) {
                    // This uses email as a fallback when names are missing.
                    $customer = $vote->customer;
                    $nameParts = array_filter([$customer?->first_name, $customer?->last_name]);
                    $name = $nameParts ? implode(' ', $nameParts) : ($customer?->email ?? 'Customer');

                    fputcsv($handle, [
                        $vote->option?->label,
                        $name,
                        $customer?->email,
                        $customer?->tier?->title,
                        optional($vote->voted_at)->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, $headers);
    }

    // This deletes a chat message and its related records via model cascades.
    public function destroy(ChatMessage $message)
    {
        $message->delete();

        return redirect()->route('exclusive-chat');
    }

    // This returns the global chat settings, creating defaults if missing.
    private function getSettings(): ChatSetting
    {
        return ChatSetting::firstOrCreate(
            ['store_id' => null],
            ['enabled' => false, 'allowed_tiers' => []]
        );
    }
}

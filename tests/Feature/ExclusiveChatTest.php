<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatPoll;
use App\Models\ChatPollOption;
use App\Models\ChatPollVote;
use App\Models\ChatSetting;
use App\Models\Customer;
use App\Models\Tier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class ExclusiveChatTest extends TestCase
{
    use RefreshDatabase;

    private function makeToken(Customer $customer): string
    {
        return Crypt::encryptString(json_encode([
            'shopify_id' => $customer->shopify_id,
            'email' => $customer->email,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]));
    }

    public function test_chat_messages_are_tier_gated(): void
    {
        $tier = Tier::create([
            'title' => 'Gold',
            'color' => '#fbbf24',
            'min_points' => 0,
            'max_points' => 1000,
            'single_point_value' => 1.00,
            'status' => 'active',
        ]);
        $otherTier = Tier::create([
            'title' => 'Platinum',
            'color' => '#a855f7',
            'min_points' => 1001,
            'max_points' => 2000,
            'single_point_value' => 1.00,
            'status' => 'active',
        ]);

        $customer = Customer::create([
            'shopify_id' => 'chat-1',
            'email' => 'chat@example.com',
            'tier_id' => $tier->id,
        ]);

        ChatSetting::create([
            'store_id' => null,
            'enabled' => true,
            'allowed_tiers' => [$otherTier->id],
        ]);

        ChatMessage::create([
            'store_id' => null,
            'type' => 'TEXT',
            'title' => 'Hello',
            'body' => 'Only platinum should see this.',
            'tier_visibility' => [],
            'sent_at' => now(),
        ]);

        $token = $this->makeToken($customer);
        $response = $this->get('/api/widget/chat/messages?token='.$token);

        $response->assertOk();
        $response->assertJsonFragment(['allowed' => false]);
        $response->assertJsonCount(0, 'data');
    }

    public function test_poll_vote_is_single_use(): void
    {
        $tier = Tier::create([
            'title' => 'Gold',
            'color' => '#fbbf24',
            'min_points' => 0,
            'max_points' => 1000,
            'single_point_value' => 1.00,
            'status' => 'active',
        ]);
        $customer = Customer::create([
            'shopify_id' => 'chat-2',
            'email' => 'vote@example.com',
            'tier_id' => $tier->id,
        ]);

        ChatSetting::create([
            'store_id' => null,
            'enabled' => true,
            'allowed_tiers' => [$tier->id],
        ]);

        $message = ChatMessage::create([
            'store_id' => null,
            'type' => 'POLL',
            'title' => 'Vote',
            'body' => 'Pick one.',
            'tier_visibility' => [$tier->id],
            'sent_at' => now(),
        ]);

        $poll = ChatPoll::create([
            'chat_message_id' => $message->id,
            'allow_multiple' => false,
        ]);

        $optionA = ChatPollOption::create([
            'chat_poll_id' => $poll->id,
            'label' => 'Option A',
            'sort_order' => 0,
        ]);
        $optionB = ChatPollOption::create([
            'chat_poll_id' => $poll->id,
            'label' => 'Option B',
            'sort_order' => 1,
        ]);

        $token = $this->makeToken($customer);
        $response = $this->post('/api/widget/chat/polls/'.$poll->id.'/vote?token='.$token, [
            'option_id' => $optionA->id,
        ]);
        $response->assertOk();
        $response->assertJsonFragment(['my_vote_option_id' => $optionA->id]);

        $second = $this->post('/api/widget/chat/polls/'.$poll->id.'/vote?token='.$token, [
            'option_id' => $optionB->id,
        ]);
        $second->assertOk();
        $second->assertJsonFragment(['my_vote_option_id' => $optionA->id]);
        $this->assertSame(1, ChatPollVote::count());
    }

    public function test_closed_poll_rejects_vote(): void
    {
        $tier = Tier::create([
            'title' => 'Gold',
            'color' => '#fbbf24',
            'min_points' => 0,
            'max_points' => 1000,
            'single_point_value' => 1.00,
            'status' => 'active',
        ]);
        $customer = Customer::create([
            'shopify_id' => 'chat-3',
            'email' => 'closed@example.com',
            'tier_id' => $tier->id,
        ]);

        ChatSetting::create([
            'store_id' => null,
            'enabled' => true,
            'allowed_tiers' => [$tier->id],
        ]);

        $message = ChatMessage::create([
            'store_id' => null,
            'type' => 'POLL',
            'title' => 'Closed',
            'body' => 'Poll closed.',
            'tier_visibility' => [$tier->id],
            'sent_at' => now(),
        ]);

        $poll = ChatPoll::create([
            'chat_message_id' => $message->id,
            'allow_multiple' => false,
            'closes_at' => now()->subDay(),
        ]);

        $option = ChatPollOption::create([
            'chat_poll_id' => $poll->id,
            'label' => 'Option A',
            'sort_order' => 0,
        ]);

        $token = $this->makeToken($customer);
        $response = $this->post('/api/widget/chat/polls/'.$poll->id.'/vote?token='.$token, [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_poll_analytics_counts_votes(): void
    {
        $user = User::factory()->create();

        $message = ChatMessage::create([
            'store_id' => null,
            'type' => 'POLL',
            'title' => 'Analytics',
            'body' => 'Analytics poll.',
            'tier_visibility' => [],
            'sent_at' => now(),
        ]);

        $poll = ChatPoll::create([
            'chat_message_id' => $message->id,
            'allow_multiple' => false,
        ]);

        $optionA = ChatPollOption::create([
            'chat_poll_id' => $poll->id,
            'label' => 'Option A',
            'sort_order' => 0,
        ]);
        $optionB = ChatPollOption::create([
            'chat_poll_id' => $poll->id,
            'label' => 'Option B',
            'sort_order' => 1,
        ]);

        ChatPollVote::create([
            'chat_poll_id' => $poll->id,
            'option_id' => $optionA->id,
            'customer_id' => Customer::create(['shopify_id' => 'c1', 'email' => 'c1@example.com'])->id,
            'voted_at' => now(),
        ]);
        ChatPollVote::create([
            'chat_poll_id' => $poll->id,
            'option_id' => $optionB->id,
            'customer_id' => Customer::create(['shopify_id' => 'c2', 'email' => 'c2@example.com'])->id,
            'voted_at' => now(),
        ]);
        ChatPollVote::create([
            'chat_poll_id' => $poll->id,
            'option_id' => $optionB->id,
            'customer_id' => Customer::create(['shopify_id' => 'c3', 'email' => 'c3@example.com'])->id,
            'voted_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/admin/api/chat/polls/'.$poll->id.'/analytics');
        $response->assertOk();
        $response->assertJsonFragment(['total_votes' => 3]);
        $response->assertJsonFragment(['option_id' => $optionA->id, 'count' => 1]);
        $response->assertJsonFragment(['option_id' => $optionB->id, 'count' => 2]);
    }
}

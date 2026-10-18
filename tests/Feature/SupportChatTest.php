<?php

namespace Tests\Feature;

use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private User $other;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create([
            'name' => 'Cust', 'email' => 'cust@test.com', 'password' => 'password',
            'role' => 'customer', 'is_active' => true,
        ]);
        $this->other = User::create([
            'name' => 'Other', 'email' => 'other@test.com', 'password' => 'password',
            'role' => 'customer', 'is_active' => true,
        ]);
        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => 'password',
            'role' => 'admin', 'is_active' => true,
        ]);
    }

    public function test_customer_sends_and_lists_own_messages_only(): void
    {
        SupportMessage::create([
            'customer_id' => $this->other->id, 'sender_id' => $this->other->id,
            'from_customer' => true, 'body' => 'pesan orang lain',
        ]);

        Sanctum::actingAs($this->customer);
        $this->postJson('/api/support/messages', ['body' => 'Halo CS'])->assertCreated();

        $res = $this->getJson('/api/support/messages')->assertOk();
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Halo CS', $res->json('data.0.body'));
        $this->assertTrue($res->json('data.0.from_customer'));
    }

    public function test_empty_message_rejected(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/support/messages', ['body' => ''])->assertStatus(422);
    }

    public function test_admin_reply_shows_as_unread_then_read(): void
    {
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/support/messages', ['body' => 'Pesanan saya belum datang']);

        $this->actingAs($this->admin, 'web')
            ->post(route('admin.support.reply', $this->customer), ['body' => 'Sedang kami cek'])
            ->assertRedirect();

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/support/unread-count')->assertJsonPath('data.unread_count', 1);

        $res = $this->getJson('/api/support/messages');
        $this->assertFalse($res->json('data.1.from_customer'));
        $this->getJson('/api/support/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_after_id_returns_only_newer(): void
    {
        Sanctum::actingAs($this->customer);
        $first = $this->postJson('/api/support/messages', ['body' => 'satu'])->json('data.id');
        $this->postJson('/api/support/messages', ['body' => 'dua']);

        $res = $this->getJson('/api/support/messages?after_id='.$first);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('dua', $res->json('data.0.body'));
    }

    public function test_admin_page_lists_threads(): void
    {
        SupportMessage::create([
            'customer_id' => $this->customer->id, 'sender_id' => $this->customer->id,
            'from_customer' => true, 'body' => 'Tolong bantu',
        ]);

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.support.index'))
            ->assertOk()
            ->assertSee('Cust')
            ->assertSee('Tolong bantu');

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.support.index', ['customer' => $this->customer->id]))
            ->assertOk()
            ->assertSee('Tolong bantu');

        $this->assertNotNull(SupportMessage::first()->fresh()->read_at);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Page;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_home_page_returns_payload_style_layout(): void
    {
        $this->getJson('/api/v1/pages/home')
            ->assertOk()
            ->assertJsonPath('data.hero.type', 'highImpact')
            ->assertJsonStructure(['data' => ['layout' => [['blockType', 'id']]]]);
    }

    public function test_archive_block_is_populated(): void
    {
        $layout = collect($this->getJson('/api/v1/pages/home')->json('data.layout'));
        $programs = $layout->firstWhere('relationTo', 'programs');

        $this->assertNotEmpty($programs['docs']);
    }

    public function test_draft_pages_are_hidden(): void
    {
        Page::where('slug', 'tentang')->update(['status' => 'draft']);
        $this->getJson('/api/v1/pages/tentang')->assertNotFound();
    }

    public function test_collections_and_globals(): void
    {
        $this->getJson('/api/v1/globals')->assertOk()->assertJsonPath('data.site.name', 'BPC HIPMI Bantul');
        $this->getJson('/api/v1/posts')->assertOk()->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page']]);
        $this->getJson('/api/v1/events')->assertOk();
        $this->getJson('/api/v1/programs')->assertOk();
        $this->getJson('/api/v1/board-members')->assertOk();
    }

    public function test_membership_registration(): void
    {
        $payload = [
            'type' => 'membership', 'name' => 'Budi', 'email' => 'budi@example.com', 'phone' => '081234567890',
            'company_name' => 'Budi Snack', 'business_field' => 'F&B',
        ];

        $this->postJson('/api/v1/registrations', $payload)->assertCreated();
        $this->postJson('/api/v1/registrations', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_event_registration_respects_quota(): void
    {
        $event = Event::first();
        $event->update(['quota' => 1]);

        $this->postJson('/api/v1/registrations', ['type' => 'event', 'event_id' => $event->id, 'name' => 'A', 'email' => 'a@x.com', 'phone' => '081234567890'])->assertCreated();
        $this->postJson('/api/v1/registrations', ['type' => 'event', 'event_id' => $event->id, 'name' => 'B', 'email' => 'b@x.com', 'phone' => '081234567891'])
            ->assertUnprocessable()->assertJsonValidationErrors('event_id');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->postJson('/api/v1/registrations', [
            'type' => 'membership', 'name' => 'Bot', 'email' => 'bot@x.com', 'phone' => '081234567890',
            'company_name' => 'X', 'business_field' => 'Y', 'website' => 'spam',
        ])->assertUnprocessable();
    }
}

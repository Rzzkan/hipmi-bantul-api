<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_screens_render(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create());

        $urls = [
            '/admin', '/admin/settings',
            '/admin/pages', '/admin/pages/create', '/admin/pages/'.Page::first()->id.'/edit',
            '/admin/posts', '/admin/posts/create', '/admin/posts/'.Post::first()->id.'/edit',
            '/admin/events', '/admin/events/create', '/admin/events/'.Event::first()->id.'/edit',
            '/admin/programs', '/admin/programs/create',
            '/admin/board-members', '/admin/board-members/create',
            '/admin/partners', '/admin/partners/create',
            '/admin/registrations',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_editing_page_keeps_block_layout(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create());
        $page = Page::where('slug', 'home')->first();

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Beranda Baru'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->getJson('/api/v1/pages/home')
            ->assertJsonPath('data.title', 'Beranda Baru')
            ->assertJsonPath('data.layout.0.blockType', 'stats')
            ->assertJsonPath('data.layout.2.blockType', 'archive');
    }

    public function test_settings_page_saves_globals(): void
    {
        $this->seed(ContentSeeder::class);
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageSettings::class)
            ->set('data.site.tagline', 'Tagline baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/globals')->assertJsonPath('data.site.tagline', 'Tagline baru')
            ->assertJsonPath('data.header.navItems.0.label', 'Beranda');
    }
}

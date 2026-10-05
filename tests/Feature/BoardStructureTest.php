<?php

namespace Tests\Feature;

use App\Filament\Resources\BoardMembers\Pages\CreateBoardMember;
use App\Filament\Resources\Divisions\Pages\EditDivision;
use App\Models\BoardMember;
use App\Models\Compartment;
use App\Models\Division;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    public function test_seeder_creates_twelve_divisions_and_core_positions(): void
    {
        $this->assertSame(12, Division::count());
        $this->assertSame('Investasi dan Kerjasama antar Daerah', Division::where('number', 12)->value('name'));

        $res = $this->getJson('/api/v1/board-members')->assertOk();
        $this->assertSame(['Ketua Umum', 'Sekretaris Umum', 'Bendahara'], array_column($res->json('data.inti'), 'position'));
        $this->assertCount(12, $res->json('data.divisions'));
        $this->assertSame('Bidang 1', $res->json('data.divisions.0.label'));
    }

    public function test_members_are_placed_in_division_and_compartments_with_socials(): void
    {
        $division = Division::where('number', 9)->first();
        $koperasi = Compartment::create(['division_id' => $division->id, 'name' => 'Koperasi', 'sort_order' => 1]);
        $umkm = Compartment::create(['division_id' => $division->id, 'name' => 'UMKM Digital', 'sort_order' => 0]);

        BoardMember::create(['name' => 'Ketua Sembilan', 'position' => 'Ketua Bidang', 'level' => 'bidang', 'division_id' => $division->id, 'compartment_id' => $koperasi->id]);
        BoardMember::create([
            'name' => 'Anggota Koperasi', 'position' => 'Ketua Kompartemen', 'level' => 'kompartemen', 'compartment_id' => $koperasi->id,
            'instagram' => '@hipmi.bantul', 'tiktok' => 'hipmibantul', 'linkedin' => 'https://www.linkedin.com/in/hipmi-bantul/',
        ]);

        $bidang9 = collect($this->getJson('/api/v1/board-members')->json('data.divisions'))->firstWhere('number', 9);

        $this->assertSame('Ketua Sembilan', $bidang9['leaders'][0]['name']);
        $this->assertNull($bidang9['leaders'][0]['compartmentId'], 'Ketua bidang tidak boleh terikat kompartemen');
        $this->assertSame(['UMKM Digital', 'Koperasi'], array_column($bidang9['compartments'], 'name'));

        $member = $bidang9['compartments'][1]['members'][0];
        $this->assertSame($division->id, $member['divisionId'], 'Bidang otomatis mengikuti kompartemen');
        $this->assertSame(['handle' => 'hipmi.bantul', 'url' => 'https://instagram.com/hipmi.bantul'], $member['socials']['instagram']);
        $this->assertSame('https://www.tiktok.com/@hipmibantul', $member['socials']['tiktok']['url']);
        $this->assertSame('hipmi-bantul', $member['socials']['linkedin']['handle']);
    }

    public function test_tentang_page_team_block_contains_structure(): void
    {
        $team = collect($this->getJson('/api/v1/pages/tentang')->json('data.layout'))->firstWhere('blockType', 'team');

        $this->assertCount(3, $team['structure']['inti']);
        $this->assertCount(12, $team['structure']['divisions']);
    }

    public function test_admin_screens_for_structure_render(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/divisions')->assertOk();
        $this->get('/admin/divisions/create')->assertOk();
        $this->get('/admin/divisions/'.Division::first()->id.'/edit')->assertOk();
        $this->get('/admin/board-members')->assertOk();
        $this->get('/admin/board-members/'.BoardMember::first()->id.'/edit')->assertOk();
    }

    public function test_admin_can_add_compartments_to_a_division(): void
    {
        $this->actingAs(User::factory()->create());
        $division = Division::where('number', 1)->first();

        Livewire::test(EditDivision::class, ['record' => $division->getRouteKey()])
            ->set('data.compartments', [
                'a' => ['name' => 'Kaderisasi'],
                'b' => ['name' => 'Keanggotaan'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Kaderisasi', 'Keanggotaan'], $division->compartments()->pluck('name')->all());
    }

    public function test_admin_can_create_compartment_member(): void
    {
        $this->actingAs(User::factory()->create());
        $division = Division::where('number', 4)->first();
        $comp = Compartment::create(['division_id' => $division->id, 'name' => 'Perdagangan']);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'level' => 'kompartemen',
                'division_id' => $division->id,
                'compartment_id' => $comp->id,
                'position' => 'Ketua Kompartemen',
                'name' => 'Budi',
                'tiktok' => '@budi',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('board_members', ['name' => 'Budi', 'compartment_id' => $comp->id, 'division_id' => $division->id, 'tiktok' => '@budi']);
    }

    public function test_inti_chart_groups_multiple_deputies_under_secretary_and_treasurer(): void
    {
        foreach ([['Wakil Sekretaris Umum I', 1], ['Wakil Sekretaris Umum II', 2], ['Wakil Bendahara I', 3], ['Wakil Bendahara II', 4], ['Wakil Bendahara III', 5]] as [$pos, $order]) {
            BoardMember::create(['name' => "Orang {$pos}", 'position' => $pos, 'level' => 'inti', 'sort_order' => 10 + $order]);
        }

        $chart = $this->getJson('/api/v1/board-members')->assertOk()->json('data.intiChart');

        $this->assertSame(['Ketua Umum'], array_column($chart['ketua'], 'position'));
        $this->assertSame(['Sekretaris Umum'], array_column($chart['sekretaris']['heads'], 'position'));
        $this->assertSame(['Wakil Sekretaris Umum I', 'Wakil Sekretaris Umum II'], array_column($chart['sekretaris']['deputies'], 'position'));
        $this->assertSame(['Bendahara'], array_column($chart['bendahara']['heads'], 'position'));
        $this->assertCount(3, $chart['bendahara']['deputies']);
        $this->assertSame([], $chart['others']);
    }
}

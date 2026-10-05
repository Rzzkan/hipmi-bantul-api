<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_okk_admin_account(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = User::where('email', 'okkhipmibantul@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('okkbergerak', $user->password));
        $this->assertNotSame('okkbergerak', $user->password, 'Password harus tersimpan dalam bentuk hash');
    }

    public function test_admin_can_log_in_and_open_panel(): void
    {
        $this->seed(AdminUserSeeder::class);

        $this->assertTrue(auth()->attempt(['email' => 'okkhipmibantul@gmail.com', 'password' => 'okkbergerak']));
        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk();
    }

    public function test_login_form_accepts_the_credentials(): void
    {
        $this->seed(AdminUserSeeder::class);

        Livewire::test(Login::class)
            ->fillForm(['email' => 'okkhipmibantul@gmail.com', 'password' => 'okkbergerak'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_reseeding_does_not_overwrite_a_changed_password(): void
    {
        $this->seed(AdminUserSeeder::class);
        User::where('email', 'okkhipmibantul@gmail.com')->update(['password' => Hash::make('passwordbaru123')]);

        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::count());
        $this->assertTrue(Hash::check('passwordbaru123', User::first()->password));
    }
}

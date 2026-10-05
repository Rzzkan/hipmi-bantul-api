<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@hipmibantul.id')],
            ['name' => 'Admin HIPMI Bantul', 'password' => env('ADMIN_PASSWORD', 'password')],
        );

        $this->call(ContentSeeder::class);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun login admin CMS (/admin).
 *
 * Default: okkhipmibantul@gmail.com / okkbergerak
 * Bisa diganti lewat .env (ADMIN_EMAIL, ADMIN_PASSWORD) sebelum seeding.
 *
 * Jalankan sendiri di server:  php artisan db:seed --class=AdminUserSeeder --force
 *
 * Password hanya di-set saat akun pertama kali dibuat — kalau password sudah
 * diganti lewat menu Profil di admin, menjalankan seeder lagi tidak menimpanya.
 */
class AdminUserSeeder extends Seeder
{
    public const DEFAULT_EMAIL = 'okkhipmibantul@gmail.com';

    public const DEFAULT_PASSWORD = 'okkbergerak';

    public function run(): void
    {
        // `?:` (bukan default env()) supaya baris kosong `ADMIN_PASSWORD=` di .env tetap memakai default.
        $email = env('ADMIN_EMAIL') ?: self::DEFAULT_EMAIL;
        $password = env('ADMIN_PASSWORD') ?: self::DEFAULT_PASSWORD;

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'OKK HIPMI Bantul';
        $user->email_verified_at ??= now();

        if (! $user->exists) {
            $user->password = $password; // di-hash otomatis oleh cast 'hashed'
        }

        $user->save();

        $this->command?->info(($user->wasRecentlyCreated ? 'Akun admin dibuat: ' : 'Akun admin sudah ada: ').$email);
    }
}

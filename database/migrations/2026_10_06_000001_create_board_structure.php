<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur pengurus BPC: Pengurus Inti + Bidang (1..n) + Kompartemen per bidang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->nullable(); // "Bidang 1", "Bidang 2", ...
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('compartments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('board_members', function (Blueprint $table) {
            // inti = Ketua Umum/Sekum/Bendahara · bidang = Ketua/Wakil bidang · kompartemen = pengurus kompartemen
            $table->string('level')->default('inti')->after('position')->index();
            $table->foreignId('division_id')->nullable()->after('level')->constrained()->nullOnDelete();
            $table->foreignId('compartment_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
            $table->string('tiktok')->nullable()->after('instagram');
        });

        // Data lama (kolom `division` berisi teks bebas) tidak bisa dipetakan otomatis ke bidang baru:
        // pengurus non-inti dinonaktifkan supaya tidak tampil salah; atur ulang bidangnya di admin.
        DB::table('board_members')->whereNotNull('division')->where('division', '!=', 'inti')
            ->update(['is_active' => false]);

        Schema::table('board_members', function (Blueprint $table) {
            $table->dropIndex(['division']);
            $table->dropColumn('division');
        });
    }

    public function down(): void
    {
        Schema::table('board_members', function (Blueprint $table) {
            $table->string('division')->nullable()->index();
            $table->dropIndex(['level']);
            $table->dropConstrainedForeignId('compartment_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn(['level', 'tiktok']);
        });
        Schema::dropIfExists('compartments');
        Schema::dropIfExists('divisions');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardMember extends Model
{
    public const LEVEL_INTI = 'inti';

    public const LEVEL_BIDANG = 'bidang';

    public const LEVEL_KOMPARTEMEN = 'kompartemen';

    public const LEVELS = [
        self::LEVEL_INTI => 'Pengurus Inti',
        self::LEVEL_BIDANG => 'Pimpinan Bidang',
        self::LEVEL_KOMPARTEMEN => 'Kompartemen',
    ];

    /** Saran jabatan per level (bisa diketik bebas di admin). */
    public const POSITION_SUGGESTIONS = [
        // Tidak ada Wakil Ketua Umum. Wakil Sekretaris & Wakil Bendahara boleh lebih dari satu.
        self::LEVEL_INTI => ['Ketua Umum', 'Sekretaris Umum', 'Wakil Sekretaris Umum', 'Bendahara', 'Wakil Bendahara'],
        self::LEVEL_BIDANG => ['Ketua Bidang', 'Wakil Ketua Bidang', 'Sekretaris Bidang'],
        self::LEVEL_KOMPARTEMEN => ['Ketua Kompartemen', 'Wakil Ketua Kompartemen', 'Anggota Kompartemen'],
    ];

    protected $fillable = [
        'name', 'position', 'level', 'division_id', 'compartment_id', 'photo', 'company', 'bio',
        'instagram', 'tiktok', 'linkedin', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Jaga konsistensi: inti tidak punya bidang; bidang tidak punya kompartemen;
        // kompartemen selalu ikut bidang induknya.
        static::saving(function (BoardMember $m) {
            if ($m->level === self::LEVEL_INTI) {
                $m->division_id = null;
                $m->compartment_id = null;
            } elseif ($m->level === self::LEVEL_BIDANG) {
                $m->compartment_id = null;
            } elseif ($m->level === self::LEVEL_KOMPARTEMEN && $m->compartment_id) {
                $m->division_id = Compartment::query()->whereKey($m->compartment_id)->value('division_id');
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function compartment(): BelongsTo
    {
        return $this->belongsTo(Compartment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}

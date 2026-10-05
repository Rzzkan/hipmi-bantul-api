<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Registration extends Model
{
    public const TYPES = [
        'membership' => 'Calon Anggota',
        'event' => 'Peserta Agenda',
        'program' => 'Peserta Program',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_APPROVED => 'Diterima',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    protected $fillable = [
        'type', 'event_id', 'program_id', 'name', 'email', 'phone', 'company_name', 'business_field',
        'position', 'age', 'address', 'message', 'status', 'admin_notes',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}

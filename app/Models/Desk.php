<?php

namespace App\Models;

use App\TicketStatus;
use Database\Factories\DeskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Desk extends Model
{
    /** @use HasFactory<DeskFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function openAttendances(): HasMany
    {
        return $this->hasMany(Ticket::class, 'current_desk_id')
            ->whereIn('status', [TicketStatus::CALLED, TicketStatus::IN_SERVICE]);
    }

    public function hasOpenAttendance(): bool
    {
        return $this->openAttendances()
            ->where('clinic_id', $this->clinic_id)
            ->exists();
    }

    public function hasWaitingTargetedTickets(): bool
    {
        return Ticket::query()
            ->where('clinic_id', $this->clinic_id)
            ->where('target_desk_id', $this->id)
            ->where('status', TicketStatus::WAITING)
            ->exists();
    }
}

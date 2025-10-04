<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_date',
        'title',
        'background_image',
        'is_closed',
    ];

    protected $casts = [
        'event_date' => 'date',
        'is_closed' => 'boolean',
    ];

    /**
     * Get the users registered for this event.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope to get only open events.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Scope to get events after a specific date.
     */
    public function scopeAfterDate($query, $date)
    {
        return $query->where('event_date', '>=', $date);
    }

    /**
     * Get the background image URL.
     */
    public function getBackgroundImageUrlAttribute(): ?string
    {
        if (!$this->background_image) {
            return null;
        }

        if (filter_var($this->background_image, FILTER_VALIDATE_URL)) {
            return $this->background_image;
        }

        return asset('storage/' . $this->background_image);
    }

    /**
     * Get formatted event date.
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->event_date->format('d-m-Y');
    }

    /**
     * Get event statistics.
     */
    public function getStatsAttribute(): array
    {
        return [
            'total_registrations' => $this->users()->count(),
            'validated_registrations' => $this->users()->where('is_validated', true)->count(),
            'pending_registrations' => $this->users()->where('is_validated', false)->count(),
            'emails_sent' => $this->users()->where('email_status', 'sent')->count(),
            'emails_failed' => $this->users()->where('email_status', 'failed')->count(),
        ];
    }
}







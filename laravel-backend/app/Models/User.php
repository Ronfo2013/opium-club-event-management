<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class User extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'event_id',
        'qr_token',
        'qr_code_path',
        'is_validated',
        'validated_at',
        'email_sent_at',
        'email_status',
        'email_error',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_validated' => 'boolean',
        'validated_at' => 'datetime',
        'email_sent_at' => 'datetime',
    ];

    /**
     * Get the event this user is registered for.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Generate a unique QR token.
     */
    public static function generateQrToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get formatted phone number.
     */
    public function getFormattedPhoneAttribute(): string
    {
        // Remove any non-digit characters
        $phone = preg_replace('/[^\d]/', '', $this->phone);
        
        // Add +39 prefix if not present
        if (!str_starts_with($phone, '39')) {
            $phone = '39' . $phone;
        }
        
        return '+' . $phone;
    }

    /**
     * Get age from birth date.
     */
    public function getAgeAttribute(): int
    {
        return $this->birth_date->age;
    }

    /**
     * Check if user can be validated.
     */
    public function canBeValidated(): bool
    {
        return !$this->is_validated;
    }

    /**
     * Mark user as validated.
     */
    public function markAsValidated(): void
    {
        $this->update([
            'is_validated' => true,
            'validated_at' => now(),
        ]);
    }

    /**
     * Mark email as sent.
     */
    public function markEmailAsSent(): void
    {
        $this->update([
            'email_sent_at' => now(),
            'email_status' => 'sent',
            'email_error' => null,
        ]);
    }

    /**
     * Mark email as failed.
     */
    public function markEmailAsFailed(string $error): void
    {
        $this->update([
            'email_status' => 'failed',
            'email_error' => $error,
        ]);
    }

    /**
     * Scope for validated users.
     */
    public function scopeValidated($query)
    {
        return $query->where('is_validated', true);
    }

    /**
     * Scope for pending validation.
     */
    public function scopePendingValidation($query)
    {
        return $query->where('is_validated', false);
    }

    /**
     * Scope for email status.
     */
    public function scopeEmailStatus($query, string $status)
    {
        return $query->where('email_status', $status);
    }
}







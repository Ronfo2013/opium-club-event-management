<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdaySent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_email',
        'user_name',
        'birthday_date',
        'sent_year',
        'template_id',
        'sent_at',
    ];

    protected $casts = [
        'birthday_date' => 'date',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the template used for this birthday message.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(BirthdayTemplate::class, 'template_id');
    }

    /**
     * Check if birthday was already sent for a user in a specific year.
     */
    public static function wasSentForUser(string $email, int $year): bool
    {
        return self::where('user_email', $email)
            ->where('sent_year', $year)
            ->exists();
    }

    /**
     * Mark birthday as sent for a user.
     */
    public static function markAsSent(
        string $email,
        string $name,
        string $birthdayDate,
        int $year,
        ?int $templateId = null
    ): self {
        return self::create([
            'user_email' => $email,
            'user_name' => $name,
            'birthday_date' => $birthdayDate,
            'sent_year' => $year,
            'template_id' => $templateId,
            'sent_at' => now(),
        ]);
    }
}







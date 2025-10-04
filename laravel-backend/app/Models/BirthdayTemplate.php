<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BirthdayTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'html_content',
        'background_image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the birthday sent records for this template.
     */
    public function birthdaySent(): HasMany
    {
        return $this->hasMany(BirthdaySent::class, 'template_id');
    }

    /**
     * Get the active template.
     */
    public static function getActive(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /**
     * Set this template as active and deactivate others.
     */
    public function setAsActive(): void
    {
        // Deactivate all templates
        self::where('is_active', true)->update(['is_active' => false]);
        
        // Activate this template
        $this->update(['is_active' => true]);
    }

    /**
     * Get background image URL.
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
}







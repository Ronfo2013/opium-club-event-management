<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BirthdayAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    /**
     * Get the file URL.
     */
    public function getFileUrlAttribute(): string
    {
        if (filter_var($this->file_path, FILTER_VALIDATE_URL)) {
            return $this->file_path;
        }

        return asset('storage/' . $this->file_path);
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image.
     */
    public function isImage(): bool
    {
        return in_array($this->file_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }
}







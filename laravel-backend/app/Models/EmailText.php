<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailText extends Model
{
    use HasFactory;

    protected $fillable = [
        'text_key',
        'text_value',
    ];

    /**
     * Get email text by key.
     */
    public static function getByKey(string $key, string $default = ''): string
    {
        $emailText = self::where('text_key', $key)->first();
        
        return $emailText ? $emailText->text_value : $default;
    }

    /**
     * Set email text by key.
     */
    public static function setByKey(string $key, string $value): void
    {
        self::updateOrCreate(
            ['text_key' => $key],
            ['text_value' => $value]
        );
    }

    /**
     * Get all email texts as array.
     */
    public static function getAllAsArray(): array
    {
        return self::pluck('text_value', 'text_key')->toArray();
    }

    /**
     * Replace variables in text.
     */
    public static function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', $value, $text);
        }
        
        return $text;
    }
}







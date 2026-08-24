<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Leer un setting por su key, cacheado indefinidamente hasta que se
     * escriba con set(). Un valor nunca guardado devuelve $default sin
     * cachear (para no "pegar" un default de un caller distinto).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting.{$key}", fn () => static::where('key', $key)->value('value'));

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("setting.{$key}");
    }
}

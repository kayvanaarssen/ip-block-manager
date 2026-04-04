<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting.{$key}", function () use ($key, $default) {
            $setting = static::find($key);
            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting.{$key}");
    }

    public static function getAll(): array
    {
        return [
            'app_name' => static::get('app_name', config('app.name', 'IPBlock')),
            'logo_light' => static::get('logo_light'),
            'logo_dark' => static::get('logo_dark'),
        ];
    }
}

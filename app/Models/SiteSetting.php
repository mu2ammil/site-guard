<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function read(string $key, string $default): string
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }
}

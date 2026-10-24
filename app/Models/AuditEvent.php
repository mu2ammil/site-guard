<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'type', 'ip_address', 'user_agent', 'path', 'details', 'created_at'];

    protected function casts(): array
    {
        return ['details' => 'array', 'created_at' => 'datetime'];
    }

    /** @param array<string, mixed> $details */
    public static function record(Request $request, string $type, array $details = []): void
    {
        static::create([
            'user_id' => $request->user()?->id,
            'type' => $type,
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'path' => substr($request->path(), 0, 255),
            'details' => $details,
            'created_at' => now(),
        ]);
    }
}

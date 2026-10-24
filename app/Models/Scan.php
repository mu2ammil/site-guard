<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = ['user_id', 'target', 'host', 'ip_address', 'user_agent', 'status', 'http_status', 'grade', 'report', 'error'];

    protected function casts(): array
    {
        return ['report' => 'array'];
    }
}

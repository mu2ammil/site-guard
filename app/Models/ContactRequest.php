<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactRequest extends Model
{
    protected $fillable = ['name', 'email', 'site', 'message', 'ip_address', 'user_agent'];
}

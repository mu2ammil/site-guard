<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoredSite extends Model
{
    protected $fillable = ['user_id', 'domain', 'verification_token', 'verified_at', 'alerts_enabled'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'alerts_enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasValidDnsRecord(): bool
    {
        $records = dns_get_record('_siteguard.'.$this->domain, DNS_TXT) ?: [];

        return collect($records)->contains(
            fn (array $record) => hash_equals($this->verification_token, (string) ($record['txt'] ?? '')),
        );
    }
}

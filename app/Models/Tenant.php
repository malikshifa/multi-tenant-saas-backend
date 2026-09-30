<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Tenant extends Model
{
    use LogsActivity, SoftDeletes;

    protected $hidden = [
        'database_host',
        'database_port',
        'database_username',
        'database_password',
    ];

    protected $fillable = [
        'name',
        'slug',
        'status',
        'database_name',
        'database_host',
        'database_port',
        'database_username',
        'database_password',
        'provisioned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'database_password' => 'encrypted',
            'provisioned_at' => 'datetime',
            'database_port' => 'integer',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function primaryDomain()
    {
        return $this->hasOne(Domain::class)
            ->where('is_primary', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', [
                SubscriptionStatus::TRIALING->value,
                SubscriptionStatus::ACTIVE->value,
            ])
            ->latestOfMany();
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never log database_* columns: they include decrypted credentials.
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'status', 'provisioned_at'])
            ->logOnlyDirty();
    }
}

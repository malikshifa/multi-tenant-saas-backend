<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTenantConnection;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

class Activity extends SpatieActivity
{
    use ResolvesTenantConnection;
}

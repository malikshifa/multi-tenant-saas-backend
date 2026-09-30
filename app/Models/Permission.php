<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTenantConnection;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use ResolvesTenantConnection;
}

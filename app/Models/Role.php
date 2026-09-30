<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTenantConnection;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use ResolvesTenantConnection;
}

<?php

namespace App\Models;

use App\Models\Concerns\ResolvesTenantConnection;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use ResolvesTenantConnection;
}

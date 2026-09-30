<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantDatabaseManager
{
    public function connect(Tenant $tenant): void
    {
        Config::set('database.connections.tenant', [
            'driver' => 'mysql',
            'host' => $tenant->database_host,
            'port' => $tenant->database_port,
            'database' => $tenant->database_name,
            'username' => $tenant->database_username,
            'password' => $tenant->database_password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);

        DB::purge('tenant');

        DB::reconnect('tenant');
    }

    public function disconnect(): void
    {
        DB::purge('tenant');
    }

    public function createDatabase(Tenant $tenant): void
    {
        $database = str_replace('`', '``', $tenant->database_name);

        DB::connection('mysql')->statement(
            "CREATE DATABASE `$database`
             CHARACTER SET utf8mb4
             COLLATE utf8mb4_unicode_ci"
        );
    }

    public function dropDatabase(Tenant $tenant): void
    {
        $database = str_replace('`', '``', $tenant->database_name);

        DB::connection('mysql')->statement(
            "DROP DATABASE IF EXISTS `$database`"
        );
    }
}

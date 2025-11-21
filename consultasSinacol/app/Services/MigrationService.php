<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class MigrationService
{
    public function hasMigration($migrationName)
    {
        $migration = DB::table('migrations')->where('migration', 'like', '%'.$migrationName.'%')->first();

        return $migration ? true : false;
    }
}

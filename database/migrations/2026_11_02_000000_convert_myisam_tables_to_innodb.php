<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Databases first migrated on a server that defaults to MyISAM (e.g. WAMP's MariaDB)
 * have MyISAM tables, which can't take part in the ticket foreign keys. Convert them
 * in place before the ticket tables are created. Data is kept; nothing is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (Schema::getTables(DB::getDatabaseName()) as $table) {
            if (strcasecmp((string) ($table['engine'] ?? ''), 'MyISAM') === 0) {
                DB::statement(sprintf('ALTER TABLE `%s` ENGINE=InnoDB', str_replace('`', '``', $table['name'])));
            }
        }
    }

    public function down(): void
    {
        // Converting back to MyISAM would drop foreign-key support, so this is intentionally one-way.
    }
};

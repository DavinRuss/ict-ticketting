<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Assignment history: which technician a ticket moved from and to, so the log
 * keeps every past assignment even after the ticket is reassigned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->foreignId('from_technician_id')->nullable()->after('to_status')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_technician_id')->nullable()->after('from_technician_id')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('to_technician_id');
            $table->dropConstrainedForeignId('from_technician_id');
        });
    }
};

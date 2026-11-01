<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            // Nullable only for the instant between insert and reference generation (see Ticket::booted()).
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('priority', TicketPriority::values())->default(TicketPriority::Medium->value);
            $table->enum('status', TicketStatus::values())->default(TicketStatus::Open->value)->index();
            $table->foreignId('assigned_to')->nullable()->index()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

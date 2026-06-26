<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('source', 100)->nullable();
            $table->decimal('budget', 12, 2)->default(0);
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('stage', ['new', 'attempted', 'negotiation', 'followup', 'won', 'lost'])->default('new');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['stage']);
            $table->index(['assignee_id']);
            $table->index(['created_at']);
            $table->index(['priority']);
            $table->index(['stage', 'assignee_id', 'created_at'], 'leads_composite_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

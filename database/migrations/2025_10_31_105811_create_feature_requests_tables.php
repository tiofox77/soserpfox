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
        // Feature Requests
        Schema::create('feature_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('status', ['pending', 'under_review', 'planned', 'in_development', 'completed', 'rejected'])->default('pending');
            $table->integer('votes_count')->default(0);
            $table->timestamps();
            
            $table->index(['tenant_id', 'status']);
            $table->index('votes_count');
        });
        
        // Votes
        Schema::create('feature_request_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('feature_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['request_id', 'user_id']);
        });
        
        // Comments
        Schema::create('feature_request_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('feature_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->timestamps();
            
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_request_comments');
        Schema::dropIfExists('feature_request_votes');
        Schema::dropIfExists('feature_requests');
    }
};

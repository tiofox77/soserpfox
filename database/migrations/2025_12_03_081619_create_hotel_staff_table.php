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
        Schema::create('hotel_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('document')->nullable();
            $table->string('photo')->nullable();
            $table->string('position'); // receptionist, housekeeper, maintenance, manager, etc
            $table->string('department')->nullable(); // front_desk, housekeeping, maintenance, food_service
            $table->text('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('hire_date')->nullable();
            $table->json('working_days')->nullable();
            $table->time('work_start')->nullable();
            $table->time('work_end')->nullable();
            $table->decimal('hourly_rate', 10, 2)->default(0);
            $table->decimal('monthly_salary', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('skills')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_staff');
    }
};

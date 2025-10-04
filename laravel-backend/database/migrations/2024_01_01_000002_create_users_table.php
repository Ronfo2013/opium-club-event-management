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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->date('birth_date');
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('qr_token')->unique();
            $table->string('qr_code_path');
            $table->boolean('is_validated')->default(false);
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->enum('email_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('email_error')->nullable();
            $table->timestamps();
            
            $table->index(['email', 'event_id']);
            $table->index('qr_token');
            $table->index('email_status');
            $table->index('is_validated');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};







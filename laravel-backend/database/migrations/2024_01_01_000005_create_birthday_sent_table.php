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
        Schema::create('birthday_sent', function (Blueprint $table) {
            $table->id();
            $table->string('user_email');
            $table->string('user_name');
            $table->date('birthday_date');
            $table->year('sent_year');
            $table->foreignId('template_id')->nullable()->constrained('birthday_templates');
            $table->timestamp('sent_at')->useCurrent();
            
            $table->unique(['user_email', 'sent_year'], 'unique_birthday_year');
            $table->index('user_email');
            $table->index('sent_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('birthday_sent');
    }
};







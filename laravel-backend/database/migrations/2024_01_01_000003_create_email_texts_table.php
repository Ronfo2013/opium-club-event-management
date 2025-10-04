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
        Schema::create('email_texts', function (Blueprint $table) {
            $table->id();
            $table->string('text_key')->unique();
            $table->text('text_value');
            $table->timestamps();
            
            $table->index('text_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_texts');
    }
};







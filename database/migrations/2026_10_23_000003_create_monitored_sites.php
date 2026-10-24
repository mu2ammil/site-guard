<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 253)->index();
            $table->string('verification_token', 80);
            $table->timestamp('verified_at')->nullable();
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_sites');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target', 2048);
            $table->string('host', 253)->index();
            $table->string('ip_address', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->char('grade', 1)->nullable();
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['created_at', 'host']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};

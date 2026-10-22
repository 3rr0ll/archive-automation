<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_search_console_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('client_email')->unique();
            $table->longText('credentials');
            $table->string('owner_email')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_accounts');
    }
};

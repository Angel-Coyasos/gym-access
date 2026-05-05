<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_motivations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('check_in_id')->unique();
            $table->string('member_id')->index();
            $table->string('quote_id')->nullable();
            $table->text('quote_body');
            $table->string('quote_author');
            $table->timestamp('assigned_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_motivations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_in_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('member_id')->index();
            $table->timestamp('checked_in_at');
            $table->text('quote_body')->nullable();
            $table->string('quote_author')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_in_summaries');
    }
};

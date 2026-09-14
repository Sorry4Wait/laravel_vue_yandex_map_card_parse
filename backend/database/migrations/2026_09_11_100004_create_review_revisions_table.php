<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // only written when a reparse finds that an existing review actually
        // changed (yandex lets people edit their review text/rating later)
        Schema::create('review_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parse_run_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('previous_rating')->nullable();
            $table->text('previous_text')->nullable();
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_revisions');
    }
};

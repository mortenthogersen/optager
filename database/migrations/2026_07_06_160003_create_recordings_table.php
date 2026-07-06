<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordings', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('title')->nullable();
            $table->string('source_type')->default('manual_upload');
            $table->string('status')->default('uploaded');
            $table->string('audio_disk')->default('recordings');
            $table->string('audio_path');
            $table->string('audio_original_name');
            $table->string('audio_mime');
            $table->unsignedBigInteger('audio_size_bytes');
            $table->string('audio_checksum')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('language', 10)->default('da');
            $table->longText('transcript_text')->nullable();
            $table->json('transcript_json')->nullable();
            $table->longText('summary_text')->nullable();
            $table->json('summary_json')->nullable();
            $table->string('transcription_model')->nullable();
            $table->string('summary_model')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('transcription_started_at')->nullable();
            $table->timestamp('transcription_completed_at')->nullable();
            $table->timestamp('summary_started_at')->nullable();
            $table->timestamp('summary_completed_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('source_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordings');
    }
};

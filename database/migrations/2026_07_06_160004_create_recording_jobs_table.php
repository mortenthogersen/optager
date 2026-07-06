<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recording_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained('recordings')->cascadeOnDelete();
            $table->string('job_type');
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempt')->default(1);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['recording_id', 'job_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recording_jobs');
    }
};

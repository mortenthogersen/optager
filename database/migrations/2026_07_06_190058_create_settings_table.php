<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default keys
        $defaults = [
            'openrouter_api_key' => '',
            'openrouter_stt_model' => 'nvidia/parakeet-tdt-0.6b-v3',
            'deepseek_api_key' => '',
            'deepseek_model' => 'deepseek-v4-flash',
            'deepseek_base_url' => 'https://api.deepseek.com',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

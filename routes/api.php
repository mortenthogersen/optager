<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Interne API Routes
|--------------------------------------------------------------------------
|
| Disse endpoints er forberedt til fremtidig ESP32-enhedsintegration.
| I POC-fasen er de stubbet ud og kan aktiveres når device-upload
| skal implementeres.
|
| Fremtidig auth: per-device API keys, signed requests, rate limiting.
|
*/

Route::prefix('internal')->group(function () {

    // POST /api/internal/recordings
    // Opret en ny optagelse (metadata, uden lydfil)
    Route::post('/recordings', function () {
        return response()->json(['message' => 'Not implemented - POC phase'], 501);
    });

    // POST /api/internal/recordings/{uuid}/upload
    // Upload lydfil til eksisterende optagelse
    Route::post('/recordings/{uuid}/upload', function () {
        return response()->json(['message' => 'Not implemented - POC phase'], 501);
    });

    // GET /api/internal/recordings/{uuid}
    // Hent status og metadata for en optagelse
    Route::get('/recordings/{uuid}', function () {
        return response()->json(['message' => 'Not implemented - POC phase'], 501);
    });

    // POST /api/internal/recordings/{uuid}/retry-transcription
    // Genstart transskription for en optagelse
    Route::post('/recordings/{uuid}/retry-transcription', function () {
        return response()->json(['message' => 'Not implemented - POC phase'], 501);
    });

    // POST /api/internal/recordings/{uuid}/retry-summary
    // Genstart opsummering for en optagelse
    Route::post('/recordings/{uuid}/retry-summary', function () {
        return response()->json(['message' => 'Not implemented - POC phase'], 501);
    });

});

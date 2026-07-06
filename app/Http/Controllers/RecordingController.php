<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingTranscriptionJob;
use App\Models\Recording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RecordingController extends Controller
{
    public function create(): View
    {
        return view('record.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/webm,audio/wav,audio/ogg,audio/aac,audio/mp4', 'max:512000'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('audio');

        $uuid = (string) Str::uuid();
        $path = $file->store('uploads', 'recordings');

        $recording = Recording::create([
            'uuid' => $uuid,
            'title' => $validated['title'] ?? null,
            'source_type' => 'manual_upload',
            'status' => 'uploaded',
            'audio_disk' => 'recordings',
            'audio_path' => $path,
            'audio_original_name' => $file->getClientOriginalName(),
            'audio_mime' => $file->getMimeType(),
            'audio_size_bytes' => $file->getSize(),
            'language' => 'da',
        ]);

        ProcessRecordingTranscriptionJob::dispatch($recording);

        $recording->update(['status' => 'queued_for_transcription']);

        return redirect()->route('record.success', ['uuid' => $uuid]);
    }

    public function success(string $uuid): View
    {
        $recording = Recording::where('uuid', $uuid)->firstOrFail();

        return view('record.success', ['recording' => $recording]);
    }
}

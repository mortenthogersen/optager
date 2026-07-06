<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRecordingTranscriptionJob;
use App\Models\Recording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'audio' => ['required_without:audio_data', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/webm,audio/wav,audio/ogg,audio/aac,audio/mp4', 'max:512000'],
            'audio_data' => ['required_without:audio', 'string'],
            'audio_name' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $uuid = (string) Str::uuid();

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $path = $file->store('uploads', 'recordings');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $size = $file->getSize();
        } else {
            $data = $request->input('audio_data');
            $data = explode(',', $data, 2)[1] ?? $data;
            $decoded = base64_decode($data);
            $path = 'uploads/'.$uuid.'.webm';
            Storage::disk('recordings')->put($path, $decoded);
            $originalName = $request->input('audio_name', 'recording.webm');
            $mimeType = 'audio/webm';
            $size = strlen($decoded);
        }

        $recording = Recording::create([
            'uuid' => $uuid,
            'title' => $validated['title'] ?? null,
            'source_type' => 'manual_upload',
            'status' => 'uploaded',
            'audio_disk' => 'recordings',
            'audio_path' => $path,
            'audio_original_name' => $originalName,
            'audio_mime' => $mimeType,
            'audio_size_bytes' => $size,
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

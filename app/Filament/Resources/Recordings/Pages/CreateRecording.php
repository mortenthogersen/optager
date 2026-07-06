<?php

namespace App\Filament\Resources\Recordings\Pages;

use App\Filament\Resources\Recordings\RecordingResource;
use App\Jobs\ProcessRecordingTranscriptionJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateRecording extends CreateRecord
{
    protected static string $resource = RecordingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['title'] ??= now()->format('d/m/Y H:i');
        $data['source_type'] ??= 'manual_upload';
        $data['status'] = 'uploaded';
        $data['audio_disk'] = 'recordings';
        $data['language'] ??= 'da';
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $recording = $this->record;

        ProcessRecordingTranscriptionJob::dispatch($recording);

        $recording->update(['status' => 'queued_for_transcription']);

        Notification::make()
            ->title('Optagelse oprettet')
            ->body('Optagelsen er uploadet og transskription er sat i kø.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}

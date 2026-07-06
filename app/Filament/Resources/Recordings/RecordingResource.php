<?php

namespace App\Filament\Resources\Recordings;

use App\Filament\Resources\Recordings\Pages\CreateRecording;
use App\Filament\Resources\Recordings\Pages\EditRecording;
use App\Filament\Resources\Recordings\Pages\ListRecordings;
use App\Filament\Resources\Recordings\Pages\ViewRecording;
use App\Filament\Resources\Recordings\Schemas\RecordingForm;
use App\Filament\Resources\Recordings\Tables\RecordingsTable;
use App\Jobs\GenerateMeetingSummaryJob;
use App\Jobs\ProcessRecordingTranscriptionJob;
use App\Models\Recording;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecordingResource extends Resource
{
    protected static ?string $model = Recording::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $navigationLabel = 'Optagelser';

    protected static ?string $modelLabel = 'Optagelse';

    protected static ?string $pluralModelLabel = 'Optagelser';

    public static function form(Schema $schema): Schema
    {
        return RecordingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecordingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecordings::route('/'),
            'create' => CreateRecording::route('/create'),
            'edit' => EditRecording::route('/{record}/edit'),
            'view' => ViewRecording::route('/{record}'),
        ];
    }

    public static function retryTranscription(Recording $recording): void
    {
        if (! in_array($recording->status, ['failed', 'uploaded', 'queued_for_transcription', 'transcribed'], true)) {
            Notification::make()
                ->title('Kan ikke genstarte transskription')
                ->body("Optagelsen har status '{$recording->status}', som ikke tillader genstart.")
                ->danger()
                ->send();

            return;
        }

        ProcessRecordingTranscriptionJob::dispatch($recording);

        $recording->update(['status' => 'queued_for_transcription', 'error_message' => null]);

        Notification::make()
            ->title('Transskription sat i kø')
            ->body('Transskriptionsjobbet er blevet sat i kø og behandles asynkront.')
            ->success()
            ->send();
    }

    public static function retrySummary(Recording $recording): void
    {
        if (! $recording->transcript_text) {
            Notification::make()
                ->title('Kan ikke generere opsummering')
                ->body('Optagelsen har ingen transskription. Kør transskription først.')
                ->danger()
                ->send();

            return;
        }

        if (! in_array($recording->status, ['failed', 'transcribed', 'queued_for_summary', 'completed'], true)) {
            Notification::make()
                ->title('Kan ikke genstarte opsummering')
                ->body("Optagelsen har status '{$recording->status}', som ikke tillader genstart.")
                ->danger()
                ->send();

            return;
        }

        GenerateMeetingSummaryJob::dispatch($recording);

        $recording->update(['status' => 'queued_for_summary', 'error_message' => null]);

        Notification::make()
            ->title('Opsummering sat i kø')
            ->body('Opsummeringsjobbet er blevet sat i kø og behandles asynkront.')
            ->success()
            ->send();
    }
}

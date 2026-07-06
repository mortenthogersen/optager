<?php

namespace App\Filament\Resources\Recordings\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RecordingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Metadata')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titel')
                            ->maxLength(255),
                        Select::make('source_type')
                            ->label('Kildetype')
                            ->options([
                                'manual_upload' => 'Manuel upload',
                                'device_upload' => 'Enhedsupload',
                            ])
                            ->required()
                            ->default('manual_upload'),
                        Select::make('language')
                            ->label('Sprog')
                            ->options([
                                'da' => 'Dansk',
                                'en' => 'Engelsk',
                            ])
                            ->required()
                            ->default('da'),
                        Select::make('status')
                            ->label('Status')
                            ->options(self::statusOptions())
                            ->required()
                            ->default('uploaded'),
                    ])
                    ->columns(2),

                Section::make('Lydfil')
                    ->schema([
                        FileUpload::make('audio_path')
                            ->label('MP3-fil')
                            ->disk('recordings')
                            ->directory('uploads')
                            ->acceptedFileTypes(['audio/mpeg', 'audio/mp3', '.mp3'])
                            ->maxSize(512000)
                            ->preventFilePathTampering()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->afterStateUpdated(function ($state, $set) {
                                if ($state instanceof TemporaryUploadedFile) {
                                    $set('audio_original_name', $state->getClientOriginalName());
                                    $set('audio_mime', $state->getMimeType());
                                    $set('audio_size_bytes', $state->getSize());
                                }
                            }),
                        TextInput::make('audio_original_name')
                            ->label('Originalt filnavn')
                            ->readOnly()
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('audio_mime')
                            ->label('MIME-type')
                            ->readOnly()
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('audio_size_bytes')
                            ->label('Filstørrelse (bytes)')
                            ->readOnly()
                            ->required(fn (string $operation): bool => $operation === 'create'),
                        TextInput::make('duration_seconds')
                            ->label('Varighed (sekunder)')
                            ->numeric(),
                    ])
                    ->columns(2),

                Section::make('Transskription')
                    ->schema([
                        Textarea::make('transcript_text')
                            ->label('Rå transskription')
                            ->readOnly()
                            ->columnSpanFull()
                            ->rows(10),
                        TextInput::make('transcription_model')
                            ->label('Transskriptionsmodel')
                            ->readOnly(),
                        DateTimePicker::make('transcription_started_at')
                            ->label('Startet'),
                        DateTimePicker::make('transcription_completed_at')
                            ->label('Færdiggjort'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Opsummering')
                    ->schema([
                        Textarea::make('summary_text')
                            ->label('Møderesumé')
                            ->readOnly()
                            ->columnSpanFull()
                            ->rows(10),
                        TextInput::make('summary_model')
                            ->label('Opsummeringsmodel')
                            ->readOnly(),
                        DateTimePicker::make('summary_started_at')
                            ->label('Startet'),
                        DateTimePicker::make('summary_completed_at')
                            ->label('Færdiggjort'),
                    ])
                    ->columns(2)
                    ->collapsed(),

                Section::make('Fejl')
                    ->schema([
                        Textarea::make('error_message')
                            ->label('Fejlbesked')
                            ->readOnly()
                            ->columnSpanFull()
                            ->rows(3),
                    ])
                    ->collapsed()
                    ->visible(fn ($record) => $record?->error_message),
            ]);
    }

    public static function statusOptions(): array
    {
        return [
            'uploaded' => 'Uploadet',
            'queued_for_transcription' => 'I kø (transskription)',
            'transcribing' => 'Transskriberer',
            'transcribed' => 'Transskriberet',
            'queued_for_summary' => 'I kø (opsummering)',
            'summarizing' => 'Opsummerer',
            'completed' => 'Færdig',
            'failed' => 'Fejlet',
        ];
    }
}

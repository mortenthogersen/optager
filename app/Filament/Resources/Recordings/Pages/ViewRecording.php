<?php

namespace App\Filament\Resources\Recordings\Pages;

use App\Filament\Resources\Recordings\RecordingResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRecording extends ViewRecord
{
    protected static string $resource = RecordingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retryTranscription')
                ->label('Genstart transskription')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    RecordingResource::retryTranscription($this->record);
                })
                ->visible(fn () => in_array($this->record->status, ['failed', 'uploaded', 'queued_for_transcription', 'transcribed'], true)),
            Action::make('retrySummary')
                ->label('Genstart opsummering')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->action(function () {
                    RecordingResource::retrySummary($this->record);
                })
                ->visible(fn () => $this->record->transcript_text && in_array($this->record->status, ['failed', 'transcribed', 'queued_for_summary', 'completed'], true)),
            Action::make('downloadTranscript')
                ->label('Download transskription')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    if (! $this->record->transcript_text) {
                        Notification::make()
                            ->title('Ingen transskription')
                            ->body('Optagelsen har ingen transskription at downloade.')
                            ->warning()
                            ->send();

                        return;
                    }

                    return response()->streamDownload(
                        fn () => print ($this->record->transcript_text),
                        "transskription-{$this->record->uuid}.txt",
                    );
                })
                ->visible(fn () => (bool) $this->record->transcript_text),
        ];
    }

    protected function getInfolistSchema(): array
    {
        return [
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('uuid')
                        ->label('UUID')
                        ->copyable(),
                    TextEntry::make('title')
                        ->label('Titel')
                        ->placeholder('Ingen titel'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match ($state) {
                            'uploaded' => 'gray',
                            'queued_for_transcription', 'queued_for_summary' => 'warning',
                            'transcribing', 'summarizing' => 'info',
                            'transcribed', 'completed' => 'success',
                            'failed' => 'danger',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'uploaded' => 'Uploadet',
                            'queued_for_transcription' => 'I kø (transskription)',
                            'transcribing' => 'Transskriberer',
                            'transcribed' => 'Transskriberet',
                            'queued_for_summary' => 'I kø (opsummering)',
                            'summarizing' => 'Opsummerer',
                            'completed' => 'Færdig',
                            'failed' => 'Fejlet',
                            default => $state,
                        }),
                    TextEntry::make('source_type')
                        ->label('Kildetype')
                        ->formatStateUsing(fn (string $state): string => match ($state) {
                            'manual_upload' => 'Manuel upload',
                            'device_upload' => 'Enhedsupload',
                            default => $state,
                        }),
                    TextEntry::make('language')
                        ->label('Sprog'),
                    TextEntry::make('created_at')
                        ->label('Oprettet')
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('creator.name')
                        ->label('Uploadet af'),
                ])
                ->columns(3),

            Section::make('Lydfil')
                ->schema([
                    TextEntry::make('audio_original_name')
                        ->label('Originalt filnavn'),
                    TextEntry::make('audio_mime')
                        ->label('MIME-type'),
                    TextEntry::make('audio_size_bytes')
                        ->label('Filstørrelse')
                        ->formatStateUsing(fn (int $state): string => $this->formatBytes($state)),
                    TextEntry::make('duration_seconds')
                        ->label('Varighed')
                        ->formatStateUsing(fn (?int $state): string => $state ? $this->formatDuration($state) : '—'),
                    TextEntry::make('cost_estimate')
                        ->label('Estimeret pris (STT)')
                        ->getStateUsing(function ($record): ?string {
                            $duration = $record->duration_seconds;
                            if (! $duration) {
                                return null;
                            }

                            $sttModel = $record->transcription_model ?? config('services.openrouter.stt_model');
                            $sttRate = match ($sttModel) {
                                'nvidia/parakeet-tdt-0.6b-v3' => 0.0015,
                                default => 0.0015,
                            };

                            $usdCost = ($duration / 60) * $sttRate;
                            $dkkCost = $usdCost * 6.9;

                            $runner = config('services.transcription.runner', 'process');

                            if ($runner === 'openrouter') {
                                return sprintf('$%.4f (~%.2f kr.)', $usdCost, $dkkCost);
                            }

                            return '— (lokal GPU)';
                        }),
                ])
                ->columns(4),

            Section::make('Transskription')
                ->schema([
                    TextEntry::make('transcription_model')
                        ->label('Model'),
                    TextEntry::make('transcription_duration')
                        ->label('Behandlingstid')
                        ->getStateUsing(fn ($record): ?string => $this->formatElapsed(
                            $record->transcription_started_at,
                            $record->transcription_completed_at
                        )),
                    TextEntry::make('transcription_started_at')
                        ->label('Startet')
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('transcription_completed_at')
                        ->label('Færdiggjort')
                        ->dateTime('d/m/Y H:i:s'),
                ])
                ->columns(4)
                ->collapsible(),

            Section::make('Transskriptionstekst')
                ->schema([
                    TextEntry::make('transcript_text')
                        ->label('')
                        ->markdown()
                        ->columnSpanFull()
                        ->visible(fn ($record) => (bool) $record->transcript_text)
                        ->extraAttributes(['class' => 'prose max-w-none']),
                    TextEntry::make('no_transcript')
                        ->getStateUsing(fn () => 'Ingen transskription tilgængelig endnu.')
                        ->hidden(fn ($record) => (bool) $record->transcript_text),
                ]),

            Section::make('Møderesumé')
                ->schema([
                    TextEntry::make('summary_model')
                        ->label('Model'),
                    TextEntry::make('summary_duration')
                        ->label('Behandlingstid')
                        ->getStateUsing(fn ($record): ?string => $this->formatElapsed(
                            $record->summary_started_at,
                            $record->summary_completed_at
                        )),
                    TextEntry::make('summary_started_at')
                        ->label('Startet')
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('summary_completed_at')
                        ->label('Færdiggjort')
                        ->dateTime('d/m/Y H:i:s'),
                ])
                ->columns(4)
                ->collapsible(),

            Section::make('Resumé (Markdown)')
                ->schema([
                    TextEntry::make('summary_text')
                        ->label('')
                        ->markdown()
                        ->columnSpanFull()
                        ->visible(fn ($record) => (bool) $record->summary_text)
                        ->extraAttributes(['class' => 'prose max-w-none']),
                    TextEntry::make('no_summary')
                        ->getStateUsing(fn () => 'Intet resumé tilgængeligt endnu.')
                        ->hidden(fn ($record) => (bool) $record->summary_text),
                ]),

            Section::make('Fejl')
                ->schema([
                    TextEntry::make('error_message')
                        ->label('Fejlbesked')
                        ->columnSpanFull(),
                ])
                ->collapsed()
                ->visible(fn ($record) => (bool) $record->error_message),

            Section::make('Behandlingslog')
                ->schema([
                    TextEntry::make('processing_log')
                        ->label('')
                        ->getStateUsing(function ($record): ?string {
                            $jobs = $record->recordingJobs()
                                ->orderBy('created_at', 'desc')
                                ->get();

                            if ($jobs->isEmpty()) {
                                return null;
                            }

                            return $jobs->map(function ($job) {
                                $type = $job->job_type === 'transcription' ? 'Transskription' : 'Opsummering';
                                $statusLabel = match ($job->status) {
                                    'queued' => 'I kø',
                                    'processing' => 'Behandler',
                                    'completed' => 'Fuldført',
                                    'failed' => 'Fejlet',
                                    default => $job->status,
                                };
                                $attempt = $job->attempt;
                                $started = $job->started_at?->format('d/m/Y H:i:s') ?? '—';
                                $finished = $job->finished_at?->format('d/m/Y H:i:s') ?? '—';
                                $error = $job->error_message ? " - Fejl: {$job->error_message}" : '';

                                return "**{$type}** (Forsøg {$attempt}): {$statusLabel} | Start: {$started} | Slut: {$finished}{$error}";
                            })->implode("\n\n---\n\n");
                        })
                        ->markdown()
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => $record->recordingJobs()->exists()),
        ];
    }

    private function formatBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }

    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    private function formatElapsed($start, $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }

        $seconds = (int) $start->diffInSeconds($end);

        if ($seconds < 60) {
            return "{$seconds} sek";
        }

        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;

        return "{$minutes} min {$secs} sek";
    }
}

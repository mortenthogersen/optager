<?php

namespace App\Filament\Resources\Recordings\Tables;

use App\Filament\Resources\Recordings\RecordingResource;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecordingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->audio_original_name)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'uploaded' => 'gray',
                        'queued_for_transcription', 'queued_for_summary' => 'warning',
                        'transcribing', 'summarizing' => 'info',
                        'transcribed' => 'success',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'uploaded' => 'Uploadet',
                        'queued_for_transcription' => 'I kø (trans.)',
                        'transcribing' => 'Transskriberer',
                        'transcribed' => 'Transskriberet',
                        'queued_for_summary' => 'I kø (ops.)',
                        'summarizing' => 'Opsummerer',
                        'completed' => 'Færdig',
                        'failed' => 'Fejlet',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('source_type')
                    ->label('Kilde')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual_upload' => 'Manuel',
                        'device_upload' => 'Enhed',
                        default => $state,
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('audio_size_bytes')
                    ->label('Filstørrelse')
                    ->formatStateUsing(fn (int $state): string => self::formatBytes($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('duration_seconds')
                    ->label('Varighed')
                    ->formatStateUsing(fn (?int $state): string => $state ? self::formatDuration($state) : '—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('stt_cost')
                    ->label('Pris (STT)')
                    ->getStateUsing(function ($record): ?string {
                        if (! $record->duration_seconds) {
                            return null;
                        }

                        $runner = config('services.transcription.runner', 'process');

                        if ($runner !== 'openrouter') {
                            return '—';
                        }

                        return sprintf('~%.2f kr.', ($record->duration_seconds / 60) * 0.0015 * 6.9);
                    })
                    ->toggleable(),
                TextColumn::make('transcription_model')
                    ->label('Transskriptionsmodel')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('summary_model')
                    ->label('Opsummeringsmodel')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Uploadet')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Uploadet af')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'uploaded' => 'Uploadet',
                        'queued_for_transcription' => 'I kø (transskription)',
                        'transcribing' => 'Transskriberer',
                        'transcribed' => 'Transskriberet',
                        'queued_for_summary' => 'I kø (opsummering)',
                        'summarizing' => 'Opsummerer',
                        'completed' => 'Færdig',
                        'failed' => 'Fejlet',
                    ]),
                SelectFilter::make('source_type')
                    ->label('Kildetype')
                    ->options([
                        'manual_upload' => 'Manuel upload',
                        'device_upload' => 'Enhedsupload',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('retryTranscription')
                    ->label('Genstart transskription')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(fn ($record) => RecordingResource::retryTranscription($record))
                    ->visible(fn ($record) => in_array($record->status, ['failed', 'uploaded', 'queued_for_transcription', 'transcribed'], true)),
                Action::make('retrySummary')
                    ->label('Genstart opsummering')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->action(fn ($record) => RecordingResource::retrySummary($record))
                    ->visible(fn ($record) => $record->transcript_text && in_array($record->status, ['failed', 'transcribed', 'queued_for_summary', 'completed'], true)),
                Action::make('downloadTranscript')
                    ->label('Download transskription')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($record) {
                        if (! $record->transcript_text) {
                            return;
                        }

                        return response()->streamDownload(
                            fn () => print ($record->transcript_text),
                            "transskription-{$record->uuid}.txt",
                        );
                    })
                    ->visible(fn ($record) => (bool) $record->transcript_text),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    Action::make('requeueTranscription')
                        ->label('Genstart transskription')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                RecordingResource::retryTranscription($record);
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function formatBytes(int $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }

    private static function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}

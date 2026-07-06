<?php

namespace App\Filament\Widgets;

use App\Models\Recording;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RecordingStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Optagelser i alt', Recording::count())
                ->description('Samlet antal optagelser')
                ->descriptionIcon('heroicon-o-microphone')
                ->color('primary'),

            Stat::make('I kø / under behandling', Recording::whereIn('status', [
                'queued_for_transcription',
                'transcribing',
                'queued_for_summary',
                'summarizing',
            ])->count())
                ->description('Afventer eller behandles')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning'),

            Stat::make('Fuldførte', Recording::where('status', 'completed')->count())
                ->description('Transskriberet og opsummeret')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Fejlede', Recording::where('status', 'failed')->count())
                ->description('Kræver handling')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Indstillinger';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.settings-page';

    public string $openrouter_api_key = '';

    public string $openrouter_stt_model = '';

    public string $deepseek_api_key = '';

    public string $deepseek_model = '';

    public string $deepseek_base_url = '';

    public function mount(): void
    {
        $this->openrouter_api_key = (string) Setting::get('openrouter_api_key');
        $this->openrouter_stt_model = Setting::get('openrouter_stt_model', 'nvidia/parakeet-tdt-0.6b-v3');
        $this->deepseek_api_key = (string) Setting::get('deepseek_api_key');
        $this->deepseek_model = Setting::get('deepseek_model', 'deepseek-v4-flash');
        $this->deepseek_base_url = Setting::get('deepseek_base_url', 'https://api.deepseek.com');
    }

    public function save(): void
    {
        Setting::set('openrouter_api_key', $this->openrouter_api_key);
        Setting::set('openrouter_stt_model', $this->openrouter_stt_model);
        Setting::set('deepseek_api_key', $this->deepseek_api_key);
        Setting::set('deepseek_model', $this->deepseek_model);
        Setting::set('deepseek_base_url', $this->deepseek_base_url);

        Notification::make()
            ->title('Indstillinger gemt')
            ->success()
            ->send();
    }
}

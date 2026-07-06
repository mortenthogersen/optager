<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Indstillinger';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'openrouter_api_key' => Setting::get('openrouter_api_key'),
            'openrouter_stt_model' => Setting::get('openrouter_stt_model', 'nvidia/parakeet-tdt-0.6b-v3'),
            'deepseek_api_key' => Setting::get('deepseek_api_key'),
            'deepseek_model' => Setting::get('deepseek_model', 'deepseek-v4-flash'),
            'deepseek_base_url' => Setting::get('deepseek_base_url', 'https://api.deepseek.com'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('OpenRouter (Transskription)')
                    ->schema([
                        TextInput::make('openrouter_api_key')
                            ->label('API-nøgle')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-or-v1-...')
                            ->helperText('Findes på openrouter.ai/settings/keys'),
                        TextInput::make('openrouter_stt_model')
                            ->label('STT Model')
                            ->placeholder('nvidia/parakeet-tdt-0.6b-v3')
                            ->helperText('Model ID fra OpenRouter (f.eks. nvidia/parakeet-tdt-0.6b-v3)'),
                    ])
                    ->columns(2),

                Section::make('DeepSeek (Opsummering)')
                    ->schema([
                        TextInput::make('deepseek_api_key')
                            ->label('API-nøgle')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-...')
                            ->helperText('Findes på platform.deepseek.com/api_keys'),
                        Select::make('deepseek_model')
                            ->label('Model')
                            ->options([
                                'deepseek-v4-flash' => 'DeepSeek V4 Flash (hurtig, billig)',
                                'deepseek-v4-pro' => 'DeepSeek V4 Pro (bedre kvalitet)',
                            ])
                            ->native(false),
                        TextInput::make('deepseek_base_url')
                            ->label('API URL')
                            ->placeholder('https://api.deepseek.com'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        Notification::make()
            ->title('Indstillinger gemt')
            ->success()
            ->send();
    }
}

<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">OpenRouter (Transskription)</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="password"
                            wire:model="openrouter_api_key"
                            placeholder="sk-or-v1-..."
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 mt-1">Findes på openrouter.ai/settings/keys</p>
                </div>

                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="openrouter_stt_model"
                            placeholder="nvidia/parakeet-tdt-0.6b-v3"
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 mt-1">Model ID fra OpenRouter</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">DeepSeek (Opsummering)</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="password"
                            wire:model="deepseek_api_key"
                            placeholder="sk-..."
                        />
                    </x-filament::input.wrapper>
                    <p class="text-xs text-gray-500 mt-1">Findes på platform.deepseek.com/api_keys</p>
                </div>

                <div>
                    <select wire:model="deepseek_model" class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-gray-950 ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500">
                        <option value="deepseek-v4-flash">DeepSeek V4 Flash (hurtig, billig)</option>
                        <option value="deepseek-v4-pro">DeepSeek V4 Pro (bedre kvalitet)</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="text"
                            wire:model="deepseek_base_url"
                            placeholder="https://api.deepseek.com"
                        />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </x-filament::section>

        <div>
            <x-filament::button type="submit" color="primary">
                Gem indstillinger
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

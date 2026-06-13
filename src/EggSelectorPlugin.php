<?php

namespace JoanFo\EggSelector;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\HeaderActionPosition;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Pages\Startup;
use App\Models\Egg;
use App\Models\Server;
use App\Services\Eggs\EggChangerService;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Schema;
use JoanFo\EggSelector\Models\EggSelectorSetting;

class EggSelectorPlugin implements HasPluginSettings, Plugin
{
    public function getId(): string
    {
        return 'egg-selector';
    }

    public function register(Panel $panel): void
    {
        if ($panel->getId() !== 'server') {
            return;
        }

        Startup::registerCustomHeaderActions(
            HeaderActionPosition::After,
            Action::make('egg_selector')
                ->label('Change Egg')
                ->icon(TablerIcon::Egg->value)
                ->visible(fn () => user()?->can(SubuserPermission::StartupUpdate, Filament::getTenant()))
                ->schema(fn () => [
                    Select::make('egg_id')
                        ->label('New Egg')
                        ->options(function () {
                            $server = Filament::getTenant();
                            $allowedIds = EggSelectorSetting::getAvailableEggIds();

                            return Egg::whereIn('id', $allowedIds)
                                ->where('id', '!=', $server->egg_id)
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                    Toggle::make('keep_old_variables')
                        ->label('Keep Old Variables')
                        ->helperText('Preserve variable values from the current egg where env variable names match.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    /** @var Server $server */
                    $server = Filament::getTenant();
                    $oldName = $server->egg->name;

                    app(EggChangerService::class)->handle($server, (int) $data['egg_id'], $data['keep_old_variables'] ?? true);

                    $newName = Egg::find((int) $data['egg_id'])?->name ?? $data['egg_id'];

                    Activity::event('server:startup.egg')
                        ->property(['old' => $oldName, 'new' => $newName])
                        ->log();

                    Notification::make()
                        ->title('Egg changed to ' . $newName)
                        ->success()
                        ->send();

                    redirect()->to(Startup::getUrl());
                })
        );
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        $availableEggs = Schema::hasTable('egg_selector_settings')
            ? EggSelectorSetting::getAvailableEggIds()
            : [];

        return [
            Section::make('Available Eggs')
                ->description('Select which eggs server users are allowed to switch to.')
                ->schema([
                    Select::make('available_eggs')
                        ->label('Available Eggs')
                        ->multiple()
                        ->options(fn () => Egg::all()->pluck('name', 'id')->toArray())
                        ->default($availableEggs)
                        ->searchable()
                        ->preload(),
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        EggSelectorSetting::updateOrCreate(
            ['id' => 1],
            ['available_eggs' => $data['available_eggs'] ?? []]
        );

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}

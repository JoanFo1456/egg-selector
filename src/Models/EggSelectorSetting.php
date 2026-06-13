<?php

namespace JoanFo\EggSelector\Models;

use Illuminate\Database\Eloquent\Model;

class EggSelectorSetting extends Model
{
    protected $table = 'egg_selector_settings';

    protected $fillable = ['available_eggs'];

    protected function casts(): array
    {
        return [
            'available_eggs' => 'array',
        ];
    }

    public static function getAvailableEggIds(): array
    {
        return static::first()?->available_eggs ?? [];
    }
}

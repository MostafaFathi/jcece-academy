<?php

namespace App\Models;

use Database\Factories\SiteSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['home_autoplay_enabled', 'currency'])]
class SiteSetting extends Model
{
    /** @use HasFactory<SiteSettingFactory> */
    use HasFactory;

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['home_autoplay_enabled' => true, 'currency' => strtoupper((string) config('jcec.commerce.currency'))]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['home_autoplay_enabled' => 'boolean'];
    }
}

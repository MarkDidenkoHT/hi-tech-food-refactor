<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    /**
     * Read a boolean setting, falling back to $default when unset.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = static::where('key', $key)->value('value');

        return $value === null ? $default : $value === '1';
    }

    /**
     * Persist a boolean setting.
     */
    public static function setBool(string $key, bool $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value ? '1' : '0']);
    }
}

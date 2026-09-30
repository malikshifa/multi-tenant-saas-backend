<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
    ];

    /*
    |--------------------------------------------------------------------------
    | Get one setting
    |--------------------------------------------------------------------------
    */

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {
        [$group, $settingKey] = self::parseKey($key);

        $setting = static::query()
            ->where('group', $group)
            ->where('key', $settingKey)
            ->first();

        if (! $setting) {
            return $default;
        }

        return $setting->castValue();
    }

    /*
    |--------------------------------------------------------------------------
    | Set one setting
    |--------------------------------------------------------------------------
    */

    public static function set(
        string $key,
        mixed $value
    ): static {
        [$group, $settingKey] = self::parseKey($key);

        [$storedValue, $type] = self::prepareValue($value);

        return static::updateOrCreate(
            [
                'group' => $group,
                'key' => $settingKey,
            ],
            [
                'value' => $storedValue,
                'type' => $type,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get all settings from a group
    |--------------------------------------------------------------------------
    */

    public static function group(
        string $group
    ): array {
        return static::query()
            ->where('group', $group)
            ->get()
            ->mapWithKeys(function (self $setting) {
                return [
                    $setting->key => $setting->castValue(),
                ];
            })
            ->toArray();
    }

    /*
    |--------------------------------------------------------------------------
    | Set multiple settings
    |--------------------------------------------------------------------------
    */

    public static function setGroup(
        string $group,
        array $settings
    ): void {
        foreach ($settings as $key => $value) {
            static::set(
                "{$group}.{$key}",
                $value
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cast stored value
    |--------------------------------------------------------------------------
    */

    public function castValue(): mixed
    {
        return match ($this->type) {

            'boolean' => filter_var(
                $this->value,
                FILTER_VALIDATE_BOOLEAN
            ),

            'integer' => (int) $this->value,

            'float' => (float) $this->value,

            'json' => json_decode(
                $this->value,
                true
            ),

            default => $this->value,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare value for database
    |--------------------------------------------------------------------------
    */

    protected static function prepareValue(
        mixed $value
    ): array {

        if (is_bool($value)) {
            return [
                $value ? '1' : '0',
                'boolean',
            ];
        }

        if (is_int($value)) {
            return [
                (string) $value,
                'integer',
            ];
        }

        if (is_float($value)) {
            return [
                (string) $value,
                'float',
            ];
        }

        if (is_array($value) || is_object($value)) {
            return [
                json_encode($value),
                'json',
            ];
        }

        return [
            $value,
            'string',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Parse group.key
    |--------------------------------------------------------------------------
    */

    protected static function parseKey(
        string $key
    ): array {

        $parts = explode('.', $key, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                'Setting key must use the "group.key" format.'
            );
        }

        return $parts;
    }
}

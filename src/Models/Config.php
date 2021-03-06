<?php

namespace MouYong\LaravelConfig\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use MouYong\LaravelConfig\Models\Config as ModelsConfig;

class Config extends Model
{
    const CACHE_KEY_PREFIX = 'item_key:';
    const CACHE_KEY_MINUTES = 60;

    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'is_multilingual' => 'bool',
        'is_api' => 'bool',
        'is_custom' => 'bool',
        'is_enable' => 'bool',
    ];

    public function getItemValueDescAttribute()
    {
        if ($this->item_type === 'string') {
            return strval($this->item_value);
        }

        if (in_array($this->item_type, ['bool', 'boolean'])) {
            return boolval($this->item_value);
        }

        if (in_array($this->item_type, ['array', 'json', 'object'])) {
            return json_decode($this->item_value, true);
        }

        throw new \RuntimeException("unknown key {$this->key} type {$this->item_type} of value {$this->item_value}");
    }

    public function getDetail()
    {
        return [
            'id' => $this->id,
            'item_key' => $this->item_key,
            'item_type' => $this->item_type,
            'item_value' => $this->item_value,
            'item_value_desc' => $this->item_value_desc,
        ];
    }

    public static function addKeyValue(string $itemKey, string $itemType, $itemValue, string $itemTag)
    {
        return Config::updateOrCreate([
            'item_key' => $itemKey,
        ], [
            'item_type' => $itemType,
            'item_value' => $itemValue,
            'item_tag' => $itemTag,
        ]);
    }

    public static function addKeyValues(array $itemKeyItemValues)
    {
        return array_map(function ($item) {
            return static::addKeyValue(
                $item['item_key'],
                $item['item_type'],
                $item['item_value'],
                $item['item_tag']
            );
        }, $itemKeyItemValues);
    }

    public static function setStringValue(string $tag, string $key, ?string $value = null)
    {
        return static::addKeyValue($tag, $key, (string) $value, 'string');
    }

    public static function setBoolValue(string $tag, string $key, bool $value = false)
    {
        return static::addKeyValue($tag, $key, (bool) $value, 'bool');
    }

    public static function setJsonValue(string $tag, string $key, bool $value = false)
    {
        return static::addKeyValue($tag, $key, json_encode($value, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT), 'json');
    }

    public static function getItemValueByItemKey(string $itemKey)
    {
        $cacheKey = Config::CACHE_KEY_PREFIX . $itemKey;
        $cacheTime = now()->addMinutes(ModelsConfig::CACHE_KEY_MINUTES);

        $config = Cache::remember($cacheKey, $cacheTime, function () use ($itemKey) {
            return static::query()->where('item_key', $itemKey)->first();
        });

        if (is_null($config)) {
            return Cache::pull($cacheKey);
        }

        return $config->item_value_desc;
    }

    public static function getValueByKeys(array $itemKeys): array
    {
        $data = [];

        foreach ($itemKeys as $index => $itemKey) {
            $value = Cache::get($itemKey);
            if ($value) {
                unset($itemKeys[$index]);

                $data[$itemKey] = $value;
            }
        }

        // ???????????????????????????
        if (count($data) === count($itemKeys)) {
            return $data;
        }

        // ??????????????????????????? key??????????????? key ????????????
        $values = static::query()->whereIn('item_key', $itemKeys)->get();
        foreach ($itemKeys as $index => $itemKey) {
            $cacheKey = Config::CACHE_KEY_PREFIX . $itemKey;
            $cacheTime = now()->addMinutes(ModelsConfig::CACHE_KEY_MINUTES);

            $itemValue = Cache::remember($cacheKey, $cacheTime, function () use ($values, $itemKey) {
                return $values->where('item_key', $itemKey)->first();
            });

            if (is_null($itemValue)) {
                Cache::pull($cacheKey);
            }

            $data[$itemKey] = $itemValue;
        }

        return $data;
    }
}

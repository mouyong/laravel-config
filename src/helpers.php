<?php

use Illuminate\Database\Eloquent\Model;
use MouYong\LaravelConfig\Models\Config;

if (! function_exists('db_config')) {
    function db_config($itemKey = null): string|array|Model
    {
        $configModel = config('laravel-config.config_model', Config::class);
        
        if (is_string($itemKey)) {
            return $configModel::getItemValueByItemKey($itemKey);
        }

        if (is_array($itemKey)) {
            return $configModel::getValueByKeys($itemKey);
        }

        return new $configModel();
    }
}

if (! function_exists('db_config_central')) {
    function db_config_central($itemKey = null): string|array|Model
    {
        if (! function_exists('tenancy')) {
            return db_config($itemKey);
        }

        return central(function () use ($itemKey) {
            return db_config($itemKey);
        });
    }
}

if (! function_exists('central')) {
    function central(callable $callable): string|array|Model
    {
        if (! function_exists('tenancy')) {
            return $callable();
        }

        return tenancy()->central(function () use ($callable) {
            return $callable();
        });
    }
}

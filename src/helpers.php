<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param  string  $path
     *
     * @return string
     */
    function config_path(string $path = ''): string
    {
        return \app()->basePath().'/config'.($path ? '/'.$path : $path);
    }
}

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     *
     * @return string
     */
    function base_path(string $path = ''): string
    {
        return \app()->basePath().($path ? '/'.$path : $path);
    }
}

if (!function_exists('array_undot')) {
    function array_undot($dotNotationArray): array
    {
        $array = [];
        foreach ($dotNotationArray as $key => $value) {
            Arr::set($array, $key, $value);
        }

        return $array;
    }
}



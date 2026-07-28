<?php

namespace Rimba\Base\Support;

// Usage :
//      Env::set('RIMBA_APP', 'Rimba');
//      $value = Env::get('RIMBA_APP');
class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        return env($key, $default);
    }

    public static function set(string $key, string $value): bool
    {
        $path = base_path('.env');

        if (! file_exists($path)) {
            return false;
        }

        $content = file_get_contents($path);

        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                $pattern,
                $key . '="' . addslashes($value) . '"',
                $content
            );
        } else {
            $content .= PHP_EOL . $key . '="' . addslashes($value) . '"';
        }

        file_put_contents($path, $content);

        return true;
    }
}
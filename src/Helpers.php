<?php
namespace CryCMS;

use JetBrains\PhpStorm\NoReturn;
use JsonException;

class Helpers
{
    public static function aPre(mixed $data, bool $end = false): void
    {
        if (self::isJson($data)) {
            try {
                /** @var string $data */
                $data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);
                $data = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $exception) {
                $data = 'error json: ' . $exception->getMessage();
            }
        }

        if ($data === true) {
            $data = 'true';
        }

        if ($data === false) {
            $data = 'false';
        }

        if ($data === null) {
            $data = 'null';
        }

        $trace = debug_backtrace();

        if (
            array_key_exists(0, $trace) &&
            array_key_exists('file', $trace[0]) &&
            array_key_exists('line', $trace[0])
        ) {
            echo "<pre>";
            echo "<small>" . str_replace(DR, '', $trace[0]['file']) . ":" . $trace[0]['line'] . "</small>" . PHP_EOL;
            print_r($data);
            echo "</pre>" . PHP_EOL;
        }

        if ($end) {
            exit;
        }
    }

    /**
     * @phpstan-assert-if-true string $string
     */
    public static function isJson(mixed $string): bool
    {
        if (is_array($string)) {
            return false;
        }

        if (is_object($string)) {
            return false;
        }

        if (is_null($string)) {
            return false;
        }

        if (is_string($string)) {
            $ss = preg_replace('/"(\\.|[^"\\\\])*"/', '', $string);
            if (!empty($ss) && preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', $ss) === false) {
                return true;
            }

            try {
                $json = json_decode($string, false, 512, JSON_THROW_ON_ERROR);
                return $json && $string !== $json;
            } catch (JsonException) {
            }
        }

        return false;
    }

    public static function clean(string $string, ?int $maxLength = null): string
    {
        $string = strip_tags($string);
        $string = htmlentities($string, ENT_NOQUOTES, "UTF-8");

        if ($maxLength !== null) {
            $string = mb_substr($string, 0, $maxLength, 'UTF-8');
        }

        return trim($string);
    }

    /**
     * @param string $string
     * @param string $char
     * @param string $replacement
     *
     * @return string
     */
    public static function removeDuplicateCharacter(string $string, string $char = '\s', string $replacement = ' '): string
    {
        return preg_replace('/' . $char . '{2,}/', $replacement, $string) ?? $string;
    }

    #[NoReturn]
    public static function redirect(string $location): void
    {
        Header("Location: " . $location);
        exit;
    }

    /**
     * @param ?array<string> $url
     * @param ?array<string, mixed> $get
     * @return string
     */
    public static function buildFullPath(?array $url, ?array $get): string
    {
        if (empty($url)) {
            $path = '/';
        }
        else {
            $path = '/' . implode('/', $url) . '/';
        }

        if (!empty($get)) {
            if (isset($get['path'])) {
                unset($get['path']);
            }

            $path .= '?' . http_build_query($get);
        }

        return self::removeDuplicateCharacter($path, '\/', '/');
    }

    public static function dateWithTimeFormat(?string $datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return $datetime;
        }

        $format = [];
        $format[] = 'd.m';
        if (date('Y', $timestamp) !== date('Y')) {
            $format[] = '.Y';
        }
        $format[] = ' \a\t H:i';

        return date(implode('', $format), $timestamp);
    }

    public static function remove00(string $string): string
    {
        return str_replace('.00', '', $string);
    }
}
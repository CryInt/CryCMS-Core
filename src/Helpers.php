<?php
namespace CryCMS;

use JsonException;

class Helpers
{
    public static function aPre($data, $end = false): void
    {
        if (self::isJson($data)) {
            try {
                $data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);
                $data = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $exception) {
                $data = 'error json';
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

        echo "<pre>";
        echo "<small>" . str_replace(DR, '', $trace[0]['file']) . ":" . $trace[0]['line'] . "</small>" . PHP_EOL;
        print_r($data);
        echo "</pre>" . PHP_EOL;

        if ($end) {
            exit;
        }
    }

    public static function isJson($string): bool
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

        $ss = preg_replace('/"(\\.|[^"\\\\])*"/', '', $string);
        if (preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', $ss) === false) {
            return true;
        }

        try {
            $json = json_decode($string, false, 512, JSON_THROW_ON_ERROR);
            return $json && $string !== $json;
        }
        catch (JsonException $exception) {
        }

        return false;
    }

    public static function clean($string, $max_length = false): string
    {
        $string = strip_tags($string);
        $string = htmlentities($string, ENT_NOQUOTES, "UTF-8");

        if (!empty($max_length)) {
            $string = mb_substr($string, 0, $max_length, 'UTF-8');
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
        return preg_replace('/' . $char . '{2,}/', $replacement, $string);
    }

    public static function redirect($location): void
    {
        Header("Location: " . $location);
        exit;
    }

    public static function buildFullPath($url, $get): string
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

    public static function dateWithTimeFormat($datetime): string
    {
        if (empty($datetime)) {
            return '';
        }

        $timestamp = strtotime($datetime);

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
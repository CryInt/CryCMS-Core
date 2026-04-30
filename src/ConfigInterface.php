<?php
namespace CryCMS;

interface ConfigInterface
{
    /**
     * @return array<string, mixed>
     */
    public static function get(): array;
}
<?php

declare(strict_types=1);

namespace Happenv\LaravelTrueModular\Phpstan\Tests\Support;

use RuntimeException;

/**
 * Canonical absolute paths to the on-disk fixture module trees, so the boundary
 * resolver and PHPStan agree on file/path prefixes.
 */
final class FixturePaths
{
    /**
     * Base dir whose `app-modules/` holds acme/core, acme/catalog and acme/sale.
     */
    public static function moduleBaseDir(): string
    {
        return self::real(__DIR__.'/../Fixtures');
    }

    /**
     * Base dir whose `app-modules/` holds the circular acme/a <-> acme/b pair.
     */
    public static function circularBaseDir(): string
    {
        return self::real(__DIR__.'/../Fixtures/circular');
    }

    /**
     * Absolute path to a file inside the acme/sale module.
     */
    public static function saleFile(string $relative): string
    {
        return self::real(__DIR__.'/../Fixtures/app-modules/sale/src/'.$relative);
    }

    /**
     * Absolute path to a file that does not belong to any module.
     */
    public static function outsideFile(string $relative): string
    {
        return self::real(__DIR__.'/../Fixtures/outside/'.$relative);
    }

    /**
     * Absolute path to any file inside the circular fixture set.
     */
    public static function circularFile(string $relative): string
    {
        return self::real(__DIR__.'/../Fixtures/circular/app-modules/'.$relative);
    }

    private static function real(string $path): string
    {
        $real = realpath($path);

        if ($real === false) {
            throw new RuntimeException("Fixture path does not exist: {$path}");
        }

        return $real;
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $root = dirname(__DIR__);

        foreach ([
            'bootstrap/cache',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
        ] as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

            if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
                throw new \RuntimeException("Não foi possível criar o diretório de teste: {$path}");
            }
        }
    }
}

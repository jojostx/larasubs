<?php

namespace Jojostx\Larasubs\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jojostx\Larasubs\LarasubsServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Jojostx\\Larasubs\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LarasubsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}

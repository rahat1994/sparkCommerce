<?php

namespace Rahat1994\SparkCommerce\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Rahat1994\SparkCommerce\SparkCommerceServiceProvider;
use Rahat1994\SparkCommerce\Tests\Fixtures\AdminPanelProvider;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Rahat1994\\SparkCommerce\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            SparkCommerceServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();

        $this->runMigrationStubs([
            __DIR__ . '/../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub',
            __DIR__ . '/../vendor/spatie/laravel-tags/database/migrations/create_tag_tables.php.stub',
        ]);

        $provider = $this->app->getProvider(SparkCommerceServiceProvider::class);

        foreach ($provider->getMigrations() as $name) {
            $this->runMigrationStubs([__DIR__ . '/../database/migrations/' . $name . '.php.stub']);
        }
    }

    /**
     * @param  array<string>  $paths
     */
    protected function runMigrationStubs(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                (include $path)->up();
            }
        }
    }
}

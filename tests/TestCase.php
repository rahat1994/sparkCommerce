<?php

namespace Rahat1994\SparkCommerce\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Rahat1994\SparkCommerce\Payments\Drivers\FakeGateway;
use Rahat1994\SparkCommerce\SparkCommerceServiceProvider;
use Rahat1994\SparkCommerce\Tests\Fixtures\AdminPanelProvider;
use Rahat1994\SparkCommerce\Tests\Fixtures\User;
use Spatie\Permission\Models\Role;

class TestCase extends Orchestra
{
    protected $enablesPackageDiscoveries = true;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Rahat1994\\SparkCommerce\\Database\\Factories\\' . class_basename($modelName) . 'Factory'
        );

        // The fake gateway keeps its state in statics; isolate every test.
        FakeGateway::reset();
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

        // The suite always runs on the fake gateway (the shipped config
        // default stays 'stripe'); webhook secrets back the REAL signature
        // verification exercised by the webhook tests.
        config()->set('sparkcommerce.payments.default', 'fake');
        config()->set('sparkcommerce.payments.gateways.fake.webhook_secret', 'fake-webhook-secret');
        config()->set('sparkcommerce.payments.gateways.stripe.secret_key', 'sk_test_fake');
        config()->set('sparkcommerce.payments.gateways.stripe.webhook_secret', 'whsec_test_secret');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();

        $this->runMigrationStubs(array_merge(
            // The paid webhook deletes the shopper's cart, so the cart
            // tables must exist in this suite too.
            glob(__DIR__ . '/../vendor/binafy/laravel-cart/database/migrations/*.php') ?: [],
            [
                __DIR__ . '/../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub',
                __DIR__ . '/../vendor/spatie/laravel-tags/database/migrations/create_tag_tables.php.stub',
                __DIR__ . '/../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub',
            ],
        ));

        $provider = $this->app->getProvider(SparkCommerceServiceProvider::class);

        foreach ($provider->getMigrations() as $name) {
            $this->runMigrationStubs([__DIR__ . '/../database/migrations/' . $name . '.php.stub']);
        }
    }

    /**
     * Create a user holding the SparkCommerce admin role.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createAdminUser(array $attributes = []): User
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            ...$attributes,
        ]);

        Role::firstOrCreate(['name' => config('sparkcommerce.admin_role', 'sc_admin')]);

        $user->assignRole(config('sparkcommerce.admin_role', 'sc_admin'));

        return $user;
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

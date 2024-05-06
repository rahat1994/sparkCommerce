<?php

namespace Rahat1994\SparkCommerce\Commands;

use Illuminate\Console\Command;
use Rahat1994\SparkCommerce\SparkCommerceServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Spatie\Tags\TagsServiceProvider;

class SparkCommercePublishMigrations extends Command
{
    public $signature = 'sparkcommerce:publish-migrations';

    public $description = 'Publish all the migrations related to SparkCommerce package';

    public function handle(): int
    {
        $this->call('vendor:publish', [
            '--provider' => SparkCommerceServiceProvider::class,
            '--tag' => 'sparkcommerce-migrations',
        ]);

        $this->call('vendor:publish', [
            '--provider' => TagsServiceProvider::class,
            '--tag' => 'tags-migrations',
        ]);

        $this->call('vendor:publish', [
            '--provider' => MediaLibraryServiceProvider::class,
            '--tag' => 'medialibrary-migrations',
        ]);

        $this->info('All migrations have been published successfully.');

        return self::SUCCESS;
    }
}

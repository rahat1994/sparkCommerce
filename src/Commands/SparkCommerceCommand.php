<?php

namespace Rahat1994\SparkCommerce\Commands;

use Illuminate\Console\Command;

class SparkCommerceCommand extends Command
{
    public $signature = 'sparkcommerce';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}

<?php

namespace Rahat1994\SparkCommerce\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sc:publish-roles')]
class SCPublishRolesCommand extends Command
{
    public $signature = 'sc:publish-roles';

    public $description = 'Publish the SparkCommerce admin role';

    public function handle(): int
    {
        $adminRole = config('sparkcommerce.admin_role');

        if (! is_string($adminRole) || $adminRole === '') {
            $this->error('The sparkcommerce.admin_role config value is not set.');

            return self::FAILURE;
        }

        Role::firstOrCreate(['name' => $adminRole]);

        $this->components->info("Success! The \"{$adminRole}\" role has been published.");

        return self::SUCCESS;
    }
}

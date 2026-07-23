<?php

namespace Rahat1994\SparkCommerce\Concerns;

use Closure;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

trait CanUseDatabaseTransactions
{
    protected ?bool $hasDatabaseTransactions = null;

    public function hasDatabaseTransactions(): bool
    {
        if ($this->hasDatabaseTransactions !== null) {
            return $this->hasDatabaseTransactions;
        }

        try {
            return Filament::getCurrentOrDefaultPanel()?->hasDatabaseTransactions() ?? false;
        } catch (NoDefaultPanelSetException) {
            // No panel is registered (e.g. a non-panel consumer such as a
            // REST route): default to no transactions instead of throwing.
            return false;
        }
    }

    protected function beginDatabaseTransaction(): void
    {
        if (! $this->hasDatabaseTransactions()) {
            return;
        }

        DB::beginTransaction();
    }

    protected function commitDatabaseTransaction(): void
    {
        if (! $this->hasDatabaseTransactions()) {
            return;
        }

        DB::commit();
    }

    protected function rollBackDatabaseTransaction(): void
    {
        if (! $this->hasDatabaseTransactions()) {
            return;
        }

        DB::rollBack();
    }

    protected function wrapInDatabaseTransaction(Closure $callback): mixed
    {
        if (! $this->hasDatabaseTransactions()) {
            return $callback();
        }

        /** @phpstan-ignore-next-line */
        return DB::transaction($callback);
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\PlannifierTransfertJob;

class ExecuteScheduledTransactionsCommand extends Command
{
    protected $signature = 'transactions:execute';
    protected $description = 'Exécute les transactions plannifiées';

    public function handle()
    {
        (new PlannifierTransfertJob())->handle();
        return Command::SUCCESS;
    }
}
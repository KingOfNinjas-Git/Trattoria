<?php

namespace App\Console\Commands;

use App\Models\DiningTable;
use Illuminate\Console\Command;

class TableTokens extends Command
{
    protected $signature = 'tables:tokens {--urls : Print the full menu link of each table instead of the bare token}';

    protected $description = 'Print the QR tokens of the tables, comma separated (used by the end-to-end tests)';

    public function handle(): int
    {
        $tables = DiningTable::orderBy('number')->get();

        $this->line($tables->map(fn (DiningTable $t) => $this->option('urls') ? $t->url() : $t->token)->implode(','));

        return self::SUCCESS;
    }
}

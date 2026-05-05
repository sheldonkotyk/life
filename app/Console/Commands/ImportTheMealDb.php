<?php

namespace App\Console\Commands;

use App\Services\TheMealDbImporter;
use Illuminate\Console\Command;

class ImportTheMealDb extends Command
{
    protected $signature = 'recipes:import-themealdb
                            {--letter= : Import only meals starting with this letter}
                            {--latest : Import the most recent meals (v2 only)}';

    protected $description = 'Import recipes from TheMealDB into the global recipes catalog';

    public function handle(TheMealDbImporter $importer): int
    {
        $progress = function ($meal, $count) {
            $this->line("  [{$count}] {$meal['strMeal']}");
        };

        if ($this->option('latest')) {
            $this->info('Fetching latest meals (v2)…');
            $count = $importer->importLatest($progress);
        } elseif ($letter = $this->option('letter')) {
            $this->info("Importing meals starting with '{$letter}'…");
            $count = $importer->importByLetter($letter, $progress);
        } else {
            $this->info('Importing entire TheMealDB catalog (a–z)…');
            $count = $importer->importAll($progress);
        }

        $this->newLine();
        $this->info("Imported/updated {$count} recipes.");
        return self::SUCCESS;
    }
}

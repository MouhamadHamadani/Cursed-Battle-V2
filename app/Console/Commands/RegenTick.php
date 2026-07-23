<?php

namespace App\Console\Commands;

use App\Services\RegenService;
use Illuminate\Console\Command;

class RegenTick extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:regen-tick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply one energy/health regen tick to all characters';

    /**
     * Execute the console command.
     */
    public function handle(RegenService $regenService): void
    {
        $regenService->tick();

        $this->info('Regen tick applied.');
    }
}

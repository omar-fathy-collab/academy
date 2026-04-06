<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityPatternAnalyze extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:analyze-patterns';
    protected $description = 'Analyze security logs to identify recurring attack patterns';

    public function handle()
    {
        $this->info('Starting security pattern analysis...');
        
        \App\Services\Security\PatternAnalyzerService::analyze();
        
        $this->info('Analysis complete. New patterns identified and clusters updated.');
    }
}

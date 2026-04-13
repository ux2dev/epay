<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Laravel\Commands;

use Illuminate\Console\Command;
use Ux2Dev\Epay\Billing\FileExchange\ObligationFileGenerator;

class GenerateObligationsCommand extends Command
{
    protected $signature = 'epay:generate-obligations {file : Output file path} {--session= : Session ID (YYYYMMDDHHmmss)}';
    protected $description = 'Generate obligation file for ePay.bg Billing file exchange';

    public function handle(): int
    {
        $session = $this->option('session') ?? date('YmdHis');
        $generator = ObligationFileGenerator::create($session);
        $generator->saveTo($this->argument('file'));
        $this->info("Obligation file generated: {$this->argument('file')}");
        return self::SUCCESS;
    }
}

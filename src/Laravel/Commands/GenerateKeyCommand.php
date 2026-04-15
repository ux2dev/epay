<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Laravel\Commands;

use Illuminate\Console\Command;
use Ux2Dev\Epay\KeyGenerator\RsaKeyGenerator;

class GenerateKeyCommand extends Command
{
    protected $signature = 'epay:generate-key {--output= : Directory to save key files} {--bits=2048 : RSA key size} {--passphrase= : Passphrase to encrypt private key}';
    protected $description = 'Generate RSA key pair for ePay.bg merchant';

    public function handle(): int
    {
        $output = $this->option('output') ?? getcwd();
        $result = RsaKeyGenerator::generate(keyBits: (int) $this->option('bits'), passphrase: $this->option('passphrase'));
        $result->saveToDirectory($output);
        $this->info("RSA key pair generated:");
        $this->line("  Private key: {$output}/epay_private.key");
        $this->line("  Public key:  {$output}/epay_public.key");
        return self::SUCCESS;
    }
}

<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Laravel;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Ux2Dev\Epay\Billing\BillingHandler;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Currency;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Enum\SigningMethod;
use Ux2Dev\Epay\Exception\ConfigurationException;
use Ux2Dev\Epay\OneTouch\OneTouchClient;
use Ux2Dev\Epay\Web\WebClient;

final class EpayManager
{
    private string $currentMerchant;
    /** @var array<string, MerchantConfig> */
    private array $configs = [];
    /** @var array<string, WebClient> */
    private array $webClients = [];
    /** @var array<string, BillingHandler> */
    private array $billingHandlers = [];
    /** @var array<string, OneTouchClient> */
    private array $oneTouchClients = [];

    /** @var \Closure|null */
    private ?\Closure $billingInitResolver = null;

    /** @var \Closure|null */
    private ?\Closure $billingConfirmResolver = null;

    public function __construct(private readonly array $config)
    {
        $this->currentMerchant = $config['default'] ?? 'main';
    }

    public function merchant(string $name): self
    {
        $clone = clone $this;
        $clone->currentMerchant = $name;
        return $clone;
    }

    public function getCurrentMerchant(): string { return $this->currentMerchant; }

    public function getConfig(): MerchantConfig { return $this->resolveConfig($this->currentMerchant); }

    public function billingInitUsing(\Closure $resolver): self
    {
        $this->billingInitResolver = $resolver;
        return $this;
    }

    public function billingConfirmUsing(\Closure $resolver): self
    {
        $this->billingConfirmResolver = $resolver;
        return $this;
    }

    public function getBillingInitResolver(): ?\Closure { return $this->billingInitResolver; }

    public function getBillingConfirmResolver(): ?\Closure { return $this->billingConfirmResolver; }

    public function web(): WebClient
    {
        $name = $this->currentMerchant;
        if (!isset($this->webClients[$name])) { $this->webClients[$name] = new WebClient($this->resolveConfig($name)); }
        return $this->webClients[$name];
    }

    public function billing(): BillingHandler
    {
        $name = $this->currentMerchant;
        if (!isset($this->billingHandlers[$name])) { $this->billingHandlers[$name] = new BillingHandler($this->resolveConfig($name)); }
        return $this->billingHandlers[$name];
    }

    public function oneTouch(): OneTouchClient
    {
        $name = $this->currentMerchant;
        if (!isset($this->oneTouchClients[$name])) {
            $factory = new HttpFactory();
            $this->oneTouchClients[$name] = new OneTouchClient($this->resolveConfig($name), new Client(), $factory, $factory);
        }
        return $this->oneTouchClients[$name];
    }

    private function resolveConfig(string $name): MerchantConfig
    {
        if (isset($this->configs[$name])) { return $this->configs[$name]; }
        $merchants = $this->config['merchants'] ?? [];
        if (!isset($merchants[$name])) { throw new ConfigurationException("Merchant \"{$name}\" is not configured"); }
        $m = $merchants[$name];
        $this->configs[$name] = new MerchantConfig(
            merchantId: $m['merchant_id'], secret: $m['secret'],
            environment: Environment::from($m['environment'] ?? 'production'),
            currency: Currency::from($m['currency'] ?? 'EUR'),
            signingMethod: $m['signing_method'] === 'rsa' ? SigningMethod::Rsa : SigningMethod::HmacSha1,
            privateKey: $m['private_key'] ?? null,
            privateKeyPassphrase: $m['private_key_passphrase'] ?? null,
        );
        return $this->configs[$name];
    }
}

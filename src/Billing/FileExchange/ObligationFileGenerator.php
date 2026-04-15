<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\Billing\FileExchange;
use Ux2Dev\Epay\Exception\ConfigurationException;

final readonly class ObligationFileGenerator
{
    /** @param array<string, array{amount: int, name: ?string, address: ?string, dueDate: ?string}> $obligations */
    private function __construct(private string $session, private array $obligations = []) {}

    public static function create(string $session): self
    {
        if (!preg_match('/^\d{14}$/', $session)) {
            throw new ConfigurationException('Session must be in YYYYMMDDHHmmss format (14 digits)');
        }
        return new self($session);
    }

    public function addObligation(string $subscriberId, int $amount, ?string $name = null, ?string $address = null, ?string $dueDate = null): self
    {
        if (isset($this->obligations[$subscriberId])) {
            throw new ConfigurationException("Duplicate subscriber: {$subscriberId}");
        }
        $obligations = $this->obligations;
        $obligations[$subscriberId] = ['amount' => $amount, 'name' => $name, 'address' => $address, 'dueDate' => $dueDate];
        return new self($this->session, $obligations);
    }

    public function toString(): string
    {
        $lines = ["session={$this->session}"];
        foreach ($this->obligations as $subscriberId => $data) {
            $parts = [$subscriberId, number_format($data['amount'] / 100, 2, '.', '')];
            if ($data['name'] !== null) { $parts[] = $data['name']; }
            if ($data['address'] !== null) { $parts[] = $data['address']; }
            if ($data['dueDate'] !== null) { $parts[] = $data['dueDate']; }
            $lines[] = implode('|', $parts);
        }
        return implode("\n", $lines);
    }

    public function saveTo(string $path): void
    {
        $content = $this->toString();
        $cp1251 = mb_convert_encoding($content, 'Windows-1251', 'UTF-8');
        file_put_contents($path, $cp1251);
    }
}

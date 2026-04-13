<?php
declare(strict_types=1);
namespace Ux2Dev\Epay\OneTouch\Response;

final readonly class TokenResponse
{
    public function __construct(public string $token, public int $expires, public string $kin, public string $username, public string $realName) {}
    public static function fromArray(array $data): self { return new self(token: $data['TOKEN'], expires: $data['EXPIRES'], kin: $data['KIN'], username: $data['USERNAME'], realName: $data['REALNAME']); }
}

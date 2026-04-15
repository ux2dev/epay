<?php
declare(strict_types=1);
use Ux2Dev\Epay\Billing\FileExchange\ObligationFileGenerator;
use Ux2Dev\Epay\Exception\ConfigurationException;

test('creates file with session header', function () {
    $content = ObligationFileGenerator::create('20260413120000')->toString();
    expect($content)->toStartWith('session=20260413120000');
});

test('adds obligations with pipe delimiter', function () {
    $gen = ObligationFileGenerator::create('20260413120000')
        ->addObligation(subscriberId: '12345', amount: 8000)
        ->addObligation(subscriberId: '12346', amount: 6500, name: 'Petar Petrov');
    $lines = explode("\n", $gen->toString());
    expect($lines[1])->toBe('12345|80.00')->and($lines[2])->toBe('12346|65.00|Petar Petrov');
});

test('amount converts from stotinki to decimal', function () {
    $lines = explode("\n", ObligationFileGenerator::create('20260413120000')->addObligation(subscriberId: '12345', amount: 16600)->toString());
    expect($lines[1])->toContain('166.00');
});

test('includes optional fields', function () {
    $gen = ObligationFileGenerator::create('20260413120000')
        ->addObligation(subscriberId: '12345', amount: 8000, name: 'Ivan Ivanov', address: 'Sofia, ul. Rakovski 1', dueDate: '20260501');
    $lines = explode("\n", $gen->toString());
    expect($lines[1])->toBe('12345|80.00|Ivan Ivanov|Sofia, ul. Rakovski 1|20260501');
});

test('saveTo writes file', function () {
    $path = sys_get_temp_dir() . '/epay_test_' . uniqid() . '.txt';
    ObligationFileGenerator::create('20260413120000')->addObligation(subscriberId: '12345', amount: 8000)->saveTo($path);
    expect(file_exists($path))->toBeTrue();
    $content = file_get_contents($path);
    expect($content)->toContain('session=20260413120000')->and($content)->toContain('12345');
    unlink($path);
});

test('throws on duplicate subscriber', function () {
    ObligationFileGenerator::create('20260413120000')->addObligation(subscriberId: '12345', amount: 8000)->addObligation(subscriberId: '12345', amount: 5000);
})->throws(ConfigurationException::class, 'Duplicate subscriber');

test('throws on invalid session format', function () {
    ObligationFileGenerator::create('2026');
})->throws(ConfigurationException::class, 'Session must be in YYYYMMDDHHmmss format');

test('immutable - addObligation returns new instance', function () {
    $gen1 = ObligationFileGenerator::create('20260413120000');
    $gen2 = $gen1->addObligation(subscriberId: '12345', amount: 8000);
    expect($gen1)->not->toBe($gen2)->and($gen1->toString())->not->toContain('12345')->and($gen2->toString())->toContain('12345');
});

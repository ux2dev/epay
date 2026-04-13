<?php

declare(strict_types=1);

use Ux2Dev\Epay\Web\Request\BankTransferRequest;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\ConfigurationException;

test('toArray returns all required fields', function () {
    $request = new BankTransferRequest(
        merchant: 'Test Company', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD',
        total: '22.80', statement: 'Monthly fee', pstatement: '123456', environment: Environment::Development,
    );
    $fields = $request->toArray();
    expect($fields['PAGE'])->toBe('paylogin')
        ->and($fields['MERCHANT'])->toBe('Test Company')
        ->and($fields['IBAN'])->toBe('BG80BNBG96611020345678')
        ->and($fields['BIC'])->toBe('BNBGBGSD')
        ->and($fields['TOTAL'])->toBe('22.80')
        ->and($fields['STATEMENT'])->toBe('Monthly fee')
        ->and($fields['PSTATEMENT'])->toBe('123456');
});

test('toArray includes URL_OK and URL_CANCEL when set', function () {
    $request = new BankTransferRequest(
        merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD',
        total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development,
        urlOk: 'https://ok.com', urlCancel: 'https://cancel.com',
    );
    $fields = $request->toArray();
    expect($fields['URL_OK'])->toBe('https://ok.com')->and($fields['URL_CANCEL'])->toBe('https://cancel.com');
});

test('throws on empty merchant', function () {
    new BankTransferRequest(merchant: '', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD', total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
})->throws(ConfigurationException::class, 'merchant must not be empty');

test('throws on invalid IBAN format', function () {
    new BankTransferRequest(merchant: 'Test', iban: 'INVALID', bic: 'BNBGBGSD', total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
})->throws(ConfigurationException::class, 'IBAN format is invalid');

test('throws on invalid BIC format', function () {
    new BankTransferRequest(merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'XX', total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
})->throws(ConfigurationException::class, 'BIC format is invalid');

test('throws on invalid total', function () {
    new BankTransferRequest(merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD', total: '0.00', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
})->throws(ConfigurationException::class, 'total must be greater than 0.01');

test('throws on invalid pstatement length', function () {
    new BankTransferRequest(merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD', total: '22.80', statement: 'Fee', pstatement: '12345', environment: Environment::Development);
})->throws(ConfigurationException::class, 'pstatement must be exactly 6 digits');

test('getPage returns paylogin', function () {
    $request = new BankTransferRequest(merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD', total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
    expect($request->getPage())->toBe('paylogin');
});

test('has no ENCODED or CHECKSUM fields', function () {
    $request = new BankTransferRequest(merchant: 'Test', iban: 'BG80BNBG96611020345678', bic: 'BNBGBGSD', total: '22.80', statement: 'Fee', pstatement: '123456', environment: Environment::Development);
    $fields = $request->toArray();
    expect($fields)->not->toHaveKey('ENCODED')->and($fields)->not->toHaveKey('CHECKSUM');
});

<?php
declare(strict_types=1);
use Ux2Dev\Epay\Billing\BillingHandler;
use Ux2Dev\Epay\Billing\Request\InitRequest;
use Ux2Dev\Epay\Billing\Request\ConfirmRequest;
use Ux2Dev\Epay\Billing\Enum\BillingRequestType;
use Ux2Dev\Epay\Billing\Enum\BillingPaymentType;
use Ux2Dev\Epay\Config\MerchantConfig;
use Ux2Dev\Epay\Enum\Environment;
use Ux2Dev\Epay\Exception\InvalidResponseException;

function billingChecksum(array $params, string $secret): string
{
    return hash_hmac('sha1', \Ux2Dev\Epay\Billing\BillingHandler::buildChecksumData($params), $secret);
}

beforeEach(function () {
    $this->config = new MerchantConfig(merchantId: '0000334', secret: 'testsecret', environment: Environment::Development);
    $this->handler = new BillingHandler($this->config);
});

test('parseInitRequest parses CHECK request', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'CHECK'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseInitRequest($params);
    expect($r)->toBeInstanceOf(InitRequest::class)->and($r->idn)->toBe('12345')->and($r->type)->toBe(BillingRequestType::Check)->and($r->tid)->toBeNull();
});

test('parseInitRequest parses BILLING request with TID', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'BILLING', 'TID' => '20260413121650591535700020'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseInitRequest($params);
    expect($r->type)->toBe(BillingRequestType::Billing)->and($r->tid)->toBe('20260413121650591535700020');
});

test('parseInitRequest parses DEPOSIT request with TOTAL', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'DEPOSIT', 'TID' => '20260413121650591535700020', 'TOTAL' => '16600'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseInitRequest($params);
    expect($r->type)->toBe(BillingRequestType::Deposit)->and($r->total)->toBe(16600);
});

test('parseInitRequest throws on invalid CHECKSUM', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'CHECK', 'CHECKSUM' => 'invalid_checksum_here'];
    $this->handler->parseInitRequest($params);
})->throws(InvalidResponseException::class, 'CHECKSUM verification failed');

test('parseInitRequest throws on missing CHECKSUM', function () {
    $this->handler->parseInitRequest(['IDN' => '12345', 'MERCHANTID' => '0000334', 'TYPE' => 'CHECK']);
})->throws(InvalidResponseException::class, 'Missing CHECKSUM');

test('parseConfirmRequest parses BILLING payment', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '16600', 'TYPE' => 'BILLING'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseConfirmRequest($params);
    expect($r)->toBeInstanceOf(ConfirmRequest::class)->and($r->idn)->toBe('12345')->and($r->total)->toBe(16600)->and($r->type)->toBe(BillingPaymentType::Billing)->and($r->invoices)->toBeNull();
});

test('parseConfirmRequest parses with INVOICES', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '7800', 'TYPE' => 'BILLING', 'INVOICES' => '12345.001,12345.002'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseConfirmRequest($params);
    expect($r->invoices)->toBe('12345.001,12345.002');
});

test('parseConfirmRequest parses PARTIAL payment', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '100', 'TYPE' => 'PARTIAL'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseConfirmRequest($params);
    expect($r->type)->toBe(BillingPaymentType::Partial);
});

test('parseConfirmRequest throws on invalid CHECKSUM', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '16600', 'TYPE' => 'BILLING', 'CHECKSUM' => 'invalid'];
    $this->handler->parseConfirmRequest($params);
})->throws(InvalidResponseException::class, 'CHECKSUM verification failed');

test('parseConfirmRequest parses DEPOSIT payment', function () {
    $params = ['IDN' => '12345', 'MERCHANTID' => '0000334', 'TID' => '20260413121650591535700020', 'DATE' => '20260413181226', 'TOTAL' => '5000', 'TYPE' => 'DEPOSIT'];
    $params['CHECKSUM'] = billingChecksum($params, 'testsecret');
    $r = $this->handler->parseConfirmRequest($params);
    expect($r->type)->toBe(BillingPaymentType::Deposit)->and($r->total)->toBe(5000);
});

// Regression: ePay signs with a trailing "\n" after the last KEY+VALUE pair.
// Confirmed against a real ePay test request on 2026-04-17.
test('buildChecksumData matches the canonical ePay format (with trailing newline)', function () {
    $params = ['IDN' => '2000001', 'MERCHANTID' => '7000005', 'TID' => '20260417171529315661700020', 'TYPE' => 'BILLING'];
    $data = \Ux2Dev\Epay\Billing\BillingHandler::buildChecksumData($params);
    expect($data)->toBe("IDN2000001\nMERCHANTID7000005\nTID20260417171529315661700020\nTYPEBILLING\n");
    expect(hash_hmac('sha1', $data, 'cUAMSp39r6B2pPh9PjSh5Rd6gYvDVudG'))
        ->toBe('5a1348bb9578cbe28785d64888c4df81325d6660');
});

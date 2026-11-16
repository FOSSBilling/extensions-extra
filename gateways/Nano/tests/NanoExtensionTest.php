<?php

declare(strict_types=1);

/*
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * Tests for the extension shape: src/Nano.php must load its own helpers from
 * src/lib/ (FOSSBilling's autoloader does not look there), refuse to start
 * unconfigured or with a mistyped account, and the claims ledger must hold
 * its one-block-one-invoice rule across separate ledger objects (two PHP
 * workers) and refuse an order id that could forge a claim line.
 */

if (!interface_exists('FOSSBilling\Core\Container\InjectionAwareInterface')) {
    // Only for running outside a FOSSBilling install; inside one the real
    // interface is already loaded and this is skipped.
    eval('namespace FOSSBilling\Core\Container; interface InjectionAwareInterface { public function setDi(\Pimple\Container $di): void; public function getDi(): ?\Pimple\Container; }');
}

require_once dirname(__DIR__) . '/src/Nano.php';

const NANO_EXT_PAY_TO = 'nano_1111111111111111111111111111111111111111111111111111hifc8npp';

function nanoExtClaimsPath(): string
{
    return sys_get_temp_dir() . '/nano-ext-claims-' . bin2hex(random_bytes(6)) . '/claims.log';
}

test('the adapter loads its helpers from lib/ beside it', function (): void {
    expect(class_exists('Payment_Adapter_Nano', false))->toBeTrue();
    foreach (['Blake2b', 'Address', 'Amount', 'Binding', 'Verifier', 'ClaimsFile'] as $helper) {
        expect(class_exists('Payment_Nano_' . $helper, false))->toBeTrue();
    }
});

test('the adapter refuses to start without an account and a node', function (): void {
    expect(fn () => new Payment_Adapter_Nano(['rpc_url' => 'https://node.example']))->toThrow(Payment_Exception::class);
    expect(fn () => new Payment_Adapter_Nano(['nano_address' => NANO_EXT_PAY_TO]))->toThrow(Payment_Exception::class);
});

test('the adapter refuses a mistyped account (checksum)', function (): void {
    $typo = substr(NANO_EXT_PAY_TO, 0, -1) . 'q';
    expect(fn () => new Payment_Adapter_Nano(['nano_address' => $typo, 'rpc_url' => 'https://node.example']))->toThrow(Payment_Exception::class);
});

test('a configured adapter starts and never asks for manual approval', function (): void {
    $adapter = new Payment_Adapter_Nano(['nano_address' => NANO_EXT_PAY_TO, 'rpc_url' => 'https://node.example']);
    expect($adapter instanceof Payment_Adapter_Nano)->toBeTrue();
    expect(Payment_Adapter_Nano::requiresManualApproval())->toBeFalse();
});

test('the config form asks for no key or seed and points at the shipped logo', function (): void {
    $config = Payment_Adapter_Nano::getConfig();
    expect($config['supports_one_time_payments'])->toBeTrue();
    expect($config['supports_subscriptions'])->toBeFalse();
    expect($config['logo']['logo'])->toBe('/Nano/Nano.svg');
    expect(is_file(dirname(__DIR__) . '/src/Nano.svg'))->toBeTrue();
    $fields = implode(' ', array_keys($config['form']));
    expect(preg_match('/seed|private|secret|key/i', $fields))->toBe(0);
});

test('two ledger objects on one file (two workers) cannot both claim a block', function (): void {
    $path = nanoExtClaimsPath();
    $hash = str_repeat('E', 64);

    try {
        $workerA = new Payment_Nano_ClaimsFile($path);
        $workerB = new Payment_Nano_ClaimsFile($path);
        expect($workerA->claim($hash, 'inv-a'))->toBeTrue();
        expect(fn () => $workerB->claim($hash, 'inv-b'))->toThrow(Payment_Exception::class);
        expect($workerB->claim(strtolower($hash), 'inv-a'))->toBeFalse();
        expect($workerB->isClaimed($hash))->toBeTrue();
    } finally {
        @unlink($path);
        @rmdir(dirname($path));
    }
});

test('an order id with a tab or newline is refused, so no claim line is forged', function (): void {
    $path = nanoExtClaimsPath();

    try {
        $ledger = new Payment_Nano_ClaimsFile($path);
        expect(fn () => $ledger->claim(str_repeat('A', 64), "inv-1\n" . str_repeat('B', 64) . "\tinv-2"))->toThrow(Payment_Exception::class);
        expect(fn () => $ledger->claim(str_repeat('A', 64), ''))->toThrow(Payment_Exception::class);
        expect($ledger->isClaimed(str_repeat('B', 64)))->toBeFalse();
        expect($ledger->isClaimed(str_repeat('A', 64)))->toBeFalse();
    } finally {
        @unlink($path);
        @rmdir(dirname($path));
    }
});

test('the claims file is created under a missing directory and survives a restart', function (): void {
    $path = nanoExtClaimsPath();
    $hash = str_repeat('C', 64);

    try {
        expect((new Payment_Nano_ClaimsFile($path))->claim($hash, 'inv-c'))->toBeTrue();
        $restarted = new Payment_Nano_ClaimsFile($path);
        expect(fn () => $restarted->claim($hash, 'inv-d'))->toThrow(Payment_Exception::class);
        expect(substr_count((string) file_get_contents($path), "\n"))->toBe(1);
    } finally {
        @unlink($path);
        @rmdir(dirname($path));
    }
});

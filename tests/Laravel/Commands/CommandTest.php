<?php
declare(strict_types=1);

test('epay:generate-key creates key files', function () {
    $dir = sys_get_temp_dir() . '/epay_cmd_test_' . uniqid();
    mkdir($dir);
    $this->artisan('epay:generate-key', ['--output' => $dir])->assertSuccessful();
    expect(file_exists($dir . '/epay_private.key'))->toBeTrue()->and(file_exists($dir . '/epay_public.key'))->toBeTrue();
    unlink($dir . '/epay_private.key'); unlink($dir . '/epay_public.key'); rmdir($dir);
});

test('epay:generate-key with passphrase creates encrypted key', function () {
    $dir = sys_get_temp_dir() . '/epay_cmd_test_' . uniqid();
    mkdir($dir);
    $this->artisan('epay:generate-key', ['--output' => $dir, '--passphrase' => 'secret123'])->assertSuccessful();
    expect(file_get_contents($dir . '/epay_private.key'))->toContain('ENCRYPTED');
    unlink($dir . '/epay_private.key'); unlink($dir . '/epay_public.key'); rmdir($dir);
});

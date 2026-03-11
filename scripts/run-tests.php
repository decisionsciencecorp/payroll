#!/usr/bin/env php
<?php
/**
 * Run PHPUnit using the same PHP binary that invoked this script.
 * Use when PHP is not on PATH: /path/to/php scripts/run-tests.php
 */
$projectRoot = dirname(__DIR__);
$phpunit = $projectRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
$php = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
chdir($projectRoot);
$cmd = sprintf('%s %s %s', escapeshellcmd($php), escapeshellarg($phpunit), implode(' ', array_map('escapeshellarg', array_slice($argv, 1))));
passthru($cmd, $status);
exit($status);

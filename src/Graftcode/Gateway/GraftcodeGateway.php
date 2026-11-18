<?php

declare(strict_types=1);

namespace Graftcode\Gateway;

use HypertubePhpCore\receiver\Receiver;
use RuntimeException;

final class GraftcodeGateway
{
    private const HOSTED_RUNTIME = 'php';

    /**
     * Starts GG with callbacks dispatched directly on GG worker threads.
     *
     * PHP (non-ZTS) cannot execute userland code on foreign threads, so for a working
     * PHP host use startPolling()/run() from a long-lived CLI process instead.
     */
    public static function start(string $argsJsonUtf8): int
    {
        self::ensureGma();
        if (!NativeApi::hasCallback()) {
            self::setCallback();
        }
        return NativeApi::start(self::ensureHostedRuntime($argsJsonUtf8));
    }

    /**
     * Starts GG in thread-affine poll mode: send-command callbacks are queued by GG and
     * executed on this PHP thread whenever poll() or waitForExit() is called.
     * Requires a GG build newer than 1.4.12 and a long-lived PHP CLI process.
     */
    public static function startPolling(string $argsJsonUtf8): int
    {
        if (!NativeApi::supportsPolling()) {
            throw new RuntimeException(
                'The loaded Graftcode Gateway library does not support poll mode '
                . '(gg_set_callback_dispatch_mode/gg_poll); a GG build newer than 1.4.12 is required.'
            );
        }

        NativeApi::setCallbackDispatchMode(NativeApi::GG_CALLBACK_DISPATCH_QUEUED);
        return self::start($argsJsonUtf8);
    }

    /**
     * Executes pending GG callbacks on the calling thread (poll mode only).
     *
     * @return int number of executed callbacks
     */
    public static function poll(int $timeoutMs = 0): int
    {
        return NativeApi::poll($timeoutMs);
    }

    /**
     * Starts GG in poll mode and serves callbacks on this thread until GG exits.
     *
     * @return array{status: int, exitCode: int}
     */
    public static function run(string $argsJsonUtf8): array
    {
        $status = self::startPolling($argsJsonUtf8);
        if ($status !== NativeApi::GG_STATUS_OK) {
            throw new RuntimeException(NativeApi::lastError());
        }

        // gg_wait() executes queued callbacks while waiting.
        return self::waitForExit();
    }

    private static function ensureGma(): void
    {
        $autoload = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'gma' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }
    }

    private static function ensureHostedRuntime(string $paramsJson): string
    {
        if ($paramsJson === '' || $paramsJson === '{}') {
            return '["--runtime","' . self::HOSTED_RUNTIME . '"]';
        }

        if (strpos($paramsJson, '"--runtime"') !== false) {
            return $paramsJson;
        }

        if (isset($paramsJson[0]) && $paramsJson[0] === '[') {
            $params = json_decode($paramsJson, true, 512, JSON_THROW_ON_ERROR);
            return json_encode(
                array_merge(['--runtime', self::HOSTED_RUNTIME], $params),
                JSON_THROW_ON_ERROR
            );
        }

        return $paramsJson;
    }

    public static function stop(): int
    {
        return NativeApi::stop();
    }

    /**
     * @return array{status: int, exitCode: int}
     */
    public static function waitForExit(): array
    {
        return NativeApi::waitForExit();
    }

    public static function setCallback(?callable $handler = null): void
    {
        NativeApi::setCallback($handler ?? static function (string $request): string {
            $response = Receiver::sendCommand($request);
            if ($response === '') {
                throw new RuntimeException('Hypertube Receiver returned an empty response');
            }

            return $response;
        });
    }
}

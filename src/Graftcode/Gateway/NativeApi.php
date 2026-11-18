<?php

declare(strict_types=1);

namespace Graftcode\Gateway;

use FFI;
use RuntimeException;
use Throwable;

final class NativeApi
{
    public const GG_STATUS_OK = 0;
    public const GG_STATUS_INVALID_ARGUMENT = 1;
    public const GG_STATUS_ALREADY_STARTED = 2;
    public const GG_STATUS_NOT_STARTED = 3;
    public const GG_STATUS_INTERNAL_ERROR = 4;

    /** Default: callbacks run directly on the GG worker thread. */
    public const GG_CALLBACK_DISPATCH_DIRECT = 0;
    /** Thread-affine: callbacks run on the thread calling gg_wait()/gg_poll(). */
    public const GG_CALLBACK_DISPATCH_QUEUED = 1;

    private static ?FFI $ffi = null;

    private static ?FFI $cRuntime = null;

    private static ?string $libraryPath = null;

    /** @var array<string, FFI|null> optional functions of newer GG builds */
    private static array $optional = [];

    /** @var callable|null */
    private static $sendCommandHandler = null;

    /** @var \Closure|null keeps the FFI callback alive while it is registered */
    private static ?\Closure $callback = null;

    public static function setCallback(callable $handler): void
    {
        self::$sendCommandHandler = $handler;
        $ffi = self::load();

        $callback = static function ($request, $responseOut, $userData): int {
            unset($userData);

            try {
                $handler = self::$sendCommandHandler;
                if ($handler === null) {
                    throw new RuntimeException('send-command handler has not been registered');
                }

                $requestBytes = self::readBuffer($request);
                $responseBytes = $handler($requestBytes);
                self::writeBuffer($responseOut, $responseBytes);
                return self::GG_STATUS_OK;
            } catch (Throwable $error) {
                // Exceptions must not escape an FFI callback.
                self::reportCallbackError($error);
                return self::GG_STATUS_INTERNAL_ERROR;
            }
        };

        $status = $ffi->gg_set_send_command_callback($callback, null);
        if ($status !== self::GG_STATUS_OK) {
            throw new RuntimeException(self::lastError());
        }
        self::$callback = $callback;
    }

    public static function hasCallback(): bool
    {
        return self::$callback !== null;
    }

    /**
     * Whether the loaded GG build supports thread-affine (queued) callback dispatch.
     */
    public static function supportsPolling(): bool
    {
        return self::optional('gg_set_callback_dispatch_mode', 'int32_t gg_set_callback_dispatch_mode(int32_t mode);') !== null
            && self::optional('gg_poll', 'int32_t gg_poll(int32_t timeout_ms, int32_t* processed_count);') !== null;
    }

    public static function setCallbackDispatchMode(int $mode): void
    {
        $ffi = self::optional('gg_set_callback_dispatch_mode', 'int32_t gg_set_callback_dispatch_mode(int32_t mode);');
        if ($ffi === null) {
            throw new RuntimeException(
                'The loaded Graftcode Gateway library does not support gg_set_callback_dispatch_mode(); '
                . 'a GG build newer than 1.4.12 is required for PHP poll mode.'
            );
        }

        $status = (int) $ffi->gg_set_callback_dispatch_mode($mode);
        if ($status !== self::GG_STATUS_OK) {
            throw new RuntimeException(self::lastError());
        }
    }

    /**
     * Executes queued send-command callbacks on the calling thread (poll mode).
     * Waits up to $timeoutMs for work when nothing is queued.
     *
     * @return int number of executed callbacks
     */
    public static function poll(int $timeoutMs = 0): int
    {
        $ffi = self::optional('gg_poll', 'int32_t gg_poll(int32_t timeout_ms, int32_t* processed_count);');
        if ($ffi === null) {
            throw new RuntimeException('The loaded Graftcode Gateway library does not support gg_poll().');
        }

        $processed = $ffi->new('int32_t');
        $status = (int) $ffi->gg_poll($timeoutMs, FFI::addr($processed));
        if ($status !== self::GG_STATUS_OK) {
            throw new RuntimeException(self::lastError());
        }
        return (int) $processed->cdata;
    }

    public static function isRunning(): bool
    {
        $ffi = self::optional('gg_is_running', 'int32_t gg_is_running(int32_t* running);');
        if ($ffi === null) {
            throw new RuntimeException('The loaded Graftcode Gateway library does not support gg_is_running().');
        }

        $running = $ffi->new('int32_t');
        $status = (int) $ffi->gg_is_running(FFI::addr($running));
        if ($status !== self::GG_STATUS_OK) {
            throw new RuntimeException(self::lastError());
        }
        return (int) $running->cdata !== 0;
    }

    public static function start(string $argsJsonUtf8): int
    {
        return (int) self::load()->gg_start($argsJsonUtf8);
    }

    public static function stop(): int
    {
        return (int) self::load()->gg_stop();
    }

    public static function waitForExit(): array
    {
        $exitCode = self::load()->new('int32_t');
        $status = (int) self::load()->gg_wait(FFI::addr($exitCode));
        return ['status' => $status, 'exitCode' => (int) $exitCode->cdata];
    }

    public static function lastError(): string
    {
        $message = self::load()->gg_last_error();
        return $message !== null ? (string) $message : '';
    }

    private const CDEF = <<<'CDEF'
        typedef struct {
            uint8_t* data;
            size_t size;
        } gg_byte_buffer;

        typedef int32_t gg_status;
        typedef gg_status (*gg_send_command_callback)(
            gg_byte_buffer request,
            gg_byte_buffer* response_out,
            void* user_data
        );

        gg_status gg_start(const char* args_json_utf8);
        gg_status gg_stop(void);
        gg_status gg_wait(int32_t* exit_code);
        gg_status gg_set_send_command_callback(gg_send_command_callback callback, void* user_data);
        const char* gg_last_error(void);
    CDEF;

    private static function load(): FFI
    {
        if (self::$ffi !== null) {
            return self::$ffi;
        }

        $relativePath = self::nativeLibraryRelativePath();
        try {
            $libraryPath = self::extractNativeLibrary($relativePath);
            self::$ffi = FFI::cdef(self::CDEF, $libraryPath);
            self::$libraryPath = $libraryPath;
        } catch (Throwable $error) {
            if ($relativePath !== 'Linux/X64/lib/libgraftcode_gateway_api.so') {
                throw $error;
            }

            $fallbackPath = 'rhel-9-x64/lib/libgraftcode_gateway_api.so';
            try {
                $libraryPath = self::extractNativeLibrary($fallbackPath);
                self::$ffi = FFI::cdef(self::CDEF, $libraryPath);
                self::$libraryPath = $libraryPath;
            } catch (Throwable $fallbackError) {
                throw new RuntimeException(
                    $error->getMessage() . '; rhel-9-x64 fallback failed: ' . $fallbackError->getMessage(),
                    0,
                    $fallbackError
                );
            }
        }

        return self::$ffi;
    }

    /**
     * Binds an optional GG function that older builds (<= 1.4.12) do not export.
     */
    private static function optional(string $name, string $declaration): ?FFI
    {
        if (array_key_exists($name, self::$optional)) {
            return self::$optional[$name];
        }

        self::load();
        try {
            self::$optional[$name] = FFI::cdef($declaration, self::$libraryPath);
        } catch (Throwable $ignored) {
            self::$optional[$name] = null;
        }
        return self::$optional[$name];
    }

    private static function reportCallbackError(Throwable $error): void
    {
        $message = get_class($error) . ': ' . $error->getMessage();
        fwrite(STDERR, '[graftcode-gateway-sdk] send-command callback failed: ' . $error . PHP_EOL);

        $ffi = self::optional('gg_set_callback_error', 'int32_t gg_set_callback_error(const char* message);');
        if ($ffi !== null) {
            try {
                $ffi->gg_set_callback_error($message);
            } catch (Throwable $ignored) {
                // Best effort only.
            }
        }
    }

    private static function nativeLibraryRelativePath(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $osDir = 'Windows';
            $libraryName = 'graftcode_gateway_api.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $osDir = 'MacOs';
            $libraryName = 'libgraftcode_gateway_api.dylib';
        } elseif (PHP_OS_FAMILY === 'Linux') {
            $osDir = 'Linux';
            $libraryName = 'libgraftcode_gateway_api.so';
        } else {
            throw new RuntimeException('Unsupported operating system: ' . PHP_OS_FAMILY . ' (expected windows/linux/macos)');
        }

        $machine = strtolower(php_uname('m'));
        if (in_array($machine, ['x86_64', 'amd64', 'x64'], true)) {
            $archDir = 'X64';
        } elseif (in_array($machine, ['aarch64', 'arm64'], true)) {
            $archDir = 'ARM64';
        } else {
            throw new RuntimeException('Unsupported architecture: ' . $machine . ' (expected x64/arm64)');
        }

        return $osDir . '/' . $archDir . '/lib/' . $libraryName;
    }

    private static function extractNativeLibrary(string $relativePath): string
    {
        $sourcePath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'native' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($sourcePath)) {
            throw new RuntimeException("Missing native library: {$sourcePath}");
        }

        $targetDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'graftcode-gateway-api-' . bin2hex(random_bytes(8));
        if (!mkdir($targetDirectory) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Failed to create directory: {$targetDirectory}");
        }
        $targetPath = $targetDirectory . DIRECTORY_SEPARATOR . basename($relativePath);
        if (!copy($sourcePath, $targetPath)) {
            throw new RuntimeException("Failed to copy native library: {$sourcePath}");
        }
        return $targetPath;
    }

    /**
     * @return string
     */
    private static function readBuffer($buffer): string
    {
        $size = (int) $buffer->size;
        if ($size <= 0 || $buffer->data === null) {
            return '';
        }

        return FFI::string($buffer->data, $size);
    }

    /**
     * @param string $bytes
     */
    private static function writeBuffer($responseOut, string $bytes): void
    {
        if ($responseOut === null) {
            throw new RuntimeException('response_out cannot be null');
        }

        $length = strlen($bytes);
        if ($length === 0) {
            $responseOut->data = null;
            $responseOut->size = 0;
            throw new RuntimeException('send-command handler returned an empty response');
        }

        // GG takes ownership of the buffer and releases it with the C runtime free().
        // FFI::new memory must be released with FFI::free, so allocate with malloc.
        $pointer = self::cRuntime()->malloc($length);
        if (FFI::isNull($pointer)) {
            throw new RuntimeException("malloc({$length}) failed for send-command response");
        }

        $ffi = self::load();
        $bytesPointer = $ffi->cast('uint8_t*', $pointer);
        FFI::memcpy($bytesPointer, $bytes, $length);
        $responseOut->data = $bytesPointer;
        $responseOut->size = $length;
    }

    private static function cRuntime(): FFI
    {
        if (self::$cRuntime !== null) {
            return self::$cRuntime;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $library = 'ucrtbase.dll';
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $library = 'libSystem.dylib';
        } else {
            $library = 'libc.so.6';
        }

        self::$cRuntime = FFI::cdef('void* malloc(size_t size);', $library);
        return self::$cRuntime;
    }
}

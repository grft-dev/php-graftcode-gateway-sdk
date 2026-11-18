#pragma once

#include <stddef.h>
#include <stdint.h>

#if defined(_WIN32)
#  if defined(GG_BUILDING_LIBRARY)
#    define GG_API __declspec(dllexport)
#  else
#    define GG_API __declspec(dllimport)
#  endif
#  define GG_CALL __cdecl
#elif defined(__GNUC__) || defined(__clang__)
#  define GG_API __attribute__((visibility("default")))
#  define GG_CALL
#else
#  define GG_API
#  define GG_CALL
#endif

#ifdef __cplusplus
extern "C" {
#endif

typedef int32_t gg_status;
enum {
	GG_STATUS_OK = 0,
	GG_STATUS_INVALID_ARGUMENT = 1,
	GG_STATUS_ALREADY_STARTED = 2,
	GG_STATUS_NOT_STARTED = 3,
	GG_STATUS_INTERNAL_ERROR = 4
};

typedef struct gg_byte_buffer {
	const uint8_t* data;
	size_t size;
} gg_byte_buffer;

/* Callback dispatch modes, see gg_set_callback_dispatch_mode(). */
enum {
	/* Default: the callback runs directly on the GG worker thread that issues the command. */
	GG_CALLBACK_DISPATCH_DIRECT = 0,
	/*
	 * Thread-affine: commands are queued and the callback runs on the thread that calls
	 * gg_wait() or gg_poll(). Intended for runtimes that cannot execute code on foreign
	 * threads (for example non-ZTS PHP).
	 */
	GG_CALLBACK_DISPATCH_QUEUED = 1
};

/*
 * Invoked when GG sends a command to the hosted runtime in SDK mode.
 * On success, return GG_STATUS_OK. A response is optional: leave
 * response_out->data null and response_out->size 0 when there are no bytes.
 * The JVM adapter uses that form for a null or empty byte array.
 * When response_out->data is non-null, allocate it with malloc. GG takes
 * ownership and frees it with free(), including when size is 0.
 * A null data pointer with a non-zero size is an error.
 * On failure, return a non-OK status and leave response_out untouched. The host
 * keeps ownership of any pointer it wrote there. Optionally describe the error
 * with gg_set_callback_error() (safe from any thread) or gg_report_error().
 */
typedef gg_status(GG_CALL* gg_send_command_callback)(
	gg_byte_buffer request,
	gg_byte_buffer* response_out,
	void* user_data);

/*
 * Starts the process-wide GG singleton on a native worker thread.
 * args_json_utf8 must be a UTF-8 JSON array of strings and must not include
 * argv[0], for example: ["module.dll", "--port", "8888"].
 */
GG_API gg_status GG_CALL gg_start(const char* args_json_utf8);

/* Requests shutdown. The call is idempotent after a successful gg_start. */
GG_API gg_status GG_CALL gg_stop(void);

/*
 * Waits for GG to terminate and writes its process-style exit code.
 * The function may be called more than once after a successful gg_start.
 */
GG_API gg_status GG_CALL gg_wait(int32_t* exit_code);

/*
 * Registers the host callback used by SDK builds instead of native Hypertube DLLs.
 * Pass null to clear the callback.
 */
GG_API gg_status GG_CALL gg_set_send_command_callback(
	gg_send_command_callback callback,
	void* user_data);

/* Clears the registered send-command callback. */
GG_API gg_status GG_CALL gg_clear_send_command_callback(void);

/*
 * Returns a UTF-8 description of the last API error on the calling thread.
 * The pointer remains valid until the next GG API call on that thread.
 */
GG_API const char* GG_CALL gg_last_error(void);

/*
 * Records an API error for the calling thread and returns the provided status.
 * Intended for language bindings validating arguments before calling gg_*.
 */
GG_API gg_status GG_CALL gg_report_error(gg_status status, const char* message);

/*
 * Sets the error message for the send-command callback that is currently running.
 * Send-command callbacks are not re-entrant, so this message belongs to that
 * invocation alone.
 * This can be called from a different thread than the GG worker (for example
 * koffi/Node.js). Call it before returning a non-OK status from the callback.
 */
GG_API gg_status GG_CALL gg_set_callback_error(const char* message);

/*
 * Selects how send-command callbacks are dispatched (GG_CALLBACK_DISPATCH_*).
 * The default is GG_CALLBACK_DISPATCH_DIRECT. The mode must be selected before
 * gg_start(); calling this function afterward returns GG_STATUS_ALREADY_STARTED.
 */
GG_API gg_status GG_CALL gg_set_callback_dispatch_mode(int32_t mode);

/*
 * In GG_CALLBACK_DISPATCH_QUEUED mode, executes queued send-command callbacks on the
 * calling thread. Waits up to timeout_ms for work when the queue is empty (0 = do not wait).
 * processed_count (optional) receives the number of executed callbacks.
 * gg_wait() also executes queued callbacks while it waits.
 * Concurrent gg_poll and gg_wait calls are safe: callbacks still run one at a time,
 * on whichever caller owns the pump.
 */
GG_API gg_status GG_CALL gg_poll(int32_t timeout_ms, int32_t* processed_count);

/* Writes 1 to running while the GG worker started by gg_start() is still running, otherwise 0. */
GG_API gg_status GG_CALL gg_is_running(int32_t* running);
#ifdef __cplusplus
}
#endif

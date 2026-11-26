<?php

declare(strict_types=1);

/**
 * In-memory log handler for the `/features/log` demo.
 *
 * CI4 ships three handlers (Errorlog, File, ChromeLogger) and every one writes
 * OUTSIDE the process. A benchmark row must not measure disk or stderr I/O that
 * is not the feature under test, so this extends the framework's documented
 * extension point (`CodeIgniter\Log\Handlers\BaseHandler`) with a buffering
 * handler — the same choice the other entrants make (azera's MemoryLogger,
 * Phalcon's MemoryLoggerAdapter, Cake's `ArrayLog`).
 *
 * WHY THIS FILE HAD TO EXIST AT ALL. `apps/codeigniter/Config/Logger.php` set
 * `$threshold = 0`, which CI4 resolves to `range(1, 0)` → NO loggable levels.
 * `Logger::log()` then returns before touching a handler, so
 * `log_message('info', …)` was a no-op and `/features/log` reported a hand-built
 * array literal while nothing had been logged.
 *
 * WHAT A RECORD CAN CONTAIN. `Logger::log()` interpolates `{placeholder}`s from
 * the context into the message BEFORE calling `handle($level, $message)`, so the
 * interface hands over exactly two things. The context is already folded into
 * the message; this handler does not pretend to have kept it separately.
 */

namespace Ci4App\Support;

use CodeIgniter\Log\Handlers\BaseHandler;

final class MemoryLogHandler extends BaseHandler
{
    /** @var list<array{level: string, message: string}> */
    private array $records = [];

    /**
     * The instance the CURRENT logger is using.
     *
     * CI4 instantiates handlers LAZILY inside `Logger::log()`
     * (`$this->handlers[$className] = new $className($config)`), so the logger's
     * `handlers` property is empty until something is logged — and the demo has to
     * clear BEFORE it logs.
     *
     * A process-wide static cache is NOT enough: in worker mode
     * `resetForWorkerMode()` drops the shared services, so the next request gets a
     * NEW Logger with a NEW handler while the static would still point at the
     * previous one's buffer. The demo would then clear one handler, log into
     * another and read back the first — empty (measured: request #2 came back with
     * no entries). So resolve from the LIVE logger every time, priming one
     * throwaway write only when the framework has not built a handler yet.
     */
    public static function resolve(): ?self
    {
        $found = self::fromLiveLogger();
        if ($found !== null) {
            return $found;
        }

        // First use in this request/process: the Logger builds its handlers on the
        // first write. The caller clears immediately afterwards, so this record is
        // discarded before anything is read back.
        log_message('info', 'prime /features/log handler');

        return self::fromLiveLogger();
    }

    private static function fromLiveLogger(): ?self
    {
        $logger = \Config\Services::logger();

        $handlers = (new \ReflectionClass($logger))->getProperty('handlers')->getValue($logger);
        foreach ($handlers as $handler) {
            if ($handler instanceof self) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Collect one record — the last stop before output, which is what makes
     * reading it back proof that delivery happened rather than an assertion
     * that it should have.
     */
    public function handle($level, $message): bool
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
        ];

        // true = let any remaining handler run too (CI4 stops the chain on false).
        return true;
    }

    /**
     * The captured records.
     *
     * @return list<array{level: string, message: string}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Drop everything captured so far.
     *
     * The demo clears before logging: a resident worker reuses one process for
     * thousands of requests, so entries from the previous request would
     * otherwise leak into this one's response.
     */
    public function clear(): void
    {
        $this->records = [];
    }
}

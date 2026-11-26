<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `/features/log` must log through the framework's OWN logger, and report records
 * that logger actually captured — the same way on every entrant.
 *
 * WHY THIS EXISTS. The row was a demo of nothing on five of seven entrants:
 *
 *  - **CakePHP** registered no log engine at all. `Log::write()` iterates
 *    `LogEngineRegistry` and returns `false` when it is empty, so every record was
 *    discarded — and the endpoint then returned a hand-built array literal
 *    containing a `microtime()` delta measured across the no-op.
 *  - **CodeIgniter** configured `$threshold = 0`, which CI4 resolves to
 *    `range(1, 0)` → no loggable levels. `Logger::log()` returned before reaching a
 *    handler, so `log_message('info', …)` was a no-op and the endpoint printed the
 *    same kind of fabricated literal.
 *  - **Laravel, Symfony and Spiral** never touched a logger: their interceptors
 *    appended strings to an `array &$entries` the APPLICATION owned, then reported
 *    that array. The shared description claimed "the framework's own logger — PSR-3
 *    everywhere", which was false for all three.
 *
 * The failure mode is the same one `ExplicitAopFacilityTest` guards: the row
 * measures a workaround and reports it as the feature. Here it is worse, because
 * two entrants reported log entries that never reached a logger at all — a payload
 * that LOOKS successful while nothing happened.
 *
 * These are source contracts plus one payload-shape check, because booting all
 * seven frameworks in one process is exactly what the harness isolates per app
 * (see verify-app.php's header).
 */
final class LoggingFacilityTest extends TestCase
{
    /**
     * Per entrant: the application files that make up the `/features/log` path, and
     * the framework facility the record has to come from.
     */
    private const LOG_PATH = [
        'laravel' => [
            'apps/laravel/app/Http/Middleware/Interceptors/LogInterceptor.php',
            'apps/laravel/app/Service/AopService.php',
        ],
        'symfony' => [
            'apps/symfony/src/Service/Interceptor/LogInterceptor.php',
            'apps/symfony/src/Service/AopService.php',
        ],
        'spiral' => [
            'apps/spiral/src/Interceptor/LogInterceptor.php',
            'apps/spiral/src/Service/AopService.php',
        ],
    ];

    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::repoRoot() . '/' . $relative);
    }

    /**
     * The same file with comments and docblocks removed.
     *
     * Needed because the docblocks deliberately QUOTE the old broken shape
     * (`new LogInterceptor($entries)` over `array &$entries`) when explaining why it
     * was wrong — so a raw text match cannot tell the explanation from the code.
     */
    private static function code(string $relative): string
    {
        $code = '';
        foreach (token_get_all(self::read($relative)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
                continue;
            }
            $code .= $token;
        }

        return $code;
    }

    /**
     * The three pipeline entrants must pass a PSR-3 logger into the interceptor.
     *
     * The regression this catches is specific: `new LogInterceptor($entries)` where
     * `$entries` is an `array &$entries` parameter. That shape looks like logging and
     * logs nothing, so the constructor type is the thing to pin.
     */
    public function testPipelineEntrantsPassAPsr3LoggerNotAnArray(): void
    {
        foreach (self::LOG_PATH as $app => $files) {
            $interceptor = self::code($files[0]);

            self::assertMatchesRegularExpression(
                '/use\s+Psr\\\\Log\\\\LoggerInterface;/',
                $interceptor,
                "{$app}'s LogInterceptor must depend on Psr\\Log\\LoggerInterface"
            );
            self::assertDoesNotMatchRegularExpression(
                '/\barray\s+&\$\w+/',
                $interceptor,
                "{$app}'s LogInterceptor must not append to an application-owned array — that is the "
                    . 'workaround that measured array construction instead of logging'
            );

            $service = self::code($files[1]);

            self::assertMatchesRegularExpression(
                '/new\s+LogInterceptor\(\s*(\$this->(logger|logFactory)|\$\w+)/',
                $service,
                "{$app}'s AopService must hand the interceptor a real logger"
            );
            self::assertMatchesRegularExpression(
                '/->clear\(\)/',
                $service,
                "{$app} must clear the logger's buffer before writing, or a resident worker's previous "
                    . 'request leaks into this response'
            );
        }
    }

    /**
     * Each pipeline entrant reads its records back from the framework's facility.
     *
     * A readback is what makes the payload evidence rather than a restatement: the
     * controller cannot fabricate an entry that the logger never received.
     */
    public function testPipelineEntrantsReadRecordsBackFromTheirOwnLogger(): void
    {
        $expected = [
            'laravel' => '->getRecords()',              // Monolog TestHandler
            'symfony' => '->records()',                  // PSR-3 MemoryLogger
            'spiral'  => '->records()',                  // ListenerRegistry listener
        ];

        foreach ($expected as $app => $readback) {
            $service = self::read(self::LOG_PATH[$app][1]);

            self::assertStringContainsString(
                $readback,
                $service,
                "{$app} must read records back from the logger it logged through"
            );
            self::assertStringContainsString(
                'logRecords()',
                $service,
                "{$app}'s readback must be exposed as logRecords() so the controller cannot rebuild it"
            );
        }
    }

    /**
     * CakePHP must register a log engine, because Cake ships none by default.
     *
     * `Log::write()` returns `false` with an empty registry. Before this, nothing in
     * the app called `Log::setConfig()`, so the endpoint's record was discarded while
     * the response claimed it existed.
     */
    public function testCakeRegistersALogEngineAndReadsItBack(): void
    {
        $application = self::read('apps/cakephp/src/Application.php');

        self::assertStringContainsString(
            'Log::setConfig(',
            $application,
            'Cake ships no log engine; the app must register one or Log::write() returns false and '
                . 'drops the record'
        );

        $controller = self::read('apps/cakephp/src/Controller/FeatureController.php');

        self::assertStringContainsString(
            'featureLogEngine()',
            $controller,
            'the endpoint must read the engine back rather than build the entries itself'
        );
        self::assertStringContainsString(
            '$engine->records()',
            $controller,
            'the reported entries must come from the engine, not a literal'
        );
    }

    /**
     * CodeIgniter must make `info` loggable and capture it in-process.
     *
     * `$threshold = 0` is CI4's "log nothing" setting: it resolves to no loggable
     * levels and `Logger::log()` returns before any handler runs. The config file now
     * documents why that made the row vacuous.
     */
    public function testCodeIgniterLogsAtInfoLevelIntoAnInMemoryHandler(): void
    {
        $config = self::read('apps/codeigniter/Config/Logger.php');

        self::assertDoesNotMatchRegularExpression(
            '/\$threshold\s*=\s*0\s*;/',
            $config,
            'threshold 0 makes every level unloggable, so log_message() is a no-op'
        );
        self::assertStringContainsString(
            'MemoryLogHandler',
            $config,
            'the handler must be in-process; Errorlog/File write outside it and would measure that I/O'
        );

        $controller = self::read('apps/codeigniter/Controllers/Feature.php');

        self::assertStringContainsString(
            'MemoryLogHandler::resolve()',
            $controller,
            'the endpoint must read the live handler back rather than build the entries itself'
        );
    }

    /**
     * `/features/log` must be DIRECT on every entrant, Azera included.
     *
     * WHY THIS EXISTS AS ITS OWN TEST. Azera ships `#[Log]`, and the row originally
     * used it: `logSomething` carried the attribute, so the generated proxy applied
     * an `LogInterceptor` and the row paid a proxy dispatch plus an interceptor
     * frame that the other six entrants have no equivalent for. The chart then
     * compared two different kinds of machinery under one label — the same mistake
     * the AOP rows exist to isolate, made on a row that is meant to be about
     * logging.
     *
     * The rule: a row about a facility that EVERY entrant ships must be served the
     * same way by every entrant. Advice is measured by the `aop-*` features, which
     * is what those rows are for; `/features/log` calls the logger directly.
     * If someone re-adds `#[Log]` to `logSomething`, Azera's bar silently becomes
     * incomparable — which is invisible in a per-endpoint table, exactly like the
     * synthetic sleep that WorkloadIntegrityTest pins.
     */
    public function testTheLoggingRowIsDirectOnEveryEntrant(): void
    {
        require_once self::repoRoot() . '/scripts/report/BenchmarkConfig.php';

        $advice = \AzeraCompetition\Report\BenchmarkConfig::adviceRoutedEndpoints();

        foreach (array_keys(\AzeraCompetition\Report\BenchmarkConfig::adapterFeatures()) as $app) {
            self::assertArrayHasKey(
                $app,
                $advice,
                "{$app} must be classified, or this check is vacuous for it"
            );
            self::assertFalse(
                $advice[$app]['GET /features/log'] ?? true,
                "{$app}'s /features/log must not route through advice: the logging row is about the "
                    . 'logger, so every entrant must do the same work. Advice belongs to the aop-* rows'
            );
        }

        // ...and the non-vacuity half: the advice map must still SEE advice where it
        // legitimately exists, or the assertions above would pass on an empty map.
        self::assertTrue(
            $advice['azera']['GET /features/aop-tx'] ?? false,
            'the advice map must still report Azera\'s #[Transactional] row as proxy-routed'
        );
        self::assertTrue(
            $advice['azera']['GET /features/aop-cache'] ?? false,
            'the advice map must still report Azera\'s #[Cache] row as proxy-routed'
        );
    }

    /**
     * Azera must not declare `#[Log]` on the method `/features/log` calls.
     *
     * Source-level companion to the map check above: the map is a data structure and
     * can drift from the code, so this reads the method itself.
     */
    public function testAzeraDoesNotAdviseTheMethodBehindTheLoggingRow(): void
    {
        $service = self::code('apps/azera/Services/FeatureService.php');

        self::assertMatchesRegularExpression(
            '/public function logSomething\(/',
            $service,
            'the logging row must still call logSomething()'
        );

        // The attribute would sit on the line(s) immediately before the signature.
        self::assertDoesNotMatchRegularExpression(
            '/#\[Log\b[^\]]*\][\s\S]{0,200}?public function logSomething\(/',
            $service,
            'logSomething() must not carry #[Log]: the row would then pay for advice the other six '
                . 'entrants do not have, and the logging chart would stop being comparable'
        );

        // ...and it must log through the PSR-3 service, or the row measures nothing.
        self::assertMatchesRegularExpression(
            '/->logger\(\)/',
            $service,
            'logSomething() must log through AppContext::logger()'
        );
        self::assertStringContainsString(
            'duration_ms',
            $service,
            'the exit record must carry the measured duration, or it is not an exit record'
        );
    }

    /**
     * No entrant may hand-build its `log_entries`.
     *
     * This is the exact shape the two broken entrants used, and it passes a
     * substring-based parity check while measuring nothing:
     *
     *     'log_entries' => [['level' => 'info', 'message' => …, 'elapsed_ms' => …]]
     */
    public function testNoEntrantHandBuildsItsLogEntries(): void
    {
        $controllers = [
            'azera'       => 'apps/azera/Controllers/FeatureController.php',
            'laravel'     => 'apps/laravel/app/Http/Controllers/FeatureController.php',
            'symfony'     => 'apps/symfony/src/Controller/FeatureController.php',
            'spiral'      => 'apps/spiral/src/Controller/FeatureController.php',
            'codeigniter' => 'apps/codeigniter/Controllers/Feature.php',
            'cakephp'     => 'apps/cakephp/src/Controller/FeatureController.php',
            'phalcon'     => 'apps/phalcon/src/Controller/FeatureController.php',
        ];

        foreach ($controllers as $app => $file) {
            $source = self::code($file);

            self::assertDoesNotMatchRegularExpression(
                "/'log_entries'\s*=>\s*\[\s*\[/",
                $source,
                "{$app} hand-builds its log_entries; those entries must come from the logger that "
                    . 'received them. A literal here passes parity while measuring array construction'
            );
        }
    }

    /**
     * The shared description must not claim PSR-3 for an entrant that has none.
     *
     * Phalcon ships `Phalcon\Logger\Logger`, which does NOT implement
     * `Psr\Log\LoggerInterface` (verified: `class_implements()` reports only
     * `Phalcon\Logger\LoggerInterface` and `Phalcon\Contracts\Logger\Logger`). Saying
     * "PSR-3 everywhere" was therefore false for the one non-Azera entrant whose row
     * actually worked.
     */
    public function testTheDescriptionDoesNotClaimPsr3ForPhalcon(): void
    {
        $description = \AzeraCompetition\Report\BenchmarkConfig::featureDescriptionFor('logging');

        self::assertStringNotContainsString(
            'PSR-3 everywhere',
            $description,
            'Phalcon logs through its own API, not PSR-3, so "everywhere" is false'
        );
        self::assertStringContainsString(
            'not a PSR-3 implementation',
            $description,
            'the description must name the exception rather than imply universal PSR-3'
        );
    }

    /**
     * Every entrant's row must be readable in-process.
     *
     * A sink that writes to disk or stderr makes the row a disk benchmark: php-fpm
     * would pay the write, a resident worker would pay it once, and the two arms would
     * stop being comparable. This pins the sink each app names.
     */
    public function testEveryEntrantNamesAnInProcessSink(): void
    {
        $sinks = [
            'azera'       => ['apps/azera/Services/MemoryLogger.php', 'class MemoryLogger implements LoggerInterface'],
            'laravel'     => ['apps/laravel/config/logging.php', 'TestHandler::class'],
            'symfony'     => ['apps/symfony/src/Service/MemoryLogger.php', 'extends AbstractLogger'],
            'spiral'      => ['apps/spiral/src/Service/LogListener.php', 'ListenerRegistryInterface'],
            'codeigniter' => ['apps/codeigniter/Support/MemoryLogHandler.php', 'extends BaseHandler'],
            'cakephp'     => ['apps/cakephp/src/Support/FeatureLogEngine.php', 'extends ArrayLog'],
            'phalcon'     => ['apps/phalcon/src/Support/MemoryLoggerAdapter.php', 'extends AbstractAdapter'],
        ];

        foreach ($sinks as $app => [$file, $needle]) {
            self::assertStringContainsString(
                $needle,
                self::read($file),
                "{$app}'s log sink must be in-process and read back through the framework's own "
                    . 'extension point'
            );
        }
    }
}

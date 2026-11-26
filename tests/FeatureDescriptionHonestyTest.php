<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A feature demo's own description must describe the code behind it.
 *
 * WHY. The descriptions are what the report and the /features page show a reader,
 * so a wrong one is a wrong claim about the benchmark, not a typo. Three of them
 * were wrong at once and no test could see it:
 *
 *  - Laravel, Symfony and Spiral answered `/features/aop-tx` with "Inserted a row
 *    inside a transaction managed by … no manual begin/commit/rollback". None of
 *    the three opens a transaction at all — the upsert runs on an autocommit
 *    connection inside a retry pipeline. The word "transaction" was describing
 *    Azera's `#[Transactional]` row, which the author of that sentence had in mind
 *    because the endpoint is called `aop-tx`.
 *  - CodeIgniter's `aopDirect()` claimed "no interceptor pipeline" while running
 *    its upsert with NO transaction, unlike `aop()` — so the pair differed by the
 *    transaction and each row's text named neither.
 *  - CakePHP's `aopDirect()` named "the pipeline" as the one variable between the
 *    two rows, in an app that has no pipeline and serves the same code twice.
 *
 * These are source contracts rather than dispatches because the claim is a
 * property of the implementation, and because booting all seven frameworks in one
 * process is exactly what the harness isolates per app.
 */
final class FeatureDescriptionHonestyTest extends TestCase
{
    /** The `aop-tx` pair across every entrant that serves it. */
    private const AOP_PAIR = [
        'azera'       => 'apps/azera/Services/FeatureService.php',
        'laravel'     => 'apps/laravel/app/Service/AopService.php',
        'symfony'     => 'apps/symfony/src/Service/AopService.php',
        'spiral'      => 'apps/spiral/src/Service/AopService.php',
        'codeigniter' => 'apps/codeigniter/Controllers/Feature.php',
        'cakephp'     => 'apps/cakephp/src/Controller/FeatureController.php',
        'phalcon'     => 'apps/phalcon/src/Controller/FeatureController.php',
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
     * Nothing may claim a transaction it does not open.
     *
     * "autocommit connection" is the honest phrasing the three pipeline entrants
     * now use, and it is the phrase this looks for — a description mentioning
     * `begin`/`commit` without the word "autocommit" is claiming a transaction.
     */
    public function testNoDemoDescribesATransactionItDoesNotOpen(): void
    {
        foreach (['laravel', 'symfony', 'spiral'] as $app) {
            $source = self::read(self::AOP_PAIR[$app]);

            self::assertDoesNotMatchRegularExpression(
                '/begin|commit|rollback/',
                $source,
                "{$app}'s AOP service claims a transaction it never opens — the upsert is on an "
                    . 'autocommit connection'
            );

            // ...and the response must say so, so a reader of /features/aop-tx is not
            // told the row demonstrates transaction advice.
            $controller = self::read(match ($app) {
                'laravel' => 'apps/laravel/app/Http/Controllers/FeatureController.php',
                'symfony' => 'apps/symfony/src/Controller/FeatureController.php',
                'spiral'  => 'apps/spiral/src/Controller/FeatureController.php',
            });

            self::assertStringContainsString(
                'autocommit',
                $controller,
                "{$app}'s /features/aop-tx description must name the autocommit connection it "
                    . 'actually uses instead of claiming an interceptor-managed transaction'
            );
            self::assertStringNotContainsString(
                'no manual begin/commit/rollback',
                $controller,
                "{$app} must not claim the absence of something it never does"
            );
        }
    }

    /**
     * Azera is the transparent entrant, and ITS pair really is the transaction.
     */
    public function testAzeraStillDescribesTheProxyTransactionItUses(): void
    {
        $controller = self::read('apps/azera/Controllers/FeatureController.php');

        self::assertStringContainsString('#[Transactional] interceptor', $controller);
        self::assertStringContainsString('no manual begin/commit/rollback', $controller);

        $service = self::read('apps/azera/Services/FeatureService.php');
        self::assertStringContainsString('$db->begin(true)', $service);
        self::assertStringContainsString('$db->commit()', $service);
    }

    /**
     * An entrant with no advice layer must not claim a pipeline difference.
     *
     * CodeIgniter, CakePHP, Laravel, Symfony and Spiral all answer
     * `/features/aop-tx-direct` with a hand-written write, and none of them serves
     * `/features/aop-tx` at all. Their description therefore has to say which
     * facility they lack, not name a pipeline variable that would imply a pair
     * exists on this app.
     */
    public function testPipelineLessEntrantsDoNotClaimAPipelineVariable(): void
    {
        $controllers = [
            'codeigniter' => 'apps/codeigniter/Controllers/Feature.php',
            'cakephp'     => 'apps/cakephp/src/Controller/FeatureController.php',
            'laravel'     => 'apps/laravel/app/Http/Controllers/FeatureController.php',
            'symfony'     => 'apps/symfony/src/Controller/FeatureController.php',
            'spiral'      => 'apps/spiral/src/Controller/FeatureController.php',
        ];

        foreach ($controllers as $app => $controller) {
            $source = self::read($controller);

            self::assertStringContainsString(
                'no attribute-to-advice layer',
                $source,
                "{$app} ships no attribute-to-advice layer, so its aop-tx-direct description must "
                    . 'name the missing facility rather than a pipeline it never had'
            );
            self::assertStringContainsString(
                'does not serve `/features/aop-tx` at all',
                $source,
                "{$app} must not imply it has an `aop-tx` row to compare against"
            );
            self::assertStringNotContainsString(
                'no pipeline to remove',
                $source,
                "{$app} has no pipeline for a reader to expect removed — the pair does not exist "
                    . 'on this app'
            );
        }

        // Phalcon's control states the same absence in its own words.
        $phalcon = self::read(self::AOP_PAIR['phalcon']);
        self::assertStringContainsString('no interceptor involved', $phalcon);
    }

    /**
     * A pipeline entrant that does not serve `aop-tx` must not describe a pair.
     *
     * Laravel, Symfony and Spiral ship a pipeline but no attribute-to-advice layer,
     * so they answer only the CONTROL. Saying "both endpoints" would describe an
     * `aop-tx` row that does not exist for them — which is exactly the claim that
     * was wrong before they left the race.
     */
    public function testPipelineEntrantsDoNotDescribeAPairTheyDoNotServe(): void
    {
        foreach ([
            'laravel' => 'apps/laravel/app/Http/Controllers/FeatureController.php',
            'symfony' => 'apps/symfony/src/Controller/FeatureController.php',
            'spiral'  => 'apps/spiral/src/Controller/FeatureController.php',
        ] as $app => $controller) {
            $source = self::read($controller);

            self::assertStringContainsString(
                'autocommit connection',
                $source,
                "{$app}'s aop-tx-direct description must state how the write commits, or a reader "
                    . 'cannot tell it apart from a transactional row'
            );
            self::assertStringNotContainsString(
                'Neither endpoint opens a transaction here',
                $source,
                "{$app} does not serve `/features/aop-tx`, so there is no 'neither endpoint' to "
                    . 'describe'
            );
        }
    }

    /**
     * The `db-events` description must describe the events the demo actually fires.
     *
     * WHY THIS EXISTS. Three entrants — Laravel, Symfony and Spiral — described
     * that row as observing the "transaction lifecycle" while their handler wrote
     * on an autocommit connection and therefore fired no transaction event at all.
     * It was the same class of claim this file was written for, on a different
     * endpoint: `aop-tx` had already been corrected, and the identical sentence
     * survived one row over because nothing inspected it.
     *
     * The check is a source contract, like the others here, for the same reason:
     * the claim is a property of the implementation, and dispatching all seven
     * frameworks in one process is what the harness isolates per app.
     *
     * It reads the HANDLER BODY, not the whole file. Naming the transaction
     * facility in a docblock is accurate — those listeners really are registered
     * process-wide; they simply cannot fire on an autocommit write. What must not
     * claim to fire them is the payload a reader of the report actually sees.
     */
    public function testDbEventsDescriptionsMatchTheEventsTheirCodeFires(): void
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

        foreach ($controllers as $app => $controller) {
            $body = self::handlerBody($controller);

            // Every entrant must DESCRIBE the row, or a reader comparing charts has
            // nothing to go on. CodeIgniter and CakePHP were silent here until this
            // test existed.
            self::assertMatchesRegularExpression(
                '/[\'"]description[\'"]/',
                $body,
                "{$app}'s /features/db-events payload must carry a description alongside its events"
            );
            self::assertMatchesRegularExpression(
                '/[\'"]events[\'"]\s*=>/',
                $body,
                "{$app}'s /features/db-events payload must report the collected events"
            );
        }

        // ALL SEVEN run their demo on an autocommit connection, so all seven must
        // say so. A description that mentions a transaction here is claiming an
        // event its own handler cannot fire. Phalcon used to be the exception — it
        // copied `$db->begin()`/`commit()` from its own `aopAction()`, which
        // hand-writes a transaction because Phalcon has no advice layer. That
        // belonged to the `aop-*` rows, and it made this row report two events no
        // other entrant produced.
        foreach ($controllers as $app => $controller) {
            $body = self::handlerBody($controller);

            self::assertStringContainsString(
                'autocommit',
                $body,
                "{$app}'s db-events description must name the autocommit connection, or it is "
                    . 'claiming transaction events that never fire'
            );
            self::assertStringNotContainsString(
                'transaction lifecycle',
                $body,
                "{$app} must not claim the transaction lifecycle in its db-events response: its "
                    . 'handler never opens a transaction, so no such event reaches its log'
            );
        }
    }

    /**
     * No entrant may open a transaction in the db-events demo.
     *
     * The row exists to compare how each framework lets application code OBSERVE
     * queries. A transaction here is a second, unrelated facility on the measured
     * path: it costs a begin/commit the other entrants do not pay, and it makes the
     * reported payload differ in length for a reason that is not the feature.
     * Phalcon's transaction-event capability has its own row — `aop-tx-direct` —
     * where opening one is the point.
     */
    public function testNoEntrantOpensATransactionInTheDbEventsDemo(): void
    {
        foreach ([
            'azera'       => 'apps/azera/Controllers/FeatureController.php',
            'laravel'     => 'apps/laravel/app/Http/Controllers/FeatureController.php',
            'symfony'     => 'apps/symfony/src/Controller/FeatureController.php',
            'spiral'      => 'apps/spiral/src/Controller/FeatureController.php',
            'codeigniter' => 'apps/codeigniter/Controllers/Feature.php',
            'cakephp'     => 'apps/cakephp/src/Controller/FeatureController.php',
            'phalcon'     => 'apps/phalcon/src/Controller/FeatureController.php',
        ] as $app => $controller) {
            self::assertDoesNotMatchRegularExpression(
                '/\$db->(begin|beginTransaction)\(|->transaction\(|beginTransaction\s*\(/',
                self::handlerBody($controller),
                "{$app}'s db-events handler must not open a transaction: the row measures query "
                    . 'observation, and a transaction is a cost no other entrant pays here'
            );
        }
    }

    /**
     * Azera's db-events write must stay OFF the advice path.
     *
     * The row measures the database event pipeline. Routing the write through
     * `#[Transactional]` charged it a generated-proxy dispatch and an interceptor
     * frame the other six entrants do not pay, and made Azera the only one whose
     * demo opened a transaction — which is where the false descriptions above came
     * from. Same rule as `log`: advice belongs to the `aop-*` rows.
     */
    public function testAzeraDbEventsWriteIsNotAdvised(): void
    {
        $body = self::handlerBody('apps/azera/Controllers/FeatureController.php');

        // The docblock naming the method it does NOT call is fine; the call is not.
        self::assertMatchesRegularExpression(
            '/\$service->createItemAutocommit\(/',
            $body,
            'the db-events demo must write through createItemAutocommit()'
        );
        self::assertDoesNotMatchRegularExpression(
            '/\$service->createItemTransactional\(/',
            $body,
            'the db-events demo must not route its write through #[Transactional] — that is proxy '
                . 'cost the other six entrants do not pay on this row'
        );

        // ...and the method it calls must really be the unadvised one.
        $service = self::read('apps/azera/Services/FeatureService.php');
        self::assertMatchesRegularExpression(
            '/public function createItemAutocommit\(/',
            $service,
            'FeatureService must expose the plain write the db-events demo depends on'
        );
        self::assertDoesNotMatchRegularExpression(
            '/#\[Transactional\]\s*public function createItemAutocommit\(/',
            $service,
            'createItemAutocommit() must carry no advice'
        );

        require_once self::repoRoot() . '/scripts/report/BenchmarkConfig.php';
        self::assertFalse(
            \AzeraCompetition\Report\BenchmarkConfig::adviceRoutedEndpoints()['azera']['GET /features/db-events'],
            'the advice map must report db-events as plain code'
        );
    }

    /**
     * The `dbEvents`/`dbEventsAction` handler body from a controller.
     *
     * These handlers are well under 2 KB and contain no nested method, so a fixed
     * slice from the signature is exact enough and — unlike a brace-matching regex
     * — cannot be fooled by a brace inside a string literal.
     */
    private static function handlerBody(string $relative): string
    {
        $source = self::read($relative);
        $start  = stripos($source, 'function dbEvents');

        self::assertNotFalse($start, "{$relative} has no dbEvents handler");

        return substr($source, (int) $start, 2500);
    }
}

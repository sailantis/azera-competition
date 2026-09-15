<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Port allocation must be a function of the APP, not of its position in the
 * current --apps list.
 *
 * Ports used to be allocated as `$base + $i` while the deploy dir persists
 * across invocations, so a later single-app run (`--apps=cakephp`) restamped
 * cakephp onto the base port that a leftover azera vhost still claimed. nginx
 * binds the port to whichever server block it loads first, so the app being
 * benchmarked received ANOTHER app's responses — a wrong number, not an error.
 */
final class PortAllocationTest extends TestCase
{
    /** @var array<string,array<string,string>> */
    private array $ports = [];

    protected function setUp(): void
    {
        $this->ports = ['rr' => [], 'fpm' => []];
    }

    public function testKnownAppGetsItsCanonicalPort(): void
    {
        self::assertSame(8883, stampPort('azera', 'fpm', 8883, $this->ports));
        self::assertSame(8983, stampPort('azera', 'rr', 8983, $this->ports));
    }

    public function testPortDoesNotDependOnThePositionInTheAppList(): void
    {
        // The regression: cakephp alone must land on the SAME port it occupies
        // in a full six-app run, or its stale multi-app stamp collides.
        $full  = ['rr' => [], 'fpm' => []];
        $alone = ['rr' => [], 'fpm' => []];

        $expected = null;
        foreach (BENCH_APP_ORDER as $key) {
            $port = stampPort($key, 'fpm', 8883, $full);
            if ($key === 'cakephp') {
                $expected = $port;
            }
        }

        self::assertSame($expected, stampPort('cakephp', 'fpm', 8883, $alone));
    }

    public function testEveryKnownAppGetsADistinctPort(): void
    {
        $seen = [];
        foreach (BENCH_APP_ORDER as $key) {
            $seen[] = stampPort($key, 'fpm', 8883, $this->ports);
        }

        self::assertSame(count($seen), count(array_unique($seen)), 'two apps sharing a port serve the wrong responses');
    }

    public function testUnknownAppDoesNotCollideWithAKnownOne(): void
    {
        // A new app must not silently take a port a known app owns.
        $taken = [];
        foreach (BENCH_APP_ORDER as $key) {
            $taken[] = stampPort($key, 'fpm', 8883, $this->ports);
        }

        $port = stampPort('newframework', 'fpm', 8883, $this->ports);

        self::assertNotContains($port, $taken, 'an unknown app must be given a free port');
    }

    public function testUnknownAppsDoNotCollideWithEachOther(): void
    {
        $a = stampPort('alpha', 'fpm', 8883, $this->ports);
        $b = stampPort('beta', 'fpm', 8883, $this->ports);

        self::assertNotSame($a, $b);
    }

    public function testRrAndFpmPortSpacesAreIndependent(): void
    {
        // Each server kind keeps its own map; sharing one would offset every
        // app's port by the other kind's count.
        $rr  = stampPort('azera', 'rr', 8983, $this->ports);
        $fpm = stampPort('azera', 'fpm', 8883, $this->ports);

        self::assertSame(8983, $rr);
        self::assertSame(8883, $fpm);
    }

    public function testReStampingTheSameAppIsRefused(): void
    {
        // Handing one port to two apps is the failure this whole function
        // exists to prevent, so it must abort rather than return a port.
        $script = <<<'PHP'
        require '%s';
        $ports = ['rr' => [], 'fpm' => []];
        stampPort('azera', 'fpm', 8883, $ports);
        echo "STAMPED\n";
        stampPort('azera', 'fpm', 8883, $ports);
        echo "NOT REACHED\n";
        PHP;

        [$exit, $out] = $this->runPhp(sprintf($script, str_replace('\\', '/', dirname(__DIR__) . '/scripts/deploy-lib.php')));

        self::assertNotSame(0, $exit, 'a port collision must abort the run');
        self::assertStringNotContainsString('NOT REACHED', $out);
        self::assertStringContainsString('port collision', $out);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runPhp(string $code): array
    {
        $file = tempnam(sys_get_temp_dir(), 'bench-test-');
        file_put_contents($file, "<?php\ndeclare(strict_types=1);\n{$code}\n");
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $lines, $exit);
        unlink($file);

        return [$exit, implode("\n", $lines)];
    }
}

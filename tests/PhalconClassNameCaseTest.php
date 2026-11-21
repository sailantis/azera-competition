<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every Phalcon class the app names must resolve on a CASE-SENSITIVE filesystem.
 *
 * Phalcon 6 is the only entrant whose classes could carry this bug: the other
 * six come from packages whose names were already exercised on the VM, while
 * this app was written on Windows. Windows makes a case mismatch invisible —
 * PSR-4 asks for `src/Mvc/Model/Metadata/Memory.php`, the volume happily opens
 * `src/Mvc/Model/MetaData/Memory.php`, and the class loads.
 *
 * The first run of the real benchmark died on exactly this: `GET /items`
 * answered `500 Error: Class "Phalcon\Mvc\Model\Metadata\Memory" not found` on
 * the VM after every local check had passed, including the in-process adapter
 * parity run. The endpoint failed on the FIRST request that touched the
 * database, so the run aborted three endpoints in.
 *
 * `class_exists()` cannot be the check, for two reasons that both hide the bug:
 *   - PHP's class table is case-insensitive once a class is loaded, so probing
 *     `Metadata\Memory` AFTER `MetaData\Memory` succeeds regardless;
 *   - on Windows the loader simply finds the file.
 * So this test asks composer's PSR-4 map for the path each name SHOULD map to
 * and requires that path to exist with exactly that spelling.
 */
final class PhalconClassNameCaseTest extends TestCase
{
    public function testEveryReferencedPhalconClassResolvesWithExactCase(): void
    {
        $root  = dirname(__DIR__);
        $names = self::referencedPhalconNames($root);

        self::assertNotEmpty($names, 'the Phalcon app must reference Phalcon classes');

        $prefixes = require $root . '/vendor/autoload.php';
        $dirs = [];
        foreach ($prefixes->getPrefixesPsr4()['Phalcon\\'] ?? [] as $dir) {
            $dirs[] = rtrim(str_replace('\\', '/', $dir), '/');
        }
        self::assertNotEmpty($dirs, 'composer must map the Phalcon\\ namespace');

        $mismatches = [];
        foreach ($names as $class) {
            $relative = substr($class, strlen('Phalcon\\'));
            $parts    = explode('\\', $relative);
            $parts[count($parts) - 1] .= '.php';

            foreach ($dirs as $dir) {
                // Walked COMPONENT BY COMPONENT, and each component must appear
                // verbatim in its parent's listing. Anything less is defeated by
                // Windows: is_dir('.../Model/Metadata') succeeds against
                // `MetaData/`, and is_file() on the full path succeeds too, so a
                // whole-path check can never see the bug it exists to catch.
                $path  = $dir;
                $exact = true;
                foreach ($parts as $part) {
                    $entries = scandir($path) ?: [];
                    if (!in_array($part, $entries, true)) {
                        $exact = false;
                        break;
                    }
                    $path .= '/' . $part;
                }

                if ($exact) {
                    continue 2;
                }

                // The first component that misses: if a case variant is present,
                // that is the bug; if nothing similar is present, the name is a
                // class-name fragment and not this test's business.
                $path = $dir;
                foreach ($parts as $part) {
                    $entries = scandir($path) ?: [];
                    if (in_array($part, $entries, true)) {
                        $path .= '/' . $part;

                        continue;
                    }
                    foreach ($entries as $entry) {
                        if (strcasecmp($entry, $part) === 0) {
                            $mismatches[] = "{$class} -> on disk it is {$entry}, not {$part}";
                            break 2;
                        }
                    }
                    break;
                }
            }
        }

        self::assertSame(
            [],
            $mismatches,
            'Phalcon class names whose real file differs in case — they load on Windows and 500 on the Linux VM: '
                . "\n  " . implode("\n  ", $mismatches)
        );
    }

    /**
     * Every `Phalcon\...` literal in the app, the adapter and the entry point.
     *
     * @return list<string>
     */
    private static function referencedPhalconNames(string $root): array
    {
        $files = [];
        foreach (['/apps/phalcon/src', '/apps/phalcon/views'] as $dir) {
            $path = $root . $dir;
            if (!is_dir($path)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach (['/adapters/PhalconAdapter.php', '/public/index-phalcon.php'] as $extra) {
            if (is_file($root . $extra)) {
                $files[] = $root . $extra;
            }
        }

        $names = [];
        foreach ($files as $path) {
            preg_match_all('~Phalcon\\\\[A-Za-z0-9_\\\\]+~', (string) file_get_contents($path), $m);
            foreach ($m[0] as $name) {
                $names[$name] = true;
            }
        }

        ksort($names);

        return array_keys($names);
    }
}

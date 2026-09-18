<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * PowerShell scripts must stay ASCII-only.
 *
 * Windows PowerShell 5.1 (the powershell.exe that ships with Windows, and what
 * the harness is launched with) decodes a .ps1 as ANSI unless the file starts
 * with a UTF-8 BOM. A UTF-8 em-dash (E2 80 94) then becomes three garbage bytes
 * and the parser dies INSIDE A STRING — the error points at innocent text lines
 * later ("Unerwartetes Token", "Schliessende ) fehlt") and says nothing about
 * encoding. pwsh 7 assumes UTF-8 and never reproduces it.
 *
 * That combination cost real time twice: the committed run-remote.ps1 already
 * carried a latent parse error from an em-dash, and an edit that dropped the
 * BOM revived it. Keeping the files ASCII makes the failure impossible instead
 * of relying on a BOM surviving every future write.
 */
final class PowerShellEncodingTest extends TestCase
{
    /** @return list<string> */
    private function scripts(): array
    {
        $root = dirname(__DIR__);
        $out  = [];
        foreach (['scripts', 'deploy'] as $dir) {
            foreach (glob("{$root}/{$dir}/*.ps1") ?: [] as $f) {
                $out[] = $f;
            }
        }
        return $out;
    }

    public function testThereArePs1ScriptsToCheck(): void
    {
        // Guards against the scan passing vacuously if the glob ever misses.
        $this->assertNotEmpty($this->scripts(), 'no .ps1 scripts found — the scan would pass vacuously');
    }

    public function testScriptsContainNoNonAsciiBytes(): void
    {
        foreach ($this->scripts() as $path) {
            $bytes = (string) file_get_contents($path);
            $rel   = str_replace(dirname(__DIR__) . '/', '', str_replace('\\', '/', $path));

            $hits = [];
            if (preg_match_all('/[\x80-\xFF]+/', $bytes, $m, PREG_OFFSET_CAPTURE)) {
                foreach (array_slice($m[0], 0, 5) as [$seq, $off]) {
                    $line = substr_count(substr($bytes, 0, $off), "\n") + 1;
                    $hits[] = "line {$line} (0x" . bin2hex($seq) . ')';
                }
            }

            $this->assertSame(
                [],
                $hits,
                "{$rel} contains non-ASCII bytes: " . implode(', ', $hits)
                    . '. Windows PowerShell 5.1 reads a BOM-less .ps1 as ANSI and will fail to parse it.'
            );
        }
    }

    public function testScriptsHaveNoBom(): void
    {
        // ASCII content needs no BOM, and a stray BOM is what breaks PHP files
        // ("namespace declaration must be the very first statement") when the
        // same content is reused. Absence of both is the maintainable state.
        foreach ($this->scripts() as $path) {
            $head = substr((string) file_get_contents($path), 0, 3);
            $rel  = str_replace(dirname(__DIR__) . '/', '', str_replace('\\', '/', $path));
            $this->assertNotSame("\xEF\xBB\xBF", $head, "{$rel} starts with a UTF-8 BOM");
        }
    }

    public function testRemoteDriverUsesNoDoubleAmpersand(): void
    {
        // && is PowerShell 7 syntax; 5.1 rejects it as a STATEMENT separator.
        // Inside a double-quoted argument it is not a separator at all — it is
        // text handed to the remote shell, where bash needs it
        // (`ssh host "cd x && make"`). Comments are not code and legitimately
        // mention it. So only UNQUOTED, NON-COMMENT && is a bug.
        $src   = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-remote.ps1');
        $lines = explode("\n", $src);
        foreach ($lines as $i => $line) {
            // Strip a trailing comment FIRST (a comment legitimately discusses
            // &&), then quoted args (bash needs && there). Both regexes are
            // non-greedy single-line matches: an earlier version used '/^.*?#/'
            // which consumed from the start of the line and stripped real code.
            $code = preg_replace('/#.*$/', '', $line) ?? $line;
            $code = preg_replace('/"[^"]*"/', '', $code) ?? $code;
            if (str_contains($code, '&&')) {
                $this->fail(sprintf(
                    'run-remote.ps1:%d uses unquoted &&, which PowerShell 5.1 rejects',
                    $i + 1
                ));
            }
        }
        $this->assertTrue(true);
    }

    public function testRemoteDriverDeclaresNoDoubleColonInterpolation(): void
    {
        // "$name:" parses as a drive-qualified variable reference and fails with
        // "Invalid variable reference" — the fix is "${name}:". The automatic
        // variables ($env:, $Host:, $PSScriptRoot:) and scope qualifiers
        // ($script:, $global:, $using:) are legal, so they are excluded.
        $allowed = ['env', 'Host', 'PSScriptRoot', 'PSCommandPath', 'script', 'global', 'local', 'using', 'this'];
        $src     = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-remote.ps1');
        $lines   = explode("\n", $src);
        foreach ($lines as $i => $line) {
            if (!preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*):/', $line, $m)) {
                continue;
            }
            foreach ($m[1] as $name) {
                if (in_array($name, $allowed, true)) {
                    continue;
                }
                $this->fail(sprintf(
                    'run-remote.ps1:%d has $%s: — PowerShell 5.1 needs ${%s}:',
                    $i + 1,
                    $name,
                    $name
                ));
            }
        }
        $this->assertTrue(true);
    }

    public function testRemoteDriverDoesNotUseWindowsPathApisOnRemotePaths(): void
    {
        // Split-Path is a WINDOWS path API. On the remote POSIX path
        // '~/workspace/azera-competition' it returns '~\workspace' — a
        // backslash separator and an unexpanded '~'. ssh passes that to bash,
        // which does not tilde-expand it, so the sync extracts into a directory
        // named literally '~workspace', prints "sync done", and the repo the
        // benchmark reads never gets the files. It failed silently once.
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-remote.ps1');

        $this->assertStringNotContainsString(
            'Split-Path -Parent $RemoteDir',
            $src,
            'Split-Path is a Windows path API and mangles remote POSIX paths'
        );
        $this->assertStringContainsString(
            'Get-RemoteParent',
            $src,
            'run-remote.ps1 must derive the remote parent with POSIX string handling'
        );
        // The check that would have caught the failure immediately.
        $this->assertStringContainsString(
            'Assert-SyncLanded',
            $src,
            'run-remote.ps1 must verify the sync actually landed at $RemoteDir'
        );
    }

    public function testSyncGuardDoesNotSingleQuoteATildePath(): void
    {
        // bash does NOT tilde-expand inside quotes, so `test -f '~/x'` tests a
        // literal directory named '~'. The guard therefore reported MISSING on
        // every run, even when the sync had landed perfectly, and sent the
        // operator looking at $RemoteDir/Get-RemoteParent for a fault that was
        // in this one line. Pin the shape: the remote path must be built from
        // $HOME (expanded by bash) with no surrounding quotes.
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-remote.ps1');

        $this->assertStringNotContainsString(
            "test -f '\$RemoteDir/",
            $src,
            "the sync guard must not single-quote a '~' path — bash will not expand it"
        );
        $this->assertStringContainsString(
            '$dollar + ' . "'HOME/'",
            $src,
            'the guard must rewrite ~/ to $HOME (via [char]36) so bash expands it'
        );
        $this->assertStringContainsString(
            '[char]36',
            $src,
            'the literal $ must be built from [char]36, or PowerShell interpolates its own $HOME'
        );
    }

    public function testMultiLineRemoteCommandIsNormalisedToLf(): void
    {
        // The VM templates are LF-only, and this test file already asserts that
        // for deploy/*.sh. But run-remote.ps1 sends SHELL SCRIPT TEXT over ssh,
        // and on Windows the working tree is CRLF (core.autocrlf=true), so a
        // multi-line here-string arrives at the remote bash as 'fi\r'. bash
        // refuses it ("syntax error near unexpected token `fi'") and the whole
        // benchmark aborts in Install-Prereqs, before any measurement, with an
        // error that points at the REMOTE shell rather than at line endings.
        //
        // A .gitattributes cannot fix it — the CR is inserted by the checkout,
        // after the file is written — so the payload must be normalised where
        // the final bytes are known.
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-remote.ps1');

        $this->assertStringContainsString(
            '$remoteCmd = $remoteCmd -replace "`r`n", "`n"',
            $src,
            'the multi-line ssh payload must be normalised to LF before it is sent'
        );

        // Order matters: a normalisation AFTER the ssh call would be dead code.
        $norm = strpos($src, '$remoteCmd = $remoteCmd -replace');
        $send = strpos($src, 'ssh $SshTarget $remoteCmd');
        $this->assertNotFalse($norm);
        $this->assertNotFalse($send);
        $this->assertLessThan($send, $norm, 'the payload is normalised after it is sent — dead code');
    }

    /**
     * The definitive check: parse every .ps1 with the REAL Windows PowerShell
     * parser.
     *
     * The two heuristics above only catch the two failure modes already hit.
     * Parsing catches all of them at once (encoding, quoting, unbalanced
     * braces, bad interpolation) and reports the true line. Skipped when
     * powershell.exe is unavailable (non-Windows, or a PHP-only CI), because
     * the harness is a Windows tool and its target parser is 5.1.
     *
     * The parse runs from a temp SCRIPT FILE, not an inline -Command: a path
     * with backslashes inside a nested double-quoted -Command string is mangled
     * by the shell before PowerShell ever sees it (observed: "fehlende ) in
     * einem Methodenaufruf" pointing at the path itself).
     *
     * @dataProvider scriptPathProvider
     */
    public function testScriptsParseUnderWindowsPowerShell(string $path): void
    {
        $ps = 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe';
        if (!is_file($ps)) {
            $this->markTestSkipped('Windows PowerShell not available');
        }

        $dir   = dirname(__DIR__) . '/temp';
        $probe = $dir . '/ps-parse-' . bin2hex(random_bytes(4)) . '.ps1';
        // Single-quoted here-string in the probe: the path is inserted verbatim,
        // so backslashes cannot be interpreted as escapes.
        file_put_contents($probe, "\$e=\$null;\$t=\$null;\n"
            . '[void][System.Management.Automation.Language.Parser]::ParseFile('
            . "'" . str_replace("'", "''", $path) . "'"
            . ",[ref]\$t,[ref]\$e);\n"
            . "if (\$e.Count -eq 0) { 'OK' } else { \$e | ForEach-Object { "
            . "'line ' + \$_.Extent.StartLineNumber + ': ' + \$_.Message } }\n");

        exec(escapeshellarg($ps) . ' -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' . escapeshellarg($probe) . ' 2>&1', $out, $code);
        @unlink($probe);
        $joined = implode("\n", $out);

        $this->assertStringContainsString('OK', $joined, "parse errors in {$path}:\n{$joined}");
        $this->assertSame(0, $code, "powershell exited {$code}:\n{$joined}");
    }

    /** @return array<string,array{0:string}> */
    public static function scriptPathProvider(): array
    {
        $root = dirname(__DIR__);
        $out  = [];
        foreach (glob("{$root}/scripts/*.ps1") ?: [] as $f) {
            $out[basename($f)] = [$f];
        }
        return $out;
    }
}

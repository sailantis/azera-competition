<?php

declare(strict_types=1);

/**
 * One definition of how the `locale` and `platform` view vars are resolved,
 * shared by all seven entrants so the work is identical.
 *
 * WHY THIS FILE EXISTS. Every app renders the same layout footer —
 * `| Locale: en_US | Platform: desktop` — but only Azera computed those values:
 * `RequestContextMiddleware` parsed `Accept-Language` and regexed the
 * `User-Agent` per request, while the other six returned the two constants from
 * a `viewGlobals()` array (or, for Phalcon, rendered nothing at all). That is a
 * fairness fault in both directions:
 *
 *   - Azera's `/`, `/items` and `/items/{id}` rows carried header parsing that no
 *     other entrant performed, so the row compared different work;
 *   - every other app's footer asserted a detection that never happened, so the
 *     page claimed a locale it had not resolved.
 *
 * WHAT "NORMALIZED" MEANS HERE. Not "make everyone parse headers" and not "make
 * everyone hardcode" — both are defensible, but the first charges six entrants
 * for work they never wrote and the second deletes a real framework capability to
 * make a number smaller. The resolution is: the SAME parser, called with the
 * request's actual headers, in every app. A request that sends no locale hints
 * reaches the documented defaults; a request that does send them is honoured
 * identically everywhere. The work is identical because the code is identical.
 *
 * WHY A SHARED FILE AND NOT SEVEN COPIES. Seven copies of a header parser is the
 * situation that produced the drift: one app parsed, six hardcoded, and nothing
 * noticed. `response-guard.php` set the precedent for the harness — one rule,
 * required by every consumer, so the client and the server cannot disagree about
 * what a broken body is. This is the same shape for the same reason.
 *
 * NO SIDE EFFECTS, NO DEPENDENCIES. Safe to require from an adapter, a front
 * controller, a RoadRunner/Volucer worker, or a controller — exactly like
 * `response-guard.php`. Every app's autoloader already loads both.
 */

/**
 * The locale the accepted-languages header asks for, or the default.
 *
 * The header is read in the CLIENT's order of preference: `de-DE,en;q=0.9` asks for
 * German first, and returns `de_DE`. Quality weights are not interpreted — the
 * weights rank languages a client will accept, and this app picks the first
 * supported one, which is the same reading the original Azera middleware used for
 * an unweighted header. A header that is absent, empty or entirely unsupported
 * yields the default rather than a half-resolved tag.
 *
 * Iterating the HEADER rather than the supported map matters: iterating the map
 * returns the first supported language in MAP order, so `de-DE,en;q=0.9` would
 * answer `en_US` simply because `en` is listed first in the table. Nothing in the
 * benchmark sends this header, so neither reading changes a measured number — but
 * only one of them is what the function claims to do.
 *
 * @param string|null $acceptLanguage raw `Accept-Language` header, or null
 * @return string e.g. 'en_US', 'de_DE'
 */
function benchLocale(?string $acceptLanguage, string $default = 'en_US'): string
{
    $supported = [
        'en' => 'en_US',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'ja' => 'ja_JP',
        'es' => 'es_ES',
    ];

    $header = strtolower(trim((string) $acceptLanguage));
    if ($header === '') {
        return $default;
    }

    foreach (explode(',', $header) as $tag) {
        // 'de-DE;q=0.8' -> 'de' ; ' en-us ' -> 'en'
        $primary = strtok(trim($tag), '-;');
        if ($primary === false) {
            continue;
        }
        $primary = trim($primary);
        if (isset($supported[$primary])) {
            return $supported[$primary];
        }
    }

    return $default;
}

/**
 * The platform the user-agent string implies, or the default.
 *
 * The two patterns are the ones the Azera middleware used (Mobile/Android/iPhone,
 * then iPad/Tablet), kept verbatim so a header that DID reach this code resolves
 * exactly as it did before. Order matters: an iPad UA also contains "Mobile" in
 * Safari's mobile build, so the tablet test must not be reachable second.
 *
 * @param string|null $userAgent raw `User-Agent` header, or null
 * @return string 'mobile'|'tablet'|'desktop'
 */
function benchPlatform(?string $userAgent, string $default = 'desktop'): string
{
    $ua = (string) $userAgent;
    if ($ua === '') {
        return $default;
    }

    if (preg_match('/iPad|Tablet/i', $ua) === 1) {
        return 'tablet';
    }
    if (preg_match('/Mobile|Android|iPhone/i', $ua) === 1) {
        return 'mobile';
    }

    return $default;
}

/**
 * Both view vars at once, from the two raw header values.
 *
 * Every app calls this and merges the result into its own template data. Taking
 * two STRINGS rather than a request object is deliberate: the seven apps hold
 * requests in seven different types (Azera\Http\Request, Symfony's Request, a
 * Cake ServerRequest, a CI4 IncomingRequest, a PSR-7 message, a Spiral request),
 * and a shared helper that demanded one of them would be unusable by the other
 * six. Each app already knows how to read its own header API.
 *
 * @return array{locale: string, platform: string}
 */
function benchViewGlobals(?string $acceptLanguage, ?string $userAgent): array
{
    return [
        'locale'   => benchLocale($acceptLanguage),
        'platform' => benchPlatform($userAgent),
    ];
}

/**
 * Both view vars from the process's own SAPI state.
 *
 * For the FPM entry scripts and the resident workers, which have the real
 * superglobal and no framework request object in scope at the point the view
 * engine is configured. An in-process adapter passes its synthetic headers
 * instead (see the call sites), so both paths run the same parser on the same
 * kind of input.
 *
 * @return array{locale: string, platform: string}
 */
function benchViewGlobalsFromGlobals(): array
{
    return benchViewGlobals(
        isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
        isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
    );
}

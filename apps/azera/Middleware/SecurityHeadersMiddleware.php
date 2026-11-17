<?php
/**
 * Security headers middleware.
 *
 * Sets common security response headers that real apps apply on every
 * request.  This adds a realistic per-request overhead (header inspection
 * + modification) that all full-stack frameworks pay in their middleware
 * pipeline.
 */

namespace App\Azera\Middleware;

use Azera\AppContext;
use Azera\Core\MiddlewareInterface;
use Azera\Http\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private const HEADERS = [
        'X-Frame-Options'                   => 'SAMEORIGIN',
        'X-Content-Type-Options'            => 'nosniff',
        'X-XSS-Protection'                  => '1; mode=block',
        'Referrer-Policy'                   => 'strict-origin-when-cross-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    /**
     * Whether this Response build still exposes the name/value header setters.
     *
     * Azera 0.4.x and the release that restores name-keyed headers have
     * setHeader($name, $value). The dev line in between removed it and kept only
     * replaceHeader(), which takes a complete line. This app is measured against
     * all three, so the capability is resolved once per instance (0.04 us) rather
     * than per header, and the benchmark stays comparable across the three.
     */
    private ?bool $hasSetHeader = null;

    public function process(AppContext $ctx, callable $next): ?Response
    {
        $response = $next();

        if (!$response instanceof Response) {
            return $response;
        }

        if ($this->hasSetHeader ??= method_exists(Response::class, 'setHeader')) {
            foreach (self::HEADERS as $name => $value) {
                $response->setHeader($name, $value);
            }

            return $response;
        }

        foreach (self::HEADERS as $name => $value) {
            $response->replaceHeader($name . ': ' . $value);
        }

        return $response;
    }
}
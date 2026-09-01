<?php

declare(strict_types=1);

/**
 * Compiler pass — make the app cache persist across requests.
 *
 * The benchmark harness boots the Symfony kernel once and dispatches many
 * synthetic requests in a single long-lived PHP process (run-app.php /
 * SymfonyAdapter). Symfony's default `cache.app` pool is an ArrayAdapter
 * tagged `kernel.reset` (method `reset`), so the services_resetter clears it
 * after every request. In a normal PHP-FPM app each request is a fresh process
 * so this is invisible, but in the harness it means the cache never persists —
 * the /features/cache demo would miss on every call (~50ms).
 *
 * azera and Laravel use a process-lifetime array cache, so for apples-to-apples
 * parity we drop the `kernel.reset` tag from `cache.app` and let it persist
 * for the life of the process.
 */

namespace App\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PersistentAppCachePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('cache.app')) {
            return;
        }

        $container->getDefinition('cache.app')->clearTag('kernel.reset');
    }
}

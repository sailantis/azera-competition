<?php

// Debug script — boots the Laravel app and dumps route registration state.

require __DIR__ . '/vendor/autoload.php';

// Register the App\Laravel autoloader (same as the adapter does).
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Laravel\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = __DIR__ . '/apps/laravel/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$_ENV['APP_DEBUG'] = true;

$app = require __DIR__ . '/apps/laravel/bootstrap/app.php';

echo "App booted: " . get_class($app) . PHP_EOL;

$request = Illuminate\Http\Request::create('/items', 'GET');
$kernel  = $app->make(Illuminate\Contracts\Http\Kernel::class);

try {
    $resp = $kernel->handle($request);
    echo "Status: " . $resp->getStatusCode() . PHP_EOL;
    if ($resp->getStatusCode() !== 200) {
        // Surface the embedded exception comment Laravel puts at the top
        // of its error pages.
        $c = $resp->getContent();
        if (preg_match('/<!--(.*?)-->/s', $c, $m)) {
            echo "EXC: " . trim($m[1]) . PHP_EOL;
        }
    }
    echo substr($resp->getContent(), 0, 400) . PHP_EOL;
} catch (Throwable $e) {
    echo "THROWN: " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    foreach (array_slice($e->getTrace(), 0, 12) as $i => $frame) {
        $file = $frame['file'] ?? '?';
        $line = $frame['line'] ?? '?';
        $fn   = ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '');
        echo "  #$i $file:$line $fn" . PHP_EOL;
    }
}

$routes = $app->make('router')->getRoutes();
echo "Route count after boot: " . count($routes->getRoutes()) . PHP_EOL;
foreach (array_slice($routes->getRoutes(), 0, 10) as $route) {
    echo '  ' . implode('|', $route->methods()) . ' ' . $route->uri() . PHP_EOL;
}
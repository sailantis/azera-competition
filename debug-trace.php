<?php

// Debug v2 — capture the real reported exception via the exception handler's
// reportable hook (the debug error page only carries an HTML comment, which
// is not the actual error).

require __DIR__ . '/vendor/autoload.php';

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

$_ENV['APP_DEBUG'] = false; // keep the fancy renderer out of the picture

$app = require __DIR__ . '/apps/laravel/bootstrap/app.php';

// Capture every exception the handler reports, with its trace.
$handler = $app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
$handler->reportable(function (\Throwable $e) {
    echo "=== REPORTED: " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    foreach (array_slice($e->getTrace(), 0, 10) as $i => $fr) {
        echo '  #' . $i . ' ' . ($fr['file'] ?? '?') . ':' . ($fr['line'] ?? '?')
            . ' ' . ($fr['class'] ?? '') . ($fr['type'] ?? '') . ($fr['function'] ?? '')
            . PHP_EOL;
    }
    return false; // don't let it also hit the logger
});

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

foreach (['/', '/items', '/items/1', '/features/config'] as $uri) {
    $resp = $kernel->handle(Illuminate\Http\Request::create($uri, 'GET'));
    echo $uri . ' -> ' . $resp->getStatusCode() . PHP_EOL;
}

// Full '/' dispatch — dump the error page TITLE + exception comment, which
// carry the real exception class + message.
try {
    $resp = $kernel->handle(Illuminate\Http\Request::create('/', 'GET'));
    echo "home status: " . $resp->getStatusCode() . PHP_EOL;
    if ($resp->getStatusCode() !== 200) {
        $c = $resp->getContent();
        if (preg_match('/<title>(.*?)<\/title>/s', $c, $m)) {
            echo "TITLE: " . trim($m[1]) . PHP_EOL;
        }
        if (preg_match('/<!--\s*(.*?)\s*-->/s', $c, $m)) {
            echo "EXC: " . trim($m[1]) . PHP_EOL;
        }
        // Symfony HtmlErrorRenderer writes the class + message near the top.
        if (preg_match('/<span class="exception_title.*?>(.*?)<\/h2>/s', $c, $m)) {
            echo "SYMFONY: " . trim(strip_tags($m[1])) . PHP_EOL;
        }
        foreach (['class="exception-message"', 'exception_title'] as $needle) {
            $p = strpos($c, $needle);
            if ($p !== false) {
                $chunk = strip_tags(substr($c, $p, 400));
                echo "CHUNK[$needle]: " . preg_replace('/\s+/', ' ', $chunk) . PHP_EOL;
            }
        }
    }
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}

exit(0);

// Direct view render — bypasses the HTTP kernel + exception handler so any
// blade error surfaces raw.
try {
    $html = view('home', ['locale' => 'en_US', 'platform' => 'desktop'])->render();
    echo "VIEW OK, len=" . strlen($html) . PHP_EOL;
} catch (Throwable $e) {
    echo "VIEW THROWN: " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    foreach (array_slice($e->getTrace(), 0, 10) as $i => $fr) {
        echo '  #' . $i . ' ' . ($fr['file'] ?? '?') . ':' . ($fr['line'] ?? '?')
            . ' ' . ($fr['class'] ?? '') . ($fr['type'] ?? '') . ($fr['function'] ?? '')
            . PHP_EOL;
    }
}

// The 500 is produced by the exception handler's render() — re-run the
// same request with app.debug forced OFF so Symfony renders a plain page,
// and with a listener that dumps every reported exception trace.
$events = $app->make('events');
$events->listen(\Illuminate\Log\Events\MessageLogged::class, function ($e): void {
    if (($e->exception ?? null) instanceof Throwable) {
        echo "REPORTED: " . get_class($e->exception) . ': ' . $e->exception->getMessage() . PHP_EOL;
        foreach (array_slice($e->exception->getTrace(), 0, 14) as $i => $fr) {
            echo '  #' . $i . ' ' . ($fr['file'] ?? '?') . ':' . ($fr['line'] ?? '?')
                . ' ' . ($fr['class'] ?? '') . ($fr['type'] ?? '') . ($fr['function'] ?? '')
                . PHP_EOL;
        }
    }
});

try {
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $resp   = $kernel->handle(Illuminate\Http\Request::create('/items', 'GET'));
    echo "Second status: " . $resp->getStatusCode() . PHP_EOL;
} catch (Throwable $e) {
    echo "SECOND THROWN: " . get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
}
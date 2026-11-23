<?php
/**
 * 400/404 error page.
 *
 * A stock Cake 5 app ships `templates/Error/error400.php` and `error500.php`;
 * this app did not. That went unnoticed while ErrorHandlerMiddleware was
 * absent, but once the middleware is in place the renderer needs them: without
 * them WebExceptionRenderer throws MissingTemplateException internally, the
 * middleware catches its own failure and republishes the original exception as
 * a hard `500 An Internal Server Error Occurred` — losing the status the
 * exception carried (a route miss is a 404, not a 500).
 *
 * Deliberately self-contained: the renderer uses `_outputMessageSafe()`, which
 * switches the layout off, so this must not depend on `layout/default.php`.
 *
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not Found &mdash; Azera Competition</title>
</head>
<body>
<h1>Not Found</h1>
<p><?= h($message ?? 'The requested resource could not be found.') ?></p>
</body>
</html>

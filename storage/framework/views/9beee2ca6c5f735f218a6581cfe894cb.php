<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title data-inertia><?php echo e(config('app.name', 'ImmigrationKnowHow')); ?></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|outfit:400,500,600,700,800|jetbrains-mono:400,500" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <!-- Scripts -->
        <?php echo app('Tighten\Ziggy\BladeRouteGenerator')->generate(); ?>
        <?php if(app()->environment('local')): ?>
            <script type="module" src="http://127.0.0.1:5173/@vite/client"></script>
            <script type="module" src="http://127.0.0.1:5173/resources/js/app.js"></script>
        <?php else: ?>
            <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js']); ?>
        <?php endif; ?>
        <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <?php $__inertiaSsrResponse = app(\Inertia\Ssr\SsrState::class)->setPage($page)->dispatch();  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } ?>
    </body>
</html>
<?php /**PATH C:\Users\Lidiya Getale\Herd\immigrationknowhow\resources\views/app.blade.php ENDPATH**/ ?>
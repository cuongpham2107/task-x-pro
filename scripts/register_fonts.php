<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

$options = new Options;
$projectRoot = realpath(__DIR__.'/../');
// Allow dompdf to read files from project root (public/fonts)
$options->setChroot([$options->getRootDir(), $projectRoot]);
$options->setIsRemoteEnabled(true);
// Ensure font dir is writable
$dompdf = new Dompdf($options);
$canvas = $dompdf->getCanvas();
$fontMetrics = new FontMetrics($canvas, $options);

$base = realpath(__DIR__.'/../');
$fonts = [
    ['family' => 'Roboto', 'weight' => 'normal', 'style' => 'normal', 'file' => "$base/public/fonts/Roboto-Regular.ttf"],
    ['family' => 'Roboto', 'weight' => 'bold', 'style' => 'normal', 'file' => "$base/public/fonts/Roboto-Bold.ttf"],
    ['family' => 'Roboto', 'weight' => 'normal', 'style' => 'italic', 'file' => "$base/public/fonts/Roboto-Italic.ttf"],
    ['family' => 'Roboto', 'weight' => 'bold', 'style' => 'italic', 'file' => "$base/public/fonts/Roboto-BoldItalic.ttf"],
];

foreach ($fonts as $f) {
    $style = ['family' => $f['family'], 'weight' => $f['weight'], 'style' => $f['style']];
    $path = $f['file'];
    if (! file_exists($path)) {
        echo "Missing font: $path\n";

        continue;
    }
    $url = 'file://'.$path;
    echo "Registering {$f['family']} ({$f['weight']}, {$f['style']}) -> $path\n";
    $ok = $fontMetrics->registerFont($style, $url);
    echo $ok ? "Registered\n" : "Failed\n";
}

// Save user fonts cache
$fontMetrics->saveFontFamilies();

echo "Done\n";

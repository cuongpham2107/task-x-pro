<?php

namespace App\Console\Commands;

use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use Illuminate\Console\Command;

class RegisterDompdfFonts extends Command
{
    protected $signature = 'dompdf:register-fonts';

    protected $description = 'Register custom fonts for dompdf';

    public function handle(): void
    {
        $fontDir = storage_path('fonts/');

        $options = new Options;
        $options->setFontDir($fontDir);
        $options->setFontCache($fontDir);

        $dompdf = new Dompdf($options);
        $canvas = $dompdf->getCanvas();
        $fontMetrics = new FontMetrics($canvas, $options);

        $fonts = [
            'NotoSans' => [
                'normal' => $fontDir.'NotoSans-Regular.ttf',
                'bold' => $fontDir.'NotoSans-Bold.ttf',
                'italic' => $fontDir.'NotoSans-Italic.ttf',
                'bold_italic' => $fontDir.'NotoSans-BoldItalic.ttf',
            ],
        ];

        foreach ($fonts as $family => $variants) {
            foreach ($variants as $style => $path) {
                if (! file_exists($path)) {
                    $this->warn("File không tồn tại: {$path}");

                    continue;
                }

                $fontMetrics->registerFont(
                    ['family' => $family, 'style' => $style, 'weight' => 'normal'],
                    $path
                );

                $this->info("✓ Đã đăng ký: {$family} [{$style}]");
            }
        }

        $this->info('Hoàn tất đăng ký font!');
    }
}

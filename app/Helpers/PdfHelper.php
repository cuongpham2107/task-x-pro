<?php

namespace App\Helpers;

use Spatie\LaravelPdf\Facades\Pdf;

class PdfHelper
{
    /**
     * Generate PDF from HTML with system Chromium browser
     */
    public static function fromHtml($html, $format = 'a4')
    {
        return Pdf::html($html)
            ->format($format)
            ->withBrowsershot(function ($browsershot) {
                $browsershot->setChromePath('/usr/bin/chromium')
                    ->addChromiumArguments(['no-sandbox']);
            });
    }

    /**
     * Generate PDF from view with system Chromium browser
     */
    public static function fromView($view, $data = [], $format = 'a4')
    {
        $html = view($view, $data)->render();
        return self::fromHtml($html, $format);
    }
}

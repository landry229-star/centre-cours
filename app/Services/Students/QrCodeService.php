<?php

namespace App\Services\Students;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Génère un QR Code en SVG (aucune extension PHP requise, net à toutes les tailles).
 */
class QrCodeService
{
    public function svg(string $content, int $size = 200): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($content);

        // On retire la déclaration XML pour pouvoir insérer le SVG directement dans du HTML.
        return preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg) ?? $svg;
    }
}

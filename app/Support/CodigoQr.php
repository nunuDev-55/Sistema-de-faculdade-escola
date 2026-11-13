<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** Código QR em SVG (BaconQrCode), para pôr directamente nos documentos impressos. */
final class CodigoQr
{
    public static function svg(string $texto, int $tamanho = 132): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($tamanho, 0), new SvgImageBackEnd)))->writeString($texto);

        // Sem a declaração XML, para o SVG poder ir dentro da página
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}

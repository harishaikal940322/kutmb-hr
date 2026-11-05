<?php

namespace App\Services\Identity;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatorQrCode
{
    public function response(string $issuer, string $account, string $secret): Response
    {
        $provisioningUri = app(Google2FA::class)->getQRCodeUrl($issuer, $account, $secret);
        $renderer = new ImageRenderer(new RendererStyle(256, 4), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($provisioningUri);

        return new Response($svg, Response::HTTP_OK, [
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

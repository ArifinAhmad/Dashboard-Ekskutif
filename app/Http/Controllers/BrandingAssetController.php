<?php

namespace App\Http\Controllers;

use App\Services\BrandingService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandingAssetController extends Controller
{
    public function __invoke(BrandingService $branding): Response
    {
        $path = $branding->logoPath();
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

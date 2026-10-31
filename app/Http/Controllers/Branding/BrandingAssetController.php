<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a branding file only for the organisation identified by the hostname.
 * Paths are never taken from the request — only from the branding row.
 */
class BrandingAssetController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, string $slot): Response
    {
        $organization = $context->organization();
        abort_if($organization === null, 404);

        $branding = $organization->branding;
        abort_if($branding === null, 404);

        $path = $branding->pathFor($slot);
        abort_if(! is_string($path) || $path === '', 404);

        $prefix = 'organizations/'.$organization->uuid.'/';
        abort_unless(str_starts_with($path, $prefix), 404);
        abort_unless(Storage::disk('organizations')->exists($path), 404);

        return Storage::disk('organizations')->response($path);
    }
}

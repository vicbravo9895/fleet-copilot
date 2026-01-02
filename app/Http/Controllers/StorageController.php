<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageController extends Controller
{
    /**
     * Serve storage files with authentication.
     * This ensures files in /storage/ are only accessible to authenticated users.
     */
    public function serve(Request $request, string $path)
    {
        // Ensure user is authenticated
        if (!$request->user()) {
            abort(403, 'Unauthorized');
        }

        // Only allow access to dashcam-media and other safe paths
        $allowedPaths = ['dashcam-media'];
        $pathParts = explode('/', $path, 2);
        
        if (!in_array($pathParts[0], $allowedPaths)) {
            abort(403, 'Access denied to this storage path');
        }

        // Check if file exists
        if (!Storage::disk('public')->exists($path)) {
            abort(404, 'File not found');
        }

        // Get file path
        $filePath = Storage::disk('public')->path($path);
        
        // Get mime type
        $mimeType = Storage::disk('public')->mimeType($path) ?? 'application/octet-stream';

        // Return file response
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}


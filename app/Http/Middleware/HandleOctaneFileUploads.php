<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Octane/Swoole reuses worker processes between requests.
 * This means $_FILES superglobal and uploaded temp files
 * from a previous request can persist into the next one.
 *
 * This middleware ensures uploaded files are properly
 * accessible on the current request object.
 */
class HandleOctaneFileUploads
{
    public function handle(Request $request, Closure $next): Response
    {
        // For multipart requests, ensure Symfony properly
        // initialises the files from the Swoole request
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            // Re-initialize files collection from server data
            // This fixes Octane worker reuse corrupting $_FILES
            if (! empty($_FILES)) {
                $request->files->replace(
                    $this->normalizeFiles($_FILES)
                );
            }
        }

        return $next($request);
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                $normalized[$key] = $this->reArrayFiles($file);
            } else {
                $normalized[$key] = $file;
            }
        }

        return $normalized;
    }

    private function reArrayFiles(array $file): array
    {
        $result = [];
        $count  = count($file['name']);

        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
            for ($i = 0; $i < $count; $i++) {
                $result[$i][$key] = $file[$key][$i];
            }
        }

        return $result;
    }
}

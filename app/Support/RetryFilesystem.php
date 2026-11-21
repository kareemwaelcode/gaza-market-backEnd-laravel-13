<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

class RetryFilesystem extends Filesystem
{
    
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;
        $tempPath = tempnam(dirname($path), basename($path));

        chmod($tempPath, $mode ?? (0777 - umask()));
        file_put_contents($tempPath, $content);

        for ($i = 1; $i <= 10; $i++) {
            if (@rename($tempPath, $path)) {
                return;
            }
            usleep(50000 * $i);
            clearstatcache(true, $path);
        }

        @unlink($tempPath);
        
        file_put_contents($path, $content, LOCK_EX);
    }
}
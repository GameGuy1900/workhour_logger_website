<?php

/**
 * Appends a cache-busting version query string based on the file's last
 * modified time, so browsers/proxies never serve a stale cached copy of
 * style.css or theme.js after a deploy overwrites them.
 */
function asset_url(string $relativePath): string
{
    $fullPath = __DIR__ . '/../' . $relativePath;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    return $relativePath . '?v=' . $version;
}

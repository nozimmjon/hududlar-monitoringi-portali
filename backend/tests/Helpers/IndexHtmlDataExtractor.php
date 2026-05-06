<?php

namespace Tests\Helpers;

use RuntimeException;

class IndexHtmlDataExtractor
{
    public function extract(string $htmlPath): array
    {
        $html = file_get_contents($htmlPath);
        if ($html === false) {
            throw new RuntimeException("Could not read $htmlPath");
        }

        if (! preg_match('/const DATA\s*=\s*(\{.*?\});\s*\n/s', $html, $m)) {
            throw new RuntimeException("Could not locate `const DATA = {...};` in $htmlPath");
        }

        $decoded = json_decode($m[1], true);
        if ($decoded === null) {
            throw new RuntimeException('Failed to json_decode DATA blob: ' . json_last_error_msg());
        }

        return $decoded;
    }
}

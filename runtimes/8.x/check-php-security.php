<?php

// Upstream fixes: https://www.php.net/archive/2026.php#2026-10-21-5
// Check the installed interpreter, not PHP_VERSION (which selects an APK branch).
return static function (string $version): void {
    $minimums = [
        '8.2' => '8.2.35',
        '8.3' => '8.3.36',
        '8.4' => '8.4.27',
        '8.5' => '8.5.12',
    ];

    $branch = implode('.', array_slice(explode('.', $version), 0, 2));
    $minimum = $minimums[$branch] ?? null;

    if ($minimum === null || ! preg_match('/^\d+\.\d+\.\d+$/D', $version) || version_compare($version, $minimum, '<')) {
        throw new RuntimeException(
            'Refusing PHP '.$version.': CVE-2026-109618 / CVE-2026-109619 require PHP '
            .'8.2.35+, 8.3.36+, 8.4.27+, or 8.5.12+ on a supported branch. '
            .'Select an Alpine repository with fixed packages and rebuild. '
            .'Vendor backports must be verified separately.'
        );
    }
};

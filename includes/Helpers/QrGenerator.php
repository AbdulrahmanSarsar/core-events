<?php

/**
 * QR Code Generator Helper.
 *
 * Generates QR codes locally using the bundled phpqrcode library, removing
 * all dependencies on external services (such as the deprecated
 * quickchart.io / Google Charts APIs).
 *
 * Two output modes are supported:
 *
 *  1. data URI (base64 PNG) - the default, used in emails so the QR image
 *     is embedded directly inside the message and renders even when the
 *     recipient blocks remote images.
 *
 *  2. streamed HTTP endpoint - used by the public scan flow and any place
 *     that needs a real <img src="..."> URL. The endpoint is registered by
 *     the plugin and validated against an attendee record.
 *
 * @package CoreEventsPro\Helpers
 * @since   1.1.0
 */

namespace CoreEventsPro\Helpers;

if (! defined('ABSPATH')) {
    exit;
}

class QrGenerator
{
    /**
     * Default pixel size per QR module.
     *
     * The phpqrcode library multiplies this by the matrix size, so a value
     * of 6 produces a comfortable ~250px image for typical token strings.
     *
     * @var int
     */
    const DEFAULT_SIZE = 6;

    /**
     * Default quiet zone (margin) around the QR matrix, in modules.
     *
     * @var int
     */
    const DEFAULT_MARGIN = 2;

    /**
     * Track whether the underlying library was loaded once.
     *
     * @var bool
     */
    private static $library_loaded = false;

    /**
     * Lazily load the bundled phpqrcode library.
     *
     * The library is heavy (~150 KB of code paths). We avoid pulling it in
     * on every request and only require it the first time a QR is asked
     * for. The flag prevents redeclaration warnings on subsequent calls.
     *
     * @return void
     */
    private static function load_library()
    {
        if (self::$library_loaded || class_exists('\\QRcode')) {
            self::$library_loaded = true;
            return;
        }

        $path = CEP_PATH . 'includes/Vendor/phpqrcode/phpqrcode.php';

        if (file_exists($path)) {
            require_once $path;
        }

        self::$library_loaded = true;
    }

    /**
     * Build a base64 PNG data URI for the given text.
     *
     * Returned value is safe to drop directly into an <img src="..."> tag
     * (including inside HTML emails). On failure, returns an empty string -
     * callers should handle that gracefully.
     *
     * @param string $text   The string to encode (typically a scan URL).
     * @param int    $size   Pixels per QR module (default 6).
     * @param int    $margin Quiet zone in modules (default 2).
     * @return string The data URI, or an empty string on failure.
     */
    public static function get_data_uri($text, $size = self::DEFAULT_SIZE, $margin = self::DEFAULT_MARGIN)
    {
        $png = self::generate_png($text, $size, $margin);

        if (empty($png)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Stream a PNG response for the given text.
     *
     * Sends the appropriate headers and prints the image bytes. Calls
     * exit() so should only be used inside a dedicated request handler.
     *
     * @param string $text   The string to encode.
     * @param int    $size   Pixels per QR module.
     * @param int    $margin Quiet zone in modules.
     * @return void
     */
    public static function stream_png($text, $size = self::DEFAULT_SIZE, $margin = self::DEFAULT_MARGIN)
    {
        $png = self::generate_png($text, $size, $margin);

        if (empty($png)) {
            status_header(500);
            exit;
        }

        nocache_headers();
        header('Content-Type: image/png');
        header('Content-Length: ' . strlen($png));
        echo $png;
        exit;
    }

    /**
     * Build a public URL pointing to the plugin's QR image endpoint.
     *
     * The token is the attendee's `qr_token` column. The endpoint
     * (registered by the plugin) verifies that the token corresponds to
     * a real attendee before generating an image.
     *
     * @param string $token The attendee QR token.
     * @return string A fully-qualified URL.
     */
    public static function get_image_url($token)
    {
        return add_query_arg(
            ['cep_qr_image' => rawurlencode((string) $token)],
            site_url('/')
        );
    }

    /**
     * Build the public scan URL for a given token.
     *
     * Centralized here so the URL shape is defined in exactly one place.
     *
     * @param string $token The attendee QR token.
     * @return string The fully-qualified scan URL.
     */
    public static function get_scan_url($token)
    {
        return add_query_arg(
            ['cep_qr_scan' => rawurlencode((string) $token)],
            site_url('/')
        );
    }

    /**
     * Internal: generate raw PNG bytes for a string.
     *
     * Uses an output buffer to capture phpqrcode's direct PNG write. We
     * suppress the underlying library's `imagepng()` failures (e.g. when
     * GD is missing) and return an empty string in that case so the
     * caller can fall back to a text link.
     *
     * @param string $text   The payload to encode.
     * @param int    $size   Pixels per module.
     * @param int    $margin Quiet zone.
     * @return string Raw PNG binary (or empty on failure).
     */
    private static function generate_png($text, $size, $margin)
    {
        if ('' === (string) $text) {
            return '';
        }

        if (! function_exists('imagecreate')) {
            // GD extension missing on this server - cannot produce PNG.
            return '';
        }

        $size   = max(1, (int) $size);
        $margin = max(0, (int) $margin);

        // The bundled phpqrcode is a legacy library that emits E_DEPRECATED
        // notices under PHP 8 (optional-before-required parameters) at parse
        // time. Silence only the library's own noise so it never reaches the
        // error log or a misconfigured display_errors output, then restore
        // the previous reporting level no matter what happens.
        $previous = error_reporting();
        error_reporting($previous & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

        $png = '';

        try {
            self::load_library();

            if (class_exists('\\QRcode')) {
                ob_start();
                try {
                    // Defined by phpqrcode: 0 = L, 1 = M, 2 = Q, 3 = H.
                    // Level M is the standard for ticket scans (15% damage tolerance).
                    \QRcode::png((string) $text, false, 1, $size, $margin);
                    $png = (string) ob_get_clean();
                } catch (\Throwable $e) {
                    ob_end_clean();
                    $png = '';
                }
            }
        } finally {
            error_reporting($previous);
        }

        return $png;
    }
}

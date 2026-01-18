<?php
declare(strict_types=1);

if (!function_exists('gdy_suppress_errors')) {
    function gdy_suppress_errors(callable $fn) {
        set_error_handler(static function () { return true; });
        try { return $fn(); }
        finally { restore_error_handler(); }
    }
}

if (!function_exists('gdy_session_start')) {
    function gdy_session_start(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            gdy_suppress_errors(static function () { session_start(); });
        }
    }
}

if (!function_exists('gdy_mkdir')) {
    function gdy_mkdir(string $dir, int $mode = 0755, bool $recursive = true): bool {
        if ($dir === '' || is_dir($dir)) return true;
        if ($mode >= 0770) $mode = 0755;
        $old = umask(0);
        try {
            return (bool)gdy_suppress_errors(static function () use ($dir, $mode, $recursive) {
                return mkdir($dir, $mode, $recursive);
            });
        } finally {
            umask($old);
        }
    }
}

if (!function_exists('gdy_file_get_contents')) {
    function gdy_file_get_contents(string $path): string {
        if (!is_file($path) || !is_readable($path)) return '';
        $res = gdy_suppress_errors(static function () use ($path) { return file_get_contents($path); });
        return is_string($res) ? $res : '';
    }
}

if (!function_exists('gdy_file_put_contents')) {
    function gdy_file_put_contents(string $path, string $data, int $flags = 0): int|false {
        $dir = dirname($path);
        if ($dir !== '' && !is_dir($dir)) { gdy_mkdir($dir, 0755, true); }
        $res = gdy_suppress_errors(static function () use ($path, $data, $flags) { return file_put_contents($path, $data, $flags); });
        return is_int($res) ? $res : false;
    }
}

if (!function_exists('gdy_session_start')) {
    function gdy_session_start(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            gdy_suppress_errors(static function () { session_start(); });
        }
    }
}

if (!function_exists('gdy_mkdir')) {
    function gdy_mkdir(string $dir, int $mode = 0755, bool $recursive = true): bool {
        if ($dir === '' || is_dir($dir)) return true;
        if ($mode >= 0770) $mode = 0755;
        $old = umask(0);
        try {
            return (bool)gdy_suppress_errors(static function () use ($dir, $mode, $recursive) {
                return mkdir($dir, $mode, $recursive);
            });
        } finally {
            umask($old);
        }
    }
}

if (!function_exists('gdy_file_get_contents')) {
    function gdy_file_get_contents(string $path): string {
        if (!is_file($path) || !is_readable($path)) return '';
        $res = gdy_suppress_errors(static function () use ($path) {
            return file_get_contents($path);
        });
        return is_string($res) ? $res : '';
    }
}

if (!function_exists('gdy_file_put_contents')) {
    function gdy_file_put_contents(string $path, string $data, int $flags = 0): int|false {
        $dir = dirname($path);
        if ($dir !== '' && !is_dir($dir)) {
            gdy_mkdir($dir, 0755, true);
        }
        $res = gdy_suppress_errors(static function () use ($path, $data, $flags) {
            return file_put_contents($path, $data, $flags);
        });
        return is_int($res) ? $res : false;
    }
}

if (!function_exists('gdy_unlink')) {
    function gdy_unlink(string $path): bool {
        if (!is_file($path)) return false;
        return (bool)gdy_suppress_errors(static function () use ($path) {
            return unlink($path);
        });
    }
}

if (!function_exists('gdy_chmod')) {
    function gdy_chmod(string $path, int $mode): bool {
        if (!file_exists($path)) return false;
        return (bool)gdy_suppress_errors(static function () use ($path, $mode) {
            return chmod($path, $mode);
        });
    }
}

if (!function_exists('gdy_fopen')) {
    function gdy_fopen(string $path, string $mode) {
        return gdy_suppress_errors(static function () use ($path, $mode) {
            return fopen($path, $mode);
        });
    }
}

if (!function_exists('gdy_fclose')) {
    function gdy_fclose($handle): bool {
        if (!is_resource($handle) && !(PHP_VERSION_ID >= 80000 && $handle instanceof \GdImage)) {
            return false;
        }
        return (bool)gdy_suppress_errors(static function () use ($handle) {
            return fclose($handle);
        });
    }
}

if (!function_exists('gdy_strtotime')) {
    function gdy_strtotime(string $value) {
        return gdy_suppress_errors(static function () use ($value) { return strtotime($value); });
    }
}
if (!function_exists('gdy_parse_url')) {
    function gdy_parse_url(string $url, int $component = -1) {
        return gdy_suppress_errors(static function () use ($url, $component) { return parse_url($url, $component); });
    }
}
if (!function_exists('gdy_filemtime')) {
    function gdy_filemtime(string $path) {
        if (!file_exists($path)) return false;
        return gdy_suppress_errors(static function () use ($path) { return filemtime($path); });
    }
}
if (!function_exists('gdy_getimagesize')) {
    function gdy_getimagesize(string $path) {
        if (!is_file($path)) return false;
        return gdy_suppress_errors(static function () use ($path) { return getimagesize($path); });
    }
}
if (!function_exists('gdy_move_uploaded_file')) {
    function gdy_move_uploaded_file(string $from, string $to): bool {
        return (bool)gdy_suppress_errors(static function () use ($from, $to) { return move_uploaded_file($from, $to); });
    }
}
if (!function_exists('gdy_finfo_open')) {
    function gdy_finfo_open(int $flags = FILEINFO_MIME_TYPE) {
        return gdy_suppress_errors(static function () use ($flags) { return finfo_open($flags); });
    }
}
if (!function_exists('gdy_finfo_file')) {
    function gdy_finfo_file($finfo, string $filename) {
        return gdy_suppress_errors(static function () use ($finfo, $filename) { return finfo_file($finfo, $filename); });
    }
}
if (!function_exists('gdy_finfo_close')) {
    function gdy_finfo_close($finfo): bool {
        return (bool)gdy_suppress_errors(static function () use ($finfo) { return finfo_close($finfo); });
    }
}
if (!function_exists('gdy_readfile')) {
    function gdy_readfile(string $path) {
        if (!is_file($path)) return false;
        return gdy_suppress_errors(static function () use ($path) { return readfile($path); });
    }
}
if (!function_exists('gdy_simplexml_load_string')) {
    function gdy_simplexml_load_string(string $xml) {
        $prev = libxml_use_internal_errors(true);
        try {
            return gdy_suppress_errors(static function () use ($xml) { return simplexml_load_string($xml); });
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }
}
if (!function_exists('gdy_mail')) {
    function gdy_mail(string $to, string $subject, string $message, string $headers = '', string $parameters = ''): bool {
        return (bool)gdy_suppress_errors(static function () use ($to, $subject, $message, $headers, $parameters) {
            return $parameters !== '' ? mail($to, $subject, $message, $headers, $parameters) : mail($to, $subject, $message, $headers);
        });
    }
}
// GD image helpers
if (!function_exists('gdy_imagecreatefromjpeg')) {
    function gdy_imagecreatefromjpeg(string $path) { return gdy_suppress_errors(static function () use ($path) { return imagecreatefromjpeg($path); }); }
}
if (!function_exists('gdy_imagecreatefrompng')) {
    function gdy_imagecreatefrompng(string $path) { return gdy_suppress_errors(static function () use ($path) { return imagecreatefrompng($path); }); }
}
if (!function_exists('gdy_imagecreatefromwebp')) {
    function gdy_imagecreatefromwebp(string $path) { return gdy_suppress_errors(static function () use ($path) { return imagecreatefromwebp($path); }); }
}
if (!function_exists('gdy_imagejpeg')) {
    function gdy_imagejpeg($im, string $to, int $quality = 85): bool { return (bool)gdy_suppress_errors(static function () use ($im, $to, $quality) { return imagejpeg($im, $to, $quality); }); }
}
if (!function_exists('gdy_imagepng')) {
    function gdy_imagepng($im, string $to, int $quality = 6): bool { return (bool)gdy_suppress_errors(static function () use ($im, $to, $quality) { return imagepng($im, $to, $quality); }); }
}
if (!function_exists('gdy_imagewebp')) {
    function gdy_imagewebp($im, string $to, int $quality = 80): bool { return (bool)gdy_suppress_errors(static function () use ($im, $to, $quality) { return imagewebp($im, $to, $quality); }); }
}
if (!function_exists('gdy_imagedestroy')) {
    function gdy_imagedestroy($im): bool { return (bool)gdy_suppress_errors(static function () use ($im) { return imagedestroy($im); }); }
}
if (!function_exists('gdy_imagesavealpha')) {
    function gdy_imagesavealpha($im, bool $enable): bool { return (bool)gdy_suppress_errors(static function () use ($im, $enable) { return imagesavealpha($im, $enable); }); }
}
if (!function_exists('gdy_imagepalettetotruecolor')) {
    function gdy_imagepalettetotruecolor($im): bool { return (bool)gdy_suppress_errors(static function () use ($im) { return imagepalettetotruecolor($im); }); }
}
if (!function_exists('gdy_indexnow_submit_safe')) {
    function gdy_indexnow_submit_safe(...$args): bool {
        if (!function_exists('gdy_indexnow_submit')) return false;
        $res = gdy_suppress_errors(static function () use ($args) {
            return gdy_indexnow_submit(...$args);
        });
        return (bool)$res;
    }
}

if (!function_exists('gdy_filemtime')) {
    function gdy_filemtime(string $path) { return file_exists($path) ? gdy_suppress_errors(static function() use($path){ return filemtime($path); }) : false; }
}
if (!function_exists('gdy_filesize')) {
    function gdy_filesize(string $path) { return file_exists($path) ? gdy_suppress_errors(static function() use($path){ return filesize($path); }) : false; }
}
if (!function_exists('gdy_readfile')) {
    function gdy_readfile(string $path) { return is_file($path) ? gdy_suppress_errors(static function() use($path){ return readfile($path); }) : false; }
}
if (!function_exists('gdy_simplexml_load_string')) {
    function gdy_simplexml_load_string(string $xml) {
        $prev = libxml_use_internal_errors(true);
        try {
            $res = gdy_suppress_errors(static function() use($xml){ return simplexml_load_string($xml); });
            libxml_clear_errors();
            return $res;
        } finally {
            libxml_use_internal_errors($prev);
        }
    }
}
if (!function_exists('gdy_parse_ini_file')) {
    function gdy_parse_ini_file(string $filename, bool $processSections = false, int $scannerMode = INI_SCANNER_NORMAL) {
        return is_file($filename) ? gdy_suppress_errors(static function() use($filename,$processSections,$scannerMode){ return parse_ini_file($filename,$processSections,$scannerMode); }) : false;
    }
}
if (!function_exists('gdy_iconv')) {
    function gdy_iconv(string $from, string $to, string $str) { return gdy_suppress_errors(static function() use($from,$to,$str){ return iconv($from,$to,$str); }); }
}
if (!function_exists('gdy_copy')) {
    function gdy_copy(string $from, string $to): bool { return (bool)gdy_suppress_errors(static function() use($from,$to){ return copy($from,$to); }); }
}
if (!function_exists('gdy_file_lines')) {
    function gdy_file_lines(string $path, int $flags = 0): array {
        if (!is_file($path) || !is_readable($path)) return [];
        $res = gdy_suppress_errors(static function() use($path,$flags){ return file($path,$flags); });
        return is_array($res) ? $res : [];
    }
}
if (!function_exists('gdy_rmdir')) {
    function gdy_rmdir(string $path): bool { return is_dir($path) ? (bool)gdy_suppress_errors(static function() use($path){ return rmdir($path); }) : false; }
}
if (!function_exists('gdy_getimagesize')) {
    function gdy_getimagesize(string $filename) { return is_file($filename) ? gdy_suppress_errors(static function() use($filename){ return getimagesize($filename); }) : false; }
}
if (!function_exists('gdy_move_uploaded_file')) {
    function gdy_move_uploaded_file(string $from, string $to): bool { return (bool)gdy_suppress_errors(static function() use($from,$to){ return move_uploaded_file($from,$to); }); }
}
if (!function_exists('gdy_mail')) {
    function gdy_mail(string $to, string $subject, string $message, string $additionalHeaders = '', string $additionalParams = ''): bool {
        return (bool)gdy_suppress_errors(static function() use($to,$subject,$message,$additionalHeaders,$additionalParams){
            return $additionalParams !== '' ? mail($to,$subject,$message,$additionalHeaders,$additionalParams) : mail($to,$subject,$message,$additionalHeaders);
        });
    }
}
// Image helpers
if (!function_exists('gdy_imagecreatefromjpeg')) {
    function gdy_imagecreatefromjpeg(string $path) { return gdy_suppress_errors(static function() use($path){ return imagecreatefromjpeg($path); }); }
}
if (!function_exists('gdy_imagecreatefrompng')) {
    function gdy_imagecreatefrompng(string $path) { return gdy_suppress_errors(static function() use($path){ return imagecreatefrompng($path); }); }
}
if (!function_exists('gdy_imagecreatefromwebp')) {
    function gdy_imagecreatefromwebp(string $path) { return gdy_suppress_errors(static function() use($path){ return imagecreatefromwebp($path); }); }
}
if (!function_exists('gdy_imagecreatetruecolor')) {
    function gdy_imagecreatetruecolor(int $w, int $h) { return gdy_suppress_errors(static function() use($w,$h){ return imagecreatetruecolor($w,$h); }); }
}
if (!function_exists('gdy_imagecopyresampled')) {
    function gdy_imagecopyresampled($dst, $src, int $dst_x, int $dst_y, int $src_x, int $src_y, int $dst_w, int $dst_h, int $src_w, int $src_h): bool {
        return (bool)gdy_suppress_errors(static function() use($dst,$src,$dst_x,$dst_y,$src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h){
            return imagecopyresampled($dst,$src,$dst_x,$dst_y,$src_x,$src_y,$dst_w,$dst_h,$src_w,$src_h);
        });
    }
}
if (!function_exists('gdy_imagejpeg')) {
    function gdy_imagejpeg($img, ?string $filename = null, int $quality = 90): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$filename,$quality){
            return $filename !== null ? imagejpeg($img,$filename,$quality) : imagejpeg($img);
        });
    }
}
if (!function_exists('gdy_imagepng')) {
    function gdy_imagepng($img, ?string $filename = null, int $quality = 6): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$filename,$quality){
            return $filename !== null ? imagepng($img,$filename,$quality) : imagepng($img);
        });
    }
}
if (!function_exists('gdy_imagewebp')) {
    function gdy_imagewebp($img, ?string $filename = null, int $quality = 80): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$filename,$quality){
            return $filename !== null ? imagewebp($img,$filename,$quality) : imagewebp($img);
        });
    }
}
if (!function_exists('gdy_imagedestroy')) {
    function gdy_imagedestroy($img): bool {
        if (is_resource($img) || (is_object($img) && get_class($img) === 'GdImage')) {
            return (bool)gdy_suppress_errors(static function() use($img){ return imagedestroy($img); });
        }
        return false;
    }
}
if (!function_exists('gdy_imagepalettetotruecolor')) {
    function gdy_imagepalettetotruecolor($img): bool {
        return (bool)gdy_suppress_errors(static function() use($img){ return imagepalettetotruecolor($img); });
    }
}
if (!function_exists('gdy_imagealphablending')) {
    function gdy_imagealphablending($img, bool $blend): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$blend){ return imagealphablending($img,$blend); });
    }
}
if (!function_exists('gdy_imagesavealpha')) {
    function gdy_imagesavealpha($img, bool $save): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$save){ return imagesavealpha($img,$save); });
    }
}
if (!function_exists('gdy_imagecolorallocatealpha')) {
    function gdy_imagecolorallocatealpha($img, int $r, int $g, int $b, int $a) {
        return gdy_suppress_errors(static function() use($img,$r,$g,$b,$a){ return imagecolorallocatealpha($img,$r,$g,$b,$a); });
    }
}
if (!function_exists('gdy_imagefilledrectangle')) {
    function gdy_imagefilledrectangle($img, int $x1, int $y1, int $x2, int $y2, int $color): bool {
        return (bool)gdy_suppress_errors(static function() use($img,$x1,$y1,$x2,$y2,$color){ return imagefilledrectangle($img,$x1,$y1,$x2,$y2,$color); });
    }
}

if (!function_exists('gdy_indexnow_submit_safe')) {
    function gdy_indexnow_submit_safe(...$args): void {
        if (function_exists('gdy_indexnow_submit')) {
            gdy_suppress_errors(static function () use ($args) {
                gdy_indexnow_submit(...$args);
            });
        }
    }
}

if (!function_exists('gdy_finfo_open')) {
    function gdy_finfo_open(int $flags = FILEINFO_MIME_TYPE) {
        return gdy_suppress_errors(static function () use ($flags) { return finfo_open($flags); });
    }
}
if (!function_exists('gdy_finfo_file')) {
    function gdy_finfo_file($finfo, string $filename) {
        return gdy_suppress_errors(static function () use ($finfo, $filename) { return finfo_file($finfo, $filename); });
    }
}
if (!function_exists('gdy_finfo_close')) {
    function gdy_finfo_close($finfo): bool {
        return (bool)gdy_suppress_errors(static function () use ($finfo) { return finfo_close($finfo); });
    }
}

if (!function_exists('gdy_indexnow_submit_safe')) {
    function gdy_indexnow_submit_safe(...$args): bool {
        $res = gdy_suppress_errors(static function () use ($args) {
            if (!function_exists('gdy_indexnow_submit')) return false;
            return gdy_indexnow_submit(...$args);
        });
        return (bool)$res;
    }
}

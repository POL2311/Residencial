<?php
declare(strict_types=1);

if (!function_exists('operational_public_upload_base')) {
    function operational_public_upload_base(): string
    {
        // When the app is served from a subfolder (e.g. /residencial),
        // the public URL must include that base prefix.
        if (!function_exists('app_url')) {
            $sec = __DIR__ . '/app_security.php';
            if (is_file($sec)) {
                require_once $sec;
            }
        }
        if (function_exists('app_url')) {
            return app_url('assets/uploads/operativo');
        }
        return '/assets/uploads/operativo';
    }
}

if (!function_exists('operational_public_upload_disk_path')) {
    function operational_public_upload_disk_path(): string
    {
        return dirname(__DIR__) . '/assets/uploads/operativo';
    }
}

if (!function_exists('operational_image_extension')) {
    function operational_image_extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => '',
        };
    }
}

if (!function_exists('operational_read_orientation')) {
    function operational_read_orientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        try {
            $data = @exif_read_data($path);
            return (int)($data['Orientation'] ?? 1);
        } catch (Throwable $e) {
            return 1;
        }
    }
}

if (!function_exists('operational_fix_orientation')) {
    function operational_fix_orientation($image, int $orientation)
    {
        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}

if (!function_exists('operational_resize_image_resource')) {
    function operational_resize_image_resource($src, int $srcWidth, int $srcHeight, int $maxSide = 1200)
    {
        $srcWidth = max(1, $srcWidth);
        $srcHeight = max(1, $srcHeight);
        $maxSide = max(320, $maxSide);

        if (max($srcWidth, $srcHeight) <= $maxSide) {
            return [$src, $srcWidth, $srcHeight, false];
        }

        if ($srcWidth >= $srcHeight) {
            $newWidth = $maxSide;
            $newHeight = (int)round(($srcHeight / $srcWidth) * $newWidth);
        } else {
            $newHeight = $maxSide;
            $newWidth = (int)round(($srcWidth / $srcHeight) * $newHeight);
        }

        $newWidth = max(1, $newWidth);
        $newHeight = max(1, $newHeight);

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($dst, true);
        imagesavealpha($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);

        return [$dst, $newWidth, $newHeight, true];
    }
}

if (!function_exists('operational_is_function_enabled')) {
    function operational_is_function_enabled(string $fn): bool
    {
        if (!is_callable($fn)) {
            return false;
        }
        $disabled = (string)ini_get('disable_functions');
        if ($disabled === '') {
            return true;
        }
        $parts = array_map('trim', explode(',', $disabled));
        return !in_array($fn, $parts, true);
    }
}

if (!function_exists('operational_find_executable')) {
    function operational_find_executable(string $name, array $extraCandidates = []): ?string
    {
        $candidates = [];
        foreach ($extraCandidates as $p) {
            $candidates[] = (string)$p;
        }

        // Common locations (macOS Homebrew + typical linux)
        $candidates = array_merge($candidates, [
            '/opt/homebrew/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/usr/bin/' . $name,
            '/bin/' . $name,
        ]);

        $path = (string)getenv('PATH');
        if ($path !== '') {
            foreach (explode(':', $path) as $dir) {
                $dir = trim($dir);
                if ($dir === '') {
                    continue;
                }
                $candidates[] = rtrim($dir, '/') . '/' . $name;
            }
        }

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }
            if (@is_file($candidate) && @is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }
}

if (!function_exists('operational_safe_temp_base_in_dir')) {
    function operational_safe_temp_base_in_dir(string $dir, string $prefix = 'opimg-'): ?string
    {
        // We intentionally avoid tempnam() because it may fall back to system temp,
        // which can break under open_basedir / permissions in some XAMPP setups.
        $dir = rtrim($dir, '/');
        if ($dir === '' || !is_dir($dir) || !is_writable($dir)) {
            return null;
        }

        for ($i = 0; $i < 6; $i++) {
            $base = $dir . '/' . $prefix . bin2hex(random_bytes(8));
            // Ensure we don't collide with existing temp files
            if (!file_exists($base . '.png') && !file_exists($base . '.webp') && !file_exists($base . '.jpg')) {
                return $base;
            }
        }
        return null;
    }
}

if (!function_exists('operational_native_output_spec')) {
    function operational_native_output_spec(string $sourceMime): array
    {
        if ($sourceMime === 'image/png') {
            return ['ext' => 'png', 'mime' => 'image/png'];
        }
        return ['ext' => 'jpg', 'mime' => 'image/jpeg'];
    }
}

if (!function_exists('operational_process_image_upload')) {
    function operational_process_image_upload(array $file, array $options = []): array
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('La extensión GD es obligatoria para procesar imágenes.');
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se pudo recibir la imagen.');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new RuntimeException('Archivo temporal inválido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpPath);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Solo se aceptan imágenes JPG, JPEG, PNG o WebP.');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
            default => false,
        };
        if (!$source) {
            throw new RuntimeException('No se pudo abrir la imagen.');
        }

        $orientation = $mime === 'image/jpeg' ? operational_read_orientation($tmpPath) : 1;
        $corrected = operational_fix_orientation($source, $orientation);
        if ($corrected !== $source) {
            imagedestroy($source);
            $source = $corrected;
        }

        $srcWidth = (int)imagesx($source);
        $srcHeight = (int)imagesy($source);
        [$canvas, $newWidth, $newHeight, $isResized] = operational_resize_image_resource(
            $source,
            $srcWidth,
            $srcHeight,
            (int)($options['max_side'] ?? 1200)
        );

        if ($isResized) {
            imagedestroy($source);
        }

        $preserveText = !empty($options['preserve_text']);
        $targetBytes = max(120000, (int)($options['target_bytes'] ?? 250000));
        $qualityStart = $preserveText ? 80 : 78;
        $qualityMin = $preserveText ? 72 : 60;
        $qualityStep = 4;

        // Prefer WebP when supported by GD; otherwise use cwebp if available; otherwise fallback to jpg/png.
        $canGdWebp = operational_is_function_enabled('imagewebp') && function_exists('imagewebp');
        $cwebpBin = null;
        if (!$canGdWebp && operational_is_function_enabled('exec')) {
            $cwebpBin = operational_find_executable('cwebp');
        }
        $encoder = $canGdWebp ? 'gd_webp' : ($cwebpBin ? 'cwebp' : 'native');

        $relativeDir = date('Y/m');
        $baseDiskPath = operational_public_upload_disk_path();
        $basePublicUrl = operational_public_upload_base();
        $diskDir = $baseDiskPath . '/' . $relativeDir;
        if (!is_dir($diskDir) && !mkdir($diskDir, 0775, true) && !is_dir($diskDir)) {
            imagedestroy($canvas);
            throw new RuntimeException('No se pudo preparar el directorio de imágenes.');
        }

        $outExt = 'webp';
        $outMime = 'image/webp';
        if ($encoder === 'native') {
            $spec = operational_native_output_spec($mime);
            $outExt = (string)$spec['ext'];
            $outMime = (string)$spec['mime'];
        }

        $namePrefix = trim((string)($options['filename_prefix'] ?? 'operativo'));
        $rand = bin2hex(random_bytes(10));
        $filename = $namePrefix . '-' . $rand . '.' . $outExt;
        $diskPath = $diskDir . '/' . $filename;
        $publicUrl = rtrim($basePublicUrl, '/') . '/' . $relativeDir . '/' . $filename;

        $buffer = null;
        $size = 0;
        $chosenQuality = $qualityStart;

        if ($encoder === 'gd_webp') {
            for ($quality = $qualityStart; $quality >= $qualityMin; $quality -= $qualityStep) {
                ob_start();
                imagewebp($canvas, null, $quality);
                $candidate = ob_get_clean();
                $candidate = $candidate === false ? '' : $candidate;
                if ($candidate === '') {
                    continue;
                }

                $buffer = $candidate;
                $size = strlen($candidate);
                $chosenQuality = $quality;
                if ($size <= $targetBytes) {
                    break;
                }
            }

            if ($buffer === null || $buffer === '') {
                imagedestroy($canvas);
                throw new RuntimeException('No se pudo convertir la imagen a WebP.');
            }

            if (@file_put_contents($diskPath, $buffer) === false) {
                imagedestroy($canvas);
                throw new RuntimeException('No se pudo guardar la imagen procesada.');
            }
        } elseif ($encoder === 'cwebp') {
            // Export to a temp PNG, then compress to WebP using cwebp.
            $tmpBase = operational_safe_temp_base_in_dir($diskDir, 'opimg-');
            if ($tmpBase === null) {
                // If we can't create temp files in the upload dir, fall back to native output (jpg/png).
                $encoder = 'native';
            } else {
                $tmpIn = $tmpBase . '.png';
                $tmpOut = $tmpBase . '.webp';

                $okWrite = @imagepng($canvas, $tmpIn);
                if (!$okWrite || !is_file($tmpIn)) {
                    @unlink($tmpIn);
                    @unlink($tmpOut);
                    // Could not prepare temp input; fall back to native.
                    $encoder = 'native';
                }
            }

            if ($encoder === 'cwebp') {
                for ($quality = $qualityStart; $quality >= $qualityMin; $quality -= $qualityStep) {
                    @unlink($tmpOut);
                    $cmd = escapeshellarg((string)$cwebpBin)
                        . ' -quiet -q ' . (int)$quality
                        . ' ' . escapeshellarg($tmpIn)
                        . ' -o ' . escapeshellarg($tmpOut);
                    $outLines = [];
                    $exit = 0;
                    @exec($cmd, $outLines, $exit);
                    if ($exit !== 0 || !is_file($tmpOut) || filesize($tmpOut) <= 0) {
                        continue;
                    }

                    $candidate = @file_get_contents($tmpOut);
                    if ($candidate === false || $candidate === '') {
                        continue;
                    }

                    $buffer = $candidate;
                    $size = strlen($candidate);
                    $chosenQuality = $quality;
                    if ($size <= $targetBytes) {
                        break;
                    }
                }

                @unlink($tmpIn);
                @unlink($tmpOut);

                if ($buffer === null || $buffer === '') {
                    // cwebp failed, fall back to native instead of failing hard.
                    $encoder = 'native';
                } else {
                    if (@file_put_contents($diskPath, $buffer) === false) {
                        imagedestroy($canvas);
                        throw new RuntimeException('No se pudo guardar la imagen procesada.');
                    }
                }
            }

            if ($encoder === 'native') {
                // Recompute output filename/mime for native fallback (avoid saving JPEG as .webp).
                $spec = operational_native_output_spec($mime);
                $outExt = (string)$spec['ext'];
                $outMime = (string)$spec['mime'];
                $filename = $namePrefix . '-' . $rand . '.' . $outExt;
                $diskPath = $diskDir . '/' . $filename;
                $publicUrl = rtrim($basePublicUrl, '/') . '/' . $relativeDir . '/' . $filename;

                // Perform the native save here (since we're inside the cwebp branch).
                if ($outExt === 'png') {
                    if (!@imagepng($canvas, $diskPath)) {
                        imagedestroy($canvas);
                        throw new RuntimeException('No se pudo guardar la imagen (PNG).');
                    }
                    clearstatcache(true, $diskPath);
                    $size = (int)@filesize($diskPath);
                    $chosenQuality = 0;
                } else {
                    $q = (int)max(60, min(90, $qualityStart));
                    if (!@imagejpeg($canvas, $diskPath, $q)) {
                        imagedestroy($canvas);
                        throw new RuntimeException('No se pudo guardar la imagen (JPG).');
                    }
                    clearstatcache(true, $diskPath);
                    $size = (int)@filesize($diskPath);
                    $chosenQuality = $q;
                }
            }
        } else {
            // Native fallback: store as JPG/PNG without WebP conversion.
            if ($outExt === 'png') {
                if (!@imagepng($canvas, $diskPath)) {
                    imagedestroy($canvas);
                    throw new RuntimeException('No se pudo guardar la imagen (PNG).');
                }
                clearstatcache(true, $diskPath);
                $size = (int)@filesize($diskPath);
                $chosenQuality = 0;
            } else {
                $q = (int)max(60, min(90, $qualityStart));
                if (!@imagejpeg($canvas, $diskPath, $q)) {
                    imagedestroy($canvas);
                    throw new RuntimeException('No se pudo guardar la imagen (JPG).');
                }
                clearstatcache(true, $diskPath);
                $size = (int)@filesize($diskPath);
                $chosenQuality = $q;
            }
        }

        imagedestroy($canvas);

        return [
            'storage_disk' => 'local_public',
            'storage_path' => 'assets/uploads/operativo/' . $relativeDir . '/' . $filename,
            'public_url' => $publicUrl,
            'mime_type' => $outMime,
            'size_bytes' => $size,
            'width' => $newWidth,
            'height' => $newHeight,
            'quality' => $chosenQuality,
        ];
    }
}

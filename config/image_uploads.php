<?php
declare(strict_types=1);

if (!function_exists('operational_public_upload_base')) {
    function operational_public_upload_base(): string
    {
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
        $allowed = ['image/jpeg', 'image/png'];
        if (!in_array($mime, $allowed, true)) {
            throw new RuntimeException('Solo se aceptan imágenes JPG, JPEG o PNG.');
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
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

        $relativeDir = date('Y/m');
        $baseDiskPath = operational_public_upload_disk_path();
        $basePublicUrl = operational_public_upload_base();
        $diskDir = $baseDiskPath . '/' . $relativeDir;
        if (!is_dir($diskDir) && !mkdir($diskDir, 0775, true) && !is_dir($diskDir)) {
            imagedestroy($canvas);
            throw new RuntimeException('No se pudo preparar el directorio de imágenes.');
        }

        $filename = trim((string)($options['filename_prefix'] ?? 'operativo')) . '-' . bin2hex(random_bytes(10)) . '.webp';
        $diskPath = $diskDir . '/' . $filename;
        $publicUrl = rtrim($basePublicUrl, '/') . '/' . $relativeDir . '/' . $filename;

        $buffer = null;
        $size = 0;
        $chosenQuality = $qualityStart;

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

        imagedestroy($canvas);

        return [
            'storage_disk' => 'local_public',
            'storage_path' => 'assets/uploads/operativo/' . $relativeDir . '/' . $filename,
            'public_url' => $publicUrl,
            'mime_type' => 'image/webp',
            'size_bytes' => $size,
            'width' => $newWidth,
            'height' => $newHeight,
            'quality' => $chosenQuality,
        ];
    }
}

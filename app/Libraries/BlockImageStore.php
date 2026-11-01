<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Stores a re-encoded image under public/uploads/blocks.
 * The client filename is never used on disk.
 */
class BlockImageStore
{
    public const MAX_BYTES = 2097152;

    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    private int $maxBytes;

    private string $directory;

    public function __construct(?int $maxBytes = null, ?string $directory = null)
    {
        $this->maxBytes  = $maxBytes ?? self::MAX_BYTES;
        $this->directory = rtrim($directory ?? (FCPATH . 'uploads/blocks'), '/\\');
    }

    /**
     * @return array{ok: true, name: string, baseurl: string}|array{ok: false, error: string}
     */
    public function store(?UploadedFile $file): array
    {
        if ($file === null) {
            return $this->fail('Choose an image.');
        }

        $error = $file->getError();
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $this->fail('Choose an image.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return $this->fail('The image is too large.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            return $this->fail('The image could not be uploaded.');
        }

        if (! $this->clientNameAllowed($file->getClientName())) {
            return $this->fail('This file type is not allowed.');
        }

        $path = $file->getPathname();
        if (! $this->trustedPath($path)) {
            return $this->fail('The image could not be uploaded.');
        }

        $size = filesize($path);
        if ($size === false || $size < 1) {
            return $this->fail('The image could not be read.');
        }
        if ($size > $this->maxBytes) {
            return $this->fail('The image is too large.');
        }

        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            return $this->fail('The image could not be read.');
        }

        $mime = $this->mime($path);
        if (! isset(self::TYPES[$mime]) || ! $this->extensionMatches($file->getClientExtension(), $mime)) {
            return $this->fail('This file type is not allowed.');
        }

        $info = @getimagesizefromstring($bytes);
        if (! is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1 || ($info[0] * $info[1]) > 8000000) {
            return $this->fail('This file is not a valid image.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $this->fail('This file is not a valid image.');
        }

        $extension = self::TYPES[$mime];
        if ($extension === 'webp' && ! function_exists('imagewebp')) {
            imagedestroy($image);

            return $this->fail('This image type is not supported.');
        }

        if (in_array($extension, ['png', 'gif', 'webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $directory = $this->prepareDirectory();
        if ($directory === null) {
            imagedestroy($image);

            return $this->fail('The image could not be stored.');
        }

        $name   = $this->filename($directory, $extension);
        $target = $directory . DIRECTORY_SEPARATOR . $name;
        $saved  = $this->write($image, $extension, $target);
        imagedestroy($image);

        if (! $saved || ! $this->storedFileIsSafe($target, $mime)) {
            if (is_file($target)) {
                unlink($target);
            }

            return $this->fail('The image could not be stored.');
        }

        @chmod($target, 0644);

        return [
            'ok'      => true,
            'name'    => $name,
            'baseurl' => rtrim(base_url('uploads/blocks'), '/') . '/',
        ];
    }

    private function clientNameAllowed(string $name): bool
    {
        $base = basename(str_replace('\\', '/', $name));
        if ($base === '' || str_contains($base, "\0")) {
            return false;
        }

        if (preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|asp|aspx|svg|html?|js|exe|htaccess)(?:\.|$)/i', $base) === 1) {
            return false;
        }

        return in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    private function extensionMatches(string $extension, string $mime): bool
    {
        $extension = strtolower($extension);

        return match ($mime) {
            'image/jpeg' => in_array($extension, ['jpg', 'jpeg'], true),
            'image/png'  => $extension === 'png',
            'image/gif'  => $extension === 'gif',
            'image/webp' => $extension === 'webp',
            default      => false,
        };
    }

    private function trustedPath(string $path): bool
    {
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return false;
        }

        if (is_uploaded_file($path)) {
            return true;
        }

        if (ENVIRONMENT !== 'testing') {
            return false;
        }

        $real = realpath($path);
        if ($real === false) {
            return false;
        }

        foreach ([sys_get_temp_dir(), WRITEPATH] as $root) {
            $base = realpath($root);
            if ($base !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private function mime(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function prepareDirectory(): ?string
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0755, true) && ! is_dir($this->directory)) {
            return null;
        }

        $real = realpath($this->directory);

        return $real === false ? null : $real;
    }

    private function filename(string $directory, string $extension): string
    {
        do {
            $name = bin2hex(random_bytes(16)) . '.' . $extension;
        } while (is_file($directory . DIRECTORY_SEPARATOR . $name));

        return $name;
    }

    /**
     * @param \GdImage $image
     */
    private function write($image, string $extension, string $target): bool
    {
        return match ($extension) {
            'jpg'   => imagejpeg($image, $target, 90),
            'png'   => imagepng($image, $target, 6),
            'gif'   => imagegif($image, $target),
            'webp'  => imagewebp($image, $target, 90),
            default => false,
        };
    }

    private function storedFileIsSafe(string $target, string $mime): bool
    {
        if (! is_file($target)) {
            return false;
        }

        $contents = file_get_contents($target);
        if (! is_string($contents) || str_contains($contents, '<?php') || $this->mime($target) !== $mime) {
            return false;
        }

        $info = @getimagesize($target);

        return is_array($info) && ($info[0] ?? 0) > 0 && ($info[1] ?? 0) > 0;
    }

    /**
     * @return array{ok: false, error: string}
     */
    private function fail(string $message): array
    {
        return [
            'ok'    => false,
            'error' => $message,
        ];
    }
}

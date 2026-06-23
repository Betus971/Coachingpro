<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Redimensionne + recompresse les photos uploadées (gain de poids + chargement rapide).
 *
 * - Imagick d'abord s'il est dispo : gère TOUT, y compris le HEIC/HEIF iPhone (converti en JPEG).
 * - Sinon GD : JPEG / PNG / WebP uniquement (GD ne sait pas lire le HEIC).
 * - Si rien ne sait traiter le format (ex. HEIC sans Imagick), le fichier est laissé tel quel.
 *
 * L'optimisation est best-effort : toute erreur renvoie le chemin d'origine inchangé.
 */
final class ImageOptimizer
{
    public function __construct(
        private readonly int $maxDimension = 1600,
        private readonly int $jpegQuality = 82,
    ) {
    }

    /**
     * Optimise le fichier en place. Retourne le chemin final (extension possiblement
     * passée en .jpg), ou le chemin d'origine si non optimisable.
     */
    public function optimize(string $path): string
    {
        if (!is_file($path)) {
            return $path;
        }

        if (extension_loaded('imagick')) {
            try {
                return $this->withImagick($path);
            } catch (\Throwable) {
                // bascule sur GD
            }
        }

        if (function_exists('imagecreatetruecolor')) {
            try {
                return $this->withGd($path);
            } catch (\Throwable) {
                // laisse l'original
            }
        }

        return $path;
    }

    private function withImagick(string $path): string
    {
        $img = new \Imagick();
        $img->readImage($path . '[0]'); // 1re frame (HEIC live/burst)
        $img->autoOrientImage();
        $img->stripImage();

        if (max($img->getImageWidth(), $img->getImageHeight()) > $this->maxDimension) {
            $img->resizeImage($this->maxDimension, $this->maxDimension, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality($this->jpegQuality);

        $newPath = $this->jpgPath($path);
        $img->writeImage($newPath);
        $img->clear();
        $img->destroy();

        $this->dropOriginalIfReplaced($path, $newPath);

        return $newPath;
    }

    private function withGd(string $path): string
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return $path;
        }
        $type = $info[2];

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default        => false, // HEIC & co : GD ne sait pas
        };
        if (!$src) {
            return $path;
        }

        // Orientation EXIF (JPEG)
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($path)['Orientation'] ?? 0;
            $rotated = match ($orientation) {
                3       => imagerotate($src, 180, 0),
                6       => imagerotate($src, -90, 0),
                8       => imagerotate($src, 90, 0),
                default => $src,
            };
            if ($rotated !== false) {
                $src = $rotated;
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $this->maxDimension / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        $white = imagecolorallocate($dst, 255, 255, 255); // aplatit la transparence PNG
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $newPath = $this->jpgPath($path);
        imagejpeg($dst, $newPath, $this->jpegQuality);
        imagedestroy($src);
        imagedestroy($dst);

        $this->dropOriginalIfReplaced($path, $newPath);

        return $newPath;
    }

    private function jpgPath(string $path): string
    {
        return preg_replace('/\.[^.\/\\\\]+$/', '', $path) . '.jpg';
    }

    private function dropOriginalIfReplaced(string $original, string $new): void
    {
        if ($new !== $original && is_file($original)) {
            @unlink($original);
        }
    }
}

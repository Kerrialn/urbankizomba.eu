<?php

declare(strict_types=1);

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Where event posters live: public/uploads/posters, which is a named volume in
 * production shared with the web server so Caddy serves the files directly.
 *
 * Filenames are random. The original name is a poster's title as typed by an
 * organiser and belongs nowhere near a URL.
 */
final readonly class PosterStorage
{
    public const string PUBLIC_PATH = 'uploads/posters';

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/' . self::PUBLIC_PATH)]
        private string $directory,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Moves the upload into place and returns the stored filename.
     */
    public function store(UploadedFile $file): string
    {
        // The extension comes from the detected MIME type, never from the
        // client's filename: "poster.php.jpg" is a filename, not a file type.
        $extension = $file->guessExtension() ?? 'bin';
        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);

        $this->filesystem->mkdir($this->directory);
        $file->move($this->directory, $filename);

        return $filename;
    }

    public function remove(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        // basename() so a stored value can never reach outside the directory,
        // however it got into the database.
        $this->filesystem->remove($this->directory . '/' . basename($filename));
    }

    public function publicPath(string $filename): string
    {
        return self::PUBLIC_PATH . '/' . basename($filename);
    }
}

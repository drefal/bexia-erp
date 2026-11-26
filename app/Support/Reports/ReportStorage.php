<?php

declare(strict_types=1);

namespace App\Support\Reports;

use RuntimeException;

final class ReportStorage
{
    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim(
            $root ?? storage_path('app/private/reports'),
            DIRECTORY_SEPARATOR
        );
    }

    public function root(): string
    {
        $this->ensureDirectory($this->root);

        return $this->root;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_link($path)) {
            throw new RuntimeException(
                'Report storage cannot be a symbolic link.'
            );
        }

        if (!is_dir($path)) {

            if (
                !@mkdir($path, 0700, false)
                && !is_dir($path)
            ) {
                throw new RuntimeException(
                    'Unable to create report directory.'
                );
            }

            // Remove inherited SetGID when creating
            // the private reports directory.
            if (!chmod($path, 00700)) {
                throw new RuntimeException(
                    'Unable to secure report directory.'
                );
            }
        }

        clearstatcache(true, $path);

        if (
            is_link($path)
            || !is_dir($path)
            || (fileperms($path) & 07777) !== 0700
        ) {
            throw new RuntimeException(
                'Invalid report directory permissions.'
            );
        }
    }

    private function validateId(string $id): void
    {
        if (!preg_match(
            '/\A[0-9a-f]{8}-[0-9a-f]{4}-'
            .'[0-9a-f]{4}-[0-9a-f]{4}-'
            .'[0-9a-f]{12}\z/',
            $id
        )) {
            throw new RuntimeException(
                'Invalid report identifier.'
            );
        }
    }

    public function path(string $id): string
    {
        $this->validateId($id);

        return $this->root().'/'.$id.'.pdf';
    }

    public function lockPath(): string
    {
        return $this->root().'/generation.lock';
    }

    public function publish(
        string $id,
        string $source
    ): string {

        $destination = $this->path($id);

        if (
            !is_file($source)
            || is_link($source)
            || file_get_contents(
                $source,
                false,
                null,
                0,
                5
            ) !== '%PDF-'
        ) {
            throw new RuntimeException(
                'Invalid source PDF.'
            );
        }

        $temporary = tempnam(
            $this->root(),
            '.publishing-'
        );

        if ($temporary === false) {
            throw new RuntimeException(
                'Unable to create temporary report.'
            );
        }

        try {

            if (
                dirname($temporary) !== $this->root()
                || is_link($temporary)
                || !is_file($temporary)
            ) {
                throw new RuntimeException(
                    'Invalid temporary publication path.'
                );
            }

            if (!chmod($temporary, 0600)) {
                throw new RuntimeException(
                    'Unable to secure temporary report.'
                );
            }

            if (!copy($source, $temporary)) {
                throw new RuntimeException(
                    'Unable to copy report.'
                );
            }

            $sourceHash = hash_file('sha256', $source);

            $temporaryHash = hash_file(
                'sha256',
                $temporary
            );

            if (
                !is_string($sourceHash)
                || !is_string($temporaryHash)
                || !preg_match(
                    '/^[0-9a-f]{64}$/D',
                    $sourceHash
                )
                || !hash_equals(
                    $sourceHash,
                    $temporaryHash
                )
            ) {
                throw new RuntimeException(
                    'Report integrity verification failed.'
                );
            }

            // Publicacion atomica sin reemplazar un PDF
            // previamente publicado.

            if (!@link($temporary, $destination)) {
                throw new RuntimeException(
                    'Unable to publish report.'
                );
            }

            return $destination;

        } finally {

            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }


    /**
     * Caller must hold the report generation lock.
     */
    public function cleanupAbandonedPublications(
        int $minimumAge = 900
    ): array {

        if ($minimumAge < 900) {
            throw new RuntimeException(
                'Unsafe publication cleanup age.'
            );
        }

        if (!function_exists('posix_geteuid')) {
            throw new RuntimeException(
                'POSIX extension required.'
            );
        }

        $result = [
            'deleted' => 0,
            'bytes' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $uid = posix_geteuid();

        $cutoff = time() - $minimumAge;

        foreach (
            new \FilesystemIterator(
                $this->root(),
                \FilesystemIterator::SKIP_DOTS
            ) as $entry
        ) {

            if (!preg_match(
                '/\A\.publishing-[A-Za-z0-9]{6}\z/',
                $entry->getFilename()
            )) {
                continue;
            }

            try {

                if (
                    $entry->isLink()
                    || !$entry->isFile()
                    || $entry->getOwner() !== $uid
                    || ($entry->getPerms() & 0777)
                        !== 0600
                    || $entry->getMTime() > $cutoff
                ) {
                    $result['skipped']++;
                    continue;
                }

                $stat = lstat(
                    $entry->getPathname()
                );

                if (
                    $stat === false
                    || !in_array(
                        $stat['nlink'],
                        [1, 2],
                        true
                    )
                ) {
                    $result['skipped']++;
                    continue;
                }

                // If an atomic publication was already
                // linked, removing only this temporary
                // name preserves the final document.

                if (!unlink($entry->getPathname())) {
                    throw new RuntimeException(
                        'Publication cleanup failed.'
                    );
                }

                $result['deleted']++;

                // Count released space only when
                // no other hard link remains.

                if ($stat['nlink'] === 1) {
                    $result['bytes'] += $stat['size'];
                }

            } catch (\Throwable $exception) {

                $result['errors']++;

                error_log(
                    'Publication cleanup error: '
                    .get_class($exception)
                );
            }
        }

        return $result;
    }

    public function exists(string $id): bool
    {
        $path = $this->path($id);

        return is_file($path) && !is_link($path);
    }

    public function delete(string $id): bool
    {
        $path = $this->path($id);

        if (is_link($path)) {
            throw new RuntimeException(
                'Invalid report file.'
            );
        }

        return !is_file($path) || unlink($path);
    }
}

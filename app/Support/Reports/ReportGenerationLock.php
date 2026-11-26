<?php

declare(strict_types=1);

namespace App\Support\Reports;

use RuntimeException;

final class ReportGenerationLock
{
    private $handle = null;

    public function __construct(
        private readonly ReportStorage $storage
    ) {
    }

    public function acquire(): bool
    {
        if (is_resource($this->handle)) {
            throw new RuntimeException(
                'Lock already acquired by this instance.'
            );
        }

        $path = $this->storage->lockPath();

        if (is_link($path)) {
            throw new RuntimeException(
                'Invalid generation lock.'
            );
        }

        $previousUmask = umask(0077);

        try {
            $handle = fopen($path, 'c+');
        } finally {
            umask($previousUmask);
        }

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to open generation lock.'
            );
        }

        try {

            if (!chmod($path, 0600)) {
                throw new RuntimeException(
                    'Unable to secure generation lock.'
                );
            }

            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                fclose($handle);

                return false;
            }

            $this->handle = $handle;

            return true;

        } catch (\Throwable $e) {

            fclose($handle);

            throw $e;
        }
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        $this->handle = null;
    }

    public function __destruct()
    {
        $this->release();
    }
}

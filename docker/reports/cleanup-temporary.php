<?php

declare(strict_types=1);

function cleanupAttendanceTemporary(
    string $root,
    int $minimumAge = 900
): array {

    if ($minimumAge < 900) {
        throw new InvalidArgumentException(
            'Unsafe temporary cleanup age.'
        );
    }

    if (!function_exists('posix_geteuid')) {
        throw new RuntimeException(
            'Temporary cleanup requires POSIX.'
        );
    }

    if (
        is_link($root)
        || !is_dir($root)
        || realpath($root) !== $root
    ) {
        throw new RuntimeException(
            'Invalid temporary cleanup directory.'
        );
    }

    $uid = posix_geteuid();

    $cutoff = time() - $minimumAge;

    $result = [
        'directories' => 0,
        'files' => 0,
        'bytes' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    foreach (
        new FilesystemIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        ) as $entry
    ) {

        $name = $entry->getFilename();

        $directory = preg_match(
            '/\Abexia_attendance_pdf_[0-9a-f]{32}\z/',
            $name
        ) === 1;

        $final = preg_match(
            '/\Abexia_attendance_final_[A-Za-z0-9]{6}\z/',
            $name
        ) === 1;

        if (!$directory && !$final) {
            continue;
        }

        try {

            if (
                $entry->isLink()
                || $entry->getOwner() !== $uid
                || $entry->getMTime() > $cutoff
            ) {
                $result['skipped']++;
                continue;
            }

            if ($final) {

                if (
                    !$entry->isFile()
                    || ($entry->getPerms() & 0777)
                        !== 0600
                    || $entry->getCTime() > $cutoff
                    || lstat(
                        $entry->getPathname()
                    )['nlink'] !== 1
                ) {
                    $result['skipped']++;
                    continue;
                }

                $size = $entry->getSize();

                if (!unlink($entry->getPathname())) {
                    throw new RuntimeException(
                        'Temporary file deletion failed.'
                    );
                }

                $result['files']++;
                $result['bytes'] += $size;

                continue;
            }

            if (
                !$entry->isDir()
                || ($entry->getPerms() & 0777) !== 0700
            ) {
                $result['skipped']++;
                continue;
            }

            $files = [];
            $safe = true;

            foreach (
                new FilesystemIterator(
                    $entry->getPathname(),
                    FilesystemIterator::SKIP_DOTS
                ) as $part
            ) {

                if (
                    $part->isLink()
                    || !$part->isFile()
                    || $part->getOwner() !== $uid
                    || $part->getMTime() > $cutoff
                    || ($part->getPerms() & 0777)
                        !== 0600
                    || lstat(
                        $part->getPathname()
                    )['nlink'] !== 1
                    || preg_match(
                        '/\A(?:part_[0-9]{5}|merged)\.pdf\z/',
                        $part->getFilename()
                    ) !== 1
                ) {
                    $safe = false;
                    break;
                }

                $files[] = [
                    $part->getPathname(),
                    $part->getSize(),
                ];
            }

            if (!$safe) {
                $result['skipped']++;
                continue;
            }

            foreach ($files as [$path, $size]) {

                if (!unlink($path)) {
                    throw new RuntimeException(
                        'PDF part deletion failed.'
                    );
                }

                $result['files']++;
                $result['bytes'] += $size;
            }

            if (!rmdir($entry->getPathname())) {
                throw new RuntimeException(
                    'Temporary directory deletion failed.'
                );
            }

            $result['directories']++;

        } catch (Throwable $exception) {

            $result['errors']++;

            error_log(
                'Report temporary cleanup failed: '
                .get_class($exception)
            );
        }
    }

    return $result;
}

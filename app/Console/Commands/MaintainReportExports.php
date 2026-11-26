<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Reports\ReportMaintenanceService;
use Illuminate\Console\Command;
use Throwable;

final class MaintainReportExports extends Command
{
    protected $signature =
        'bexia:reports-maintain';

    protected $description =
        'Recover interrupted reports and expire private PDF documents';

    public function handle(
        ReportMaintenanceService $maintenance
    ): int {

        try {

            $result = $maintenance->run();

            $this->line(
                json_encode(
                    $result,
                    JSON_THROW_ON_ERROR
                )
            );

            if ($result['errors'] > 0) {
                return self::FAILURE;
            }

            return self::SUCCESS;

        } catch (Throwable $exception) {

            $this->error(
                'Report maintenance unavailable.'
            );

            return self::FAILURE;
        }
    }
}

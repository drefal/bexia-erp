<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicPageAnalytics
{
    public const MERCADO_PAGO_CALCULATOR =
        'mercado_pago_calculator';

    protected const VISITOR_COOKIE =
        'bexia_public_visitor';

    protected const COOKIE_MINUTES =
        525600;

    public function recordView(
        int $companyId,
        string $pageKey,
        Request $request
    ): void {
        if (! $this->shouldTrack($request)) {
            return;
        }

        $visitorHash =
            $this->visitorHash($request);

        $statDate =
            now()->toDateString();

        DB::transaction(
            function () use (
                $companyId,
                $pageKey,
                $visitorHash,
                $statDate
            ): void {
                $this->ensureDailyRow(
                    $companyId,
                    $pageKey,
                    $statDate
                );

                DB::table(
                    'public_page_visit_stats'
                )
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->where(
                        'page_key',
                        $pageKey
                    )
                    ->where(
                        'stat_date',
                        $statDate
                    )
                    ->increment(
                        'total_views',
                        1,
                        [
                            'updated_at' => now(),
                        ]
                    );

                $inserted =
                    DB::table(
                        'public_page_visitors'
                    )
                        ->insertOrIgnore([
                            'company_id' =>
                                $companyId,

                            'page_key' =>
                                $pageKey,

                            'stat_date' =>
                                $statDate,

                            'visitor_hash' =>
                                $visitorHash,

                            'created_at' =>
                                now(),
                        ]);

                if ($inserted === 1) {
                    DB::table(
                        'public_page_visit_stats'
                    )
                        ->where(
                            'company_id',
                            $companyId
                        )
                        ->where(
                            'page_key',
                            $pageKey
                        )
                        ->where(
                            'stat_date',
                            $statDate
                        )
                        ->increment(
                            'unique_visitors',
                            1,
                            [
                                'updated_at' =>
                                    now(),
                            ]
                        );
                }
            }
        );
    }

    public function recordPdfDownload(
        int $companyId,
        string $pageKey,
        Request $request
    ): void {
        if (! $this->shouldTrack($request)) {
            return;
        }

        $statDate =
            now()->toDateString();

        DB::transaction(
            function () use (
                $companyId,
                $pageKey,
                $statDate
            ): void {
                $this->ensureDailyRow(
                    $companyId,
                    $pageKey,
                    $statDate
                );

                DB::table(
                    'public_page_visit_stats'
                )
                    ->where(
                        'company_id',
                        $companyId
                    )
                    ->where(
                        'page_key',
                        $pageKey
                    )
                    ->where(
                        'stat_date',
                        $statDate
                    )
                    ->increment(
                        'pdf_downloads',
                        1,
                        [
                            'updated_at' => now(),
                        ]
                    );
            }
        );
    }

    public function summary(
        int $companyId,
        string $pageKey
    ): array {
        $today =
            now()->toDateString();

        $from30 =
            now()
                ->subDays(29)
                ->toDateString();

        $todayRow =
            DB::table(
                'public_page_visit_stats'
            )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'page_key',
                    $pageKey
                )
                ->where(
                    'stat_date',
                    $today
                )
                ->first();

        $last30 =
            DB::table(
                'public_page_visit_stats'
            )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'page_key',
                    $pageKey
                )
                ->whereDate(
                    'stat_date',
                    '>=',
                    $from30
                )
                ->selectRaw(
                    'COALESCE(SUM(total_views), 0) ' .
                    'as total_views, ' .
                    'COALESCE(SUM(pdf_downloads), 0) ' .
                    'as pdf_downloads'
                )
                ->first();

        $all =
            DB::table(
                'public_page_visit_stats'
            )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'page_key',
                    $pageKey
                )
                ->selectRaw(
                    'COALESCE(SUM(total_views), 0) ' .
                    'as total_views, ' .
                    'COALESCE(SUM(pdf_downloads), 0) ' .
                    'as pdf_downloads'
                )
                ->first();

        $last30Unique =
            DB::table(
                'public_page_visitors'
            )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'page_key',
                    $pageKey
                )
                ->whereDate(
                    'stat_date',
                    '>=',
                    $from30
                )
                ->distinct()
                ->count(
                    'visitor_hash'
                );

        $allUnique =
            DB::table(
                'public_page_visitors'
            )
                ->where(
                    'company_id',
                    $companyId
                )
                ->where(
                    'page_key',
                    $pageKey
                )
                ->distinct()
                ->count(
                    'visitor_hash'
                );

        return [
            'today' => [
                'views' =>
                    (int) (
                        $todayRow->total_views
                        ?? 0
                    ),

                'unique' =>
                    (int) (
                        $todayRow->unique_visitors
                        ?? 0
                    ),

                'pdf' =>
                    (int) (
                        $todayRow->pdf_downloads
                        ?? 0
                    ),
            ],

            'last30' => [
                'views' =>
                    (int) (
                        $last30->total_views
                        ?? 0
                    ),

                'unique' =>
                    (int) $last30Unique,

                'pdf' =>
                    (int) (
                        $last30->pdf_downloads
                        ?? 0
                    ),
            ],

            'all' => [
                'views' =>
                    (int) (
                        $all->total_views
                        ?? 0
                    ),

                'unique' =>
                    (int) $allUnique,

                'pdf' =>
                    (int) (
                        $all->pdf_downloads
                        ?? 0
                    ),
            ],
        ];
    }

    protected function ensureDailyRow(
        int $companyId,
        string $pageKey,
        string $statDate
    ): void {
        DB::table(
            'public_page_visit_stats'
        )
            ->insertOrIgnore([
                'company_id' =>
                    $companyId,

                'page_key' =>
                    $pageKey,

                'stat_date' =>
                    $statDate,

                'total_views' =>
                    0,

                'unique_visitors' =>
                    0,

                'pdf_downloads' =>
                    0,

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }

    protected function visitorHash(
        Request $request
    ): string {
        $visitorId =
            $request->cookie(
                self::VISITOR_COOKIE
            );

        if (
            ! is_string($visitorId)
            || strlen($visitorId) < 16
        ) {
            $visitorId =
                (string) Str::uuid();

            Cookie::queue(
                Cookie::make(
                    self::VISITOR_COOKIE,
                    $visitorId,
                    self::COOKIE_MINUTES,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'lax'
                )
            );
        }

        return hash_hmac(
            'sha256',
            $visitorId,
            (string) config('app.key')
        );
    }

    protected function shouldTrack(
        Request $request
    ): bool {
        $userAgent =
            strtolower(
                trim(
                    (string)
                    $request->userAgent()
                )
            );

        if ($userAgent === '') {
            return false;
        }

        return ! preg_match(
            '/bot|crawler|spider|slurp|' .
            'bingpreview|facebookexternalhit|' .
            'curl|wget|python-requests|' .
            'healthcheck|uptime/i',
            $userAgent
        );
    }
}

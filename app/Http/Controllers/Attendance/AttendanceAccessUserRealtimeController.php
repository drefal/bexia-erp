<?php

declare(strict_types=1);

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\Attendance\AttendanceAccessPendingInbox;
use App\Support\Attendance\AttendanceAccessUserChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class AttendanceAccessUserRealtimeController
    extends Controller
{
    private function identity(
        Request $request,
        int $company
    ): array {

        $actor = $request->user('web');

        abort_unless(
            $actor instanceof User,
            401
        );

        abort_unless(
            $company > 0,
            403
        );

        $tenant = Company::query()
            ->whereKey($company)
            ->where('active', true)
            ->first();

        abort_unless(
            $tenant instanceof Company
            && $actor->canAccessTenant($tenant),
            403
        );

        abort_unless(
            Schema::hasTable('hr_access_decisions'),
            503
        );

        abort_unless(
            AttendanceAccessPendingInbox
                ::canAccess($actor, $tenant),
            403
        );

        return [$actor, $tenant];
    }

    public function authorizeChannel(
        Request $request,
        int $company
    ): JsonResponse {

        [$actor, $tenant] = $this->identity(
            $request,
            $company
        );

        abort_unless(
            config('attendance_realtime.enabled'),
            503
        );

        $data = $request->validate([
            'socket_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^\d+\.\d+$/D',
            ],

            // Channel identity is selected exclusively
            // by the authenticated server context.

            'user_id' => ['prohibited'],
            'company_id' => ['prohibited'],
            'channel' => ['prohibited'],
            'channel_name' => ['prohibited'],
        ]);

        $key = (string) config(
            'attendance_realtime.key'
        );

        $secret = (string) config(
            'attendance_realtime.secret'
        );

        abort_if(
            $key === '' || $secret === '',
            503
        );

        $channel = AttendanceAccessUserChannel
            ::channel(
                (int) $tenant->id,
                (int) $actor->id
            );

        $signature = AttendanceAccessUserChannel
            ::signature(
                $data['socket_id'],
                (int) $tenant->id,
                (int) $actor->id,
                $secret
            );

        return response()->json([
            'channel' => $channel,
            'auth' => $key . ':' . $signature,
        ])->header(
            'Cache-Control',
            'private, no-store'
        );
    }

    public function pendingCount(
        Request $request,
        int $company
    ): JsonResponse {

        [$actor, $tenant] = $this->identity(
            $request,
            $company
        );

        $count = AttendanceAccessPendingInbox
            ::pendingCount(
                $actor,
                $tenant
            );

        return response()->json([
            'ok' => true,
            'count' => $count,
        ])->header(
            'Cache-Control',
            'private, no-store'
        );
    }
}

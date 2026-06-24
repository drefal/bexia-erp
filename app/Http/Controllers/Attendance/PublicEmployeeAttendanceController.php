<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\HrAttendanceLocation;
use App\Support\Attendance\GeofenceDistance;
use App\Support\EmployeeAttendanceIncidentSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicEmployeeAttendanceController extends Controller
{
    protected int $sameDeviceDifferentEmployeeWindowMinutes = 10;

    public function show(string $token)
    {
        $employee = $this->employeeByToken($token);

        abort_if(! $employee, 404);

        if (! (bool) $this->companySetting($employee, 'attendance_qr_enabled', true)) {
            abort(403, 'La asistencia por QR está desactivada para esta empresa.');
        }

        $attendance = $this->todayAttendance($employee);

        return view('attendance.public-employee-clock', [
            'employee' => $employee,
            'attendance' => $attendance,
            'token' => $token,
            'hasGeofence' => $this->geofenceQuery($employee)->exists(),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $employee = $this->employeeByToken($token);

        abort_if(! $employee, 404);

        if (! (bool) $this->companySetting($employee, 'attendance_qr_enabled', true)) {
            return back()->withErrors([
                'attendance' => 'La asistencia por QR está desactivada para esta empresa.',
            ]);
        }

        $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'device_fingerprint' => ['nullable', 'string', 'max:64'],
            'device_info' => ['nullable', 'string', 'max:4000'],
        ]);

        $attendance = $this->todayAttendance($employee) ?: new EmployeeAttendance();

        $direction = 'clock_in';

        if ($attendance->exists && $attendance->clock_in_at && ! $attendance->clock_out_at) {
            $direction = 'clock_out';
        } elseif ($attendance->exists && $attendance->clock_in_at && $attendance->clock_out_at) {
            return back()->with('warning', 'Ya tienes entrada y salida registradas para hoy.');
        }

        $deviceFingerprint = $request->input('device_fingerprint') ? substr((string) $request->input('device_fingerprint'), 0, 64) : null;
        $deviceInfo = $this->decodeDeviceInfo($request->input('device_info'));

        $deviceGuard = $this->deviceGuard(
            employee: $employee,
            direction: $direction,
            deviceFingerprint: $deviceFingerprint,
        );

        if (! $deviceGuard['allowed']) {
            return back()->with('warning', $deviceGuard['message']);
        }

        $locationPayload = $this->locationPayload(
            employee: $employee,
            direction: $direction,
            latitude: $request->input('latitude') !== null ? (float) $request->input('latitude') : null,
            longitude: $request->input('longitude') !== null ? (float) $request->input('longitude') : null,
            accuracy: $request->input('accuracy') !== null ? (int) $request->input('accuracy') : null,
            request: $request,
            deviceFingerprint: $deviceFingerprint,
            deviceInfo: $deviceInfo,
            deviceGuard: $deviceGuard,
        );

        if ($direction === 'clock_in') {
            $attendance->fill(array_merge([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_date' => now()->toDateString(),
                'clock_in_at' => now(),
                'source' => 'qr_link',
                'notes' => $this->appendNote($attendance->notes ?? null, 'Entrada registrada por QR publico.' . $this->locationNote($locationPayload) . $this->deviceNote($deviceGuard)),
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
            ], $locationPayload));
        } else {
            $attendance->forceFill(array_merge([
                'clock_out_at' => now(),
                'source' => $attendance->source ?: 'qr_link',
                'notes' => $this->appendNote($attendance->notes ?? null, 'Salida registrada por QR publico.' . $this->locationNote($locationPayload) . $this->deviceNote($deviceGuard)),
                'updated_by_user_id' => null,
            ], $locationPayload));
        }

        $attendance->save();

        if ($direction === 'clock_out') {
            $this->syncIncidentAfterClockOut($attendance);
        }

        return redirect()
            ->route('attendance.employee.show', ['token' => $token])
            ->with('success', ($direction === 'clock_in' ? 'Entrada' : 'Salida') . ' registrada correctamente.');
    }

    protected function syncIncidentAfterClockOut(EmployeeAttendance $attendance): void
    {
        try {
            EmployeeAttendanceIncidentSync::syncAll($attendance->fresh(), null, true);
        } catch (\Throwable $e) {
            report($e);

            try {
                $attendance->forceFill([
                    'notes' => $this->appendNote(
                        $attendance->notes ?? null,
                        'No se pudo generar incidencia automática desde QR: ' . $e->getMessage()
                    ),
                ])->save();
            } catch (\Throwable $inner) {
                report($inner);
            }
        }
    }


    protected function employeeByToken(string $token): ?Employee
    {
        if (! Schema::hasColumn('employees', 'attendance_qr_token')) {
            return null;
        }

        return Employee::query()
            ->where('attendance_qr_token', $token)
            ->where('attendance_qr_enabled', true)
            ->where('active', true)
            ->first();
    }

    protected function todayAttendance(Employee $employee): ?EmployeeAttendance
    {
        return EmployeeAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->first();
    }

    protected function geofenceQuery(Employee $employee)
    {
        $query = \App\Models\HrAttendanceLocation::query()
            ->where('company_id', $employee->company_id)
            ->where('is_active', true)
            ->where('allow_mobile_clock_in', true);

        try {
            $assignedIds = $employee->activeAttendanceLocations()
                ->pluck('hr_attendance_locations.id')
                ->filter()
                ->values();

            if ($assignedIds->isNotEmpty()) {
                $query->whereIn('id', $assignedIds->all());
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $query;
    }

    protected function nearestGeofence(Employee $employee, ?float $latitude, ?float $longitude): ?array
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $locations = $this->geofenceQuery($employee)->get();

        if ($locations->isEmpty()) {
            return null;
        }

        $nearest = null;

        foreach ($locations as $location) {
            $distance = GeofenceDistance::meters(
                $latitude,
                $longitude,
                (float) $location->latitude,
                (float) $location->longitude,
            );

            if (! $nearest || $distance < $nearest['distance_meters']) {
                $nearest = [
                    'location' => $location,
                    'distance_meters' => $distance,
                ];
            }
        }

        return $nearest;
    }

    protected function sameDeviceDifferentEmployeeWindowMinutes(Employee $employee): int
    {
        $company = null;

        if ($employee->relationLoaded('company')) {
            $company = $employee->company;
        }

        if (! $company && method_exists($employee, 'company')) {
            $company = $employee->company()->first();
        }

        if ($company && isset($company->attendance_qr_guard_enabled) && ! (bool) $company->attendance_qr_guard_enabled) {
            return 0;
        }

        $minutes = $company?->attendance_same_device_block_minutes;

        if ($minutes === null || $minutes === '') {
            return $this->sameDeviceDifferentEmployeeWindowMinutes;
        }

        return max(0, (int) $minutes);
    }

    protected function deviceGuard(Employee $employee, string $direction, ?string $deviceFingerprint): array
    {
        if (! $deviceFingerprint) {
            return [
                'allowed' => true,
                'status' => 'no_fingerprint',
                'message' => 'No se pudo identificar completamente el dispositivo.',
            ];
        }

        if (! Schema::hasColumn('employee_attendances', 'clock_in_device_fingerprint')) {
            return [
                'allowed' => true,
                'status' => 'schema_not_ready',
                'message' => null,
            ];
        }

        $windowMinutes = $this->sameDeviceDifferentEmployeeWindowMinutes($employee);

        if ($windowMinutes <= 0) {
            return [
                'allowed' => true,
                'status' => 'disabled_by_company',
                'message' => null,
            ];
        }

        $since = now()->subMinutes($windowMinutes);

        $recentOtherEmployee = DB::table('employee_attendances')
            ->where('company_id', $employee->company_id)
            ->where('employee_id', '<>', $employee->id)
            ->whereDate('attendance_date', now()->toDateString())
            ->where(function ($query) use ($deviceFingerprint, $since): void {
                $query->where(function ($query) use ($deviceFingerprint, $since): void {
                    $query->where('clock_in_device_fingerprint', $deviceFingerprint)
                        ->whereNotNull('clock_in_at')
                        ->where('clock_in_at', '>=', $since);
                })->orWhere(function ($query) use ($deviceFingerprint, $since): void {
                    $query->where('clock_out_device_fingerprint', $deviceFingerprint)
                        ->whereNotNull('clock_out_at')
                        ->where('clock_out_at', '>=', $since);
                });
            })
            ->orderByDesc('updated_at')
            ->first();

        if ($recentOtherEmployee) {
            return [
                'allowed' => false,
                'status' => 'blocked_same_device_other_employee',
                'message' => 'Este dispositivo ya fue usado recientemente para registrar a otro empleado dentro de ' . $windowMinutes . ' minutos. Usa tu propio celular o la tablet autorizada de la empresa.',
                'recent_attendance_id' => $recentOtherEmployee->id,
            ];
        }

        return [
            'allowed' => true,
            'status' => 'ok',
            'message' => null,
        ];
    }

    protected function decodeDeviceInfo(?string $deviceInfo): ?array
    {
        if (! $deviceInfo) {
            return null;
        }

        try {
            $decoded = json_decode($deviceInfo, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function locationPayload(
        Employee $employee,
        string $direction,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracy,
        Request $request,
        ?string $deviceFingerprint,
        ?array $deviceInfo,
        array $deviceGuard,
    ): array
    {
        $prefix = $direction === 'clock_out' ? 'clock_out' : 'clock_in';

        $payload = [
            $prefix . '_method' => 'qr_link',
            $prefix . '_ip_address' => $request->ip(),
            $prefix . '_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ];

        if (Schema::hasColumn('employee_attendances', $prefix . '_device_fingerprint')) {
            $payload[$prefix . '_device_fingerprint'] = $deviceFingerprint;
        }

        if (Schema::hasColumn('employee_attendances', $prefix . '_device_info')) {
            $payload[$prefix . '_device_info'] = $deviceInfo;
        }

        if (Schema::hasColumn('employee_attendances', $prefix . '_device_guard_status')) {
            $payload[$prefix . '_device_guard_status'] = $deviceGuard['status'] ?? null;
        }

        if (Schema::hasColumn('employee_attendances', $prefix . '_device_guard_message')) {
            $payload[$prefix . '_device_guard_message'] = $deviceGuard['message'] ?? null;
        }

        if (! (bool) $this->companySetting($employee, 'attendance_geofence_enabled', true)) {
            $payload[$prefix . '_latitude'] = $latitude;
            $payload[$prefix . '_longitude'] = $longitude;
            $payload[$prefix . '_accuracy_meters'] = $accuracy;
            $payload[$prefix . '_location_status'] = 'geofence_disabled';
            $payload['mobile_review_status'] = $payload['mobile_review_status'] ?? 'accepted';

            return $payload;
        }

        if ($latitude === null || $longitude === null) {
            $payload[$prefix . '_location_status'] = 'no_location';
            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        $payload[$prefix . '_latitude'] = $latitude;
        $payload[$prefix . '_longitude'] = $longitude;
        $payload[$prefix . '_accuracy_meters'] = $accuracy;

        $nearest = $this->nearestGeofence($employee, $latitude, $longitude);

        if (! $nearest) {
            $payload[$prefix . '_location_status'] = 'no_geofence';
            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        $location = $nearest['location'];
        $distance = (int) $nearest['distance_meters'];

        $status = GeofenceDistance::status(
            $distance,
            (int) $location->radius_meters,
            $accuracy,
            $location->accuracy_required_meters ? (int) $location->accuracy_required_meters : null,
        );

        $payload[$prefix . '_hr_attendance_location_id'] = $location->id;
        $payload[$prefix . '_distance_meters'] = $distance;
        $payload[$prefix . '_location_status'] = $status;

        if ($status === 'inside') {
            $payload['mobile_review_status'] = 'accepted';

            return $payload;
        }

        if (! (bool) $this->companySetting($employee, 'attendance_allow_outside_geofence', true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attendance' => 'No es posible registrar asistencia fuera de las geocercas permitidas.',
            ]);
        }

        $payload['mobile_review_status'] = (bool) $this->companySetting($employee, 'attendance_review_outside_geofence', true)
            ? 'pending'
            : 'accepted';

        return $payload;
    }

    protected function appendNote(?string $current, string $line): string
    {
        return trim(trim((string) $current) . PHP_EOL . $line);
    }

    protected function locationNote(array $payload): string
    {
        $status = (string) ($payload['clock_in_location_status'] ?? $payload['clock_out_location_status'] ?? '');
        $distance = $payload['clock_in_distance_meters'] ?? $payload['clock_out_distance_meters'] ?? null;

        $labels = [
            'inside' => 'Dentro de geocerca',
            'outside' => 'Fuera de geocerca',
            'poor_accuracy' => 'Precision GPS baja',
            'no_location' => 'Sin ubicacion',
            'no_geofence' => 'Sin geocerca',
            'geofence_disabled' => 'Geocerca desactivada',
        ];

        $note = ' Geocerca: ' . ($labels[$status] ?? 'Sin validar') . '.';

        if ($distance !== null) {
            $note .= ' Distancia: ' . $distance . ' m.';
        }

        return $note;
    }

    protected function deviceNote(array $deviceGuard): string
    {
        $status = $deviceGuard['status'] ?? null;

        if (! $status || $status === 'ok') {
            return ' Dispositivo: OK.';
        }

        return ' Dispositivo: ' . $status . '.';
    }
    protected function attendanceCompany(Employee $employee)
    {
        try {
            return $employee->company ?: \App\Models\Company::query()->find($employee->company_id);
        } catch (\Throwable $e) {
            report($e);

            return \App\Models\Company::query()->find($employee->company_id);
        }
    }

    protected function companySetting(Employee $employee, string $key, mixed $default = null): mixed
    {
        $company = $this->attendanceCompany($employee);

        return $company->{$key} ?? $default;
    }


}

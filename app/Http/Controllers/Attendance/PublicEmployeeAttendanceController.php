<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\HrAttendanceLocation;
use App\Support\Attendance\GeofenceDistance;
use App\Support\EmployeeAttendanceIncidentSync;
use App\Support\EmployeeWorkScheduleResolver;
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
            'action' => ['required', 'string', 'in:clock_in,meal_out,meal_in,clock_out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'device_fingerprint' => ['nullable', 'string', 'max:64'],
            'device_info' => ['nullable', 'string', 'max:4000'],
        ]);

        $attendance = $this->todayAttendance($employee) ?: new EmployeeAttendance();

        $direction = (string) $request->input('action');
        $allowedDirections = $this->allowedDirections($attendance);

        if (! in_array($direction, $allowedDirections, true)) {
            if ($attendance->exists && $attendance->clock_out_at) {
                return back()->with('warning', 'Tu registro de asistencia de hoy ya está completo.');
            }

            return back()->with(
                'warning',
                'La acción solicitada no corresponde al estado actual de tu asistencia. Actualiza la página e intenta nuevamente.'
            );
        }

        $deviceFingerprint = $request->input('device_fingerprint')
            ? substr((string) $request->input('device_fingerprint'), 0, 64)
            : null;

        $deviceInfo = $this->decodeDeviceInfo($request->input('device_info'));

        $deviceGuard = $this->deviceGuard(
            employee: $employee,
            direction: $direction,
            deviceFingerprint: $deviceFingerprint,
        );

        if (! $deviceGuard['allowed']) {
            return back()->with('warning', $deviceGuard['message']);
        }

        $locationPayload = $this->punchLocationPayload(
            employee: $employee,
            direction: $direction,
            latitude: $request->input('latitude') !== null
                ? (float) $request->input('latitude')
                : null,
            longitude: $request->input('longitude') !== null
                ? (float) $request->input('longitude')
                : null,
            accuracy: $request->input('accuracy') !== null
                ? (int) $request->input('accuracy')
                : null,
            request: $request,
            deviceFingerprint: $deviceFingerprint,
            deviceInfo: $deviceInfo,
            deviceGuard: $deviceGuard,
        );

        /*
         * Si una checada previa ya dejó pendiente de revisión el día,
         * una checada posterior dentro de geocerca no debe borrar ese pendiente.
         */
        if (
            $attendance->exists
            && $attendance->mobile_review_status === 'pending'
            && ($locationPayload['mobile_review_status'] ?? null) === 'accepted'
        ) {
            unset($locationPayload['mobile_review_status']);
        }

        $clockedAt = now();
        $mealWarning = null;

        switch ($direction) {
            case 'clock_in':
                $attendance->fill(array_merge([
                    'company_id' => $employee->company_id,
                    'employee_id' => $employee->id,
                    'attendance_date' => $clockedAt->toDateString(),
                    'meal_punch_required' => $this->mealPunchRequired($employee, $clockedAt),
                    'clock_in_at' => $clockedAt,
                    'source' => 'qr_link',
                    'notes' => $this->appendNote(
                        $attendance->notes ?? null,
                        'Entrada registrada por QR publico.'
                        . $this->locationNote($locationPayload)
                        . $this->deviceNote($deviceGuard)
                    ),
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                ], $locationPayload));
                break;

            case 'meal_out':
                $attendance->forceFill(array_merge([
                    'meal_out_at' => $clockedAt,
                    'source' => $attendance->source ?: 'qr_link',
                    'notes' => $this->appendNote(
                        $attendance->notes ?? null,
                        'Salida a comida registrada por QR publico.'
                        . $this->locationNote($locationPayload)
                        . $this->deviceNote($deviceGuard)
                    ),
                    'updated_by_user_id' => null,
                ], $locationPayload));
                break;

            case 'meal_in':
                $attendance->forceFill(array_merge([
                    'meal_in_at' => $clockedAt,
                    'source' => $attendance->source ?: 'qr_link',
                    'notes' => $this->appendNote(
                        $attendance->notes ?? null,
                        'Regreso de comida registrado por QR publico.'
                        . $this->locationNote($locationPayload)
                        . $this->deviceNote($deviceGuard)
                    ),
                    'updated_by_user_id' => null,
                ], $locationPayload));
                break;

            case 'clock_out':
                $mealPunchRequired = (bool) ($attendance->meal_punch_required ?? false);

                if ($mealPunchRequired && ! $attendance->meal_out_at) {
                    $mealWarning = 'Terminaste la jornada sin registrar la comida. El registro quedó pendiente de revisión.';
                } elseif (
                    $mealPunchRequired
                    && $attendance->meal_out_at
                    && ! $attendance->meal_in_at
                ) {
                    $mealWarning = 'Terminaste la jornada sin registrar el regreso de comida. El registro quedó pendiente de revisión.';
                }

                $payload = array_merge([
                    'clock_out_at' => $clockedAt,
                    'source' => $attendance->source ?: 'qr_link',
                    'notes' => $this->appendNote(
                        $attendance->notes ?? null,
                        'Salida final registrada por QR publico.'
                        . $this->locationNote($locationPayload)
                        . $this->deviceNote($deviceGuard)
                    ),
                    'updated_by_user_id' => null,
                ], $locationPayload);

                if ($mealWarning) {
                    $payload['mobile_review_status'] = 'pending';

                    $payload['notes'] = $this->appendNote(
                        $payload['notes'] ?? $attendance->notes,
                        'Revision requerida: comida no registrada o incompleta.'
                    );
                }

                $attendance->forceFill($payload);
                break;
        }

        $attendance->save();

        if ($direction === 'clock_out') {
            $this->syncIncidentAfterClockOut($attendance);
        }

        $labels = [
            'clock_in' => 'Entrada',
            'meal_out' => 'Salida a comida',
            'meal_in' => 'Regreso de comida',
            'clock_out' => 'Salida',
        ];

        $redirect = redirect()
            ->route('attendance.employee.show', ['token' => $token])
            ->with(
                'success',
                ($labels[$direction] ?? 'Registro') . ' registrada correctamente.'
            );

        if ($mealWarning) {
            $redirect->with('warning', $mealWarning);
        }

        return $redirect;
    }

    protected function allowedDirections(EmployeeAttendance $attendance): array
    {
        if (! $attendance->exists || ! $attendance->clock_in_at) {
            return ['clock_in'];
        }

        if ($attendance->clock_out_at) {
            return [];
        }

        $breakMinutes = max(0, (int) ($attendance->break_minutes ?? 0));

        if ($breakMinutes <= 0) {
            return ['clock_out'];
        }

        if (! $attendance->meal_out_at) {
            return ['meal_out', 'clock_out'];
        }

        if (! $attendance->meal_in_at) {
            return ['meal_in', 'clock_out'];
        }

        return ['clock_out'];
    }

    protected function mealPunchRequired(Employee $employee, mixed $at): bool
    {
        try {
            $schedule = EmployeeWorkScheduleResolver::scheduleForEmployee(
                $employee,
                $at,
            );

            return (int) ($schedule['break_minutes'] ?? 0) > 0;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    protected function employeeGeofencePolicy(Employee $employee): string
    {
        /*
         * Compatibilidad temporal si el esquema c8 aun no existiera.
         */
        if (! Schema::hasColumn('employees', 'attendance_geofence_policy')) {
            if (! (bool) $this->companySetting(
                $employee,
                'attendance_allow_outside_geofence',
                true
            )) {
                return 'fixed';
            }

            return (bool) $this->companySetting(
                $employee,
                'attendance_review_outside_geofence',
                true
            )
                ? 'mobile_review'
                : 'mobile_authorized';
        }

        $policy = trim(
            (string) ($employee->attendance_geofence_policy ?: 'fixed')
        );

        if (! in_array(
            $policy,
            ['fixed', 'mobile_review', 'mobile_authorized'],
            true
        )) {
            return 'fixed';
        }

        return $policy;
    }


    protected function punchLocationPayload(
        Employee $employee,
        string $direction,
        ?float $latitude,
        ?float $longitude,
        ?int $accuracy,
        Request $request,
        ?string $deviceFingerprint,
        ?array $deviceInfo,
        array $deviceGuard,
    ): array {
        if (in_array($direction, ['clock_in', 'clock_out'], true)) {
            return $this->locationPayload(
                employee: $employee,
                direction: $direction,
                latitude: $latitude,
                longitude: $longitude,
                accuracy: $accuracy,
                request: $request,
                deviceFingerprint: $deviceFingerprint,
                deviceInfo: $deviceInfo,
                deviceGuard: $deviceGuard,
            );
        }

        /*
         * Reutiliza la validación completa de geocerca existente.
         * Después traduce únicamente los campos que existen para meal_out/meal_in.
         */
        $base = $this->locationPayload(
            employee: $employee,
            direction: 'clock_in',
            latitude: $latitude,
            longitude: $longitude,
            accuracy: $accuracy,
            request: $request,
            deviceFingerprint: $deviceFingerprint,
            deviceInfo: $deviceInfo,
            deviceGuard: $deviceGuard,
        );

        $payload = [];

        foreach ($base as $key => $value) {
            if ($key === 'mobile_review_status') {
                $payload[$key] = $value;
                continue;
            }

            if (! str_starts_with($key, 'clock_in_')) {
                continue;
            }

            $target = $direction . substr($key, strlen('clock_in'));

            if (Schema::hasColumn('employee_attendances', $target)) {
                $payload[$target] = $value;
            }
        }

        return $payload;
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

            $isPolygon = method_exists($location, 'usesPolygonGeofence') && $location->usesPolygonGeofence();
            $insidePolygon = false;

            if ($isPolygon && method_exists($location, 'polygonPoints')) {
                $insidePolygon = GeofenceDistance::pointInPolygon(
                    $latitude,
                    $longitude,
                    $location->polygonPoints(),
                );
            }

            if (! $nearest || $insidePolygon || $distance < $nearest['distance_meters']) {
                $nearest = [
                    'location' => $location,
                    'distance_meters' => $distance,
                    'inside_polygon' => $insidePolygon,
                    'geofence_type' => $isPolygon ? 'polygon' : 'circle',
                ];

                if ($insidePolygon) {
                    break;
                }
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

        $policy = $this->employeeGeofencePolicy($employee);

        /*
         * attendance_geofence_enabled sigue siendo el interruptor maestro.
         */
        if (! (bool) $this->companySetting(
            $employee,
            'attendance_geofence_enabled',
            true
        )) {
            $payload[$prefix . '_latitude'] = $latitude;
            $payload[$prefix . '_longitude'] = $longitude;
            $payload[$prefix . '_accuracy_meters'] = $accuracy;
            $payload[$prefix . '_location_status'] = 'geofence_disabled';
            $payload['mobile_review_status'] =
                $payload['mobile_review_status'] ?? 'accepted';

            return $payload;
        }

        /*
         * FIJO:
         * debe poder demostrar que esta dentro de alguna geocerca permitida.
         *
         * MOVIL:
         * si no hay GPS no se acepta automaticamente; queda pendiente.
         */
        if ($latitude === null || $longitude === null) {
            $payload[$prefix . '_location_status'] = 'no_location';

            if ($policy === 'fixed') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attendance' => 'No se pudo obtener tu ubicación. Los empleados con política Fijo deben registrar dentro de una geocerca autorizada.',
                ]);
            }

            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        $payload[$prefix . '_latitude'] = $latitude;
        $payload[$prefix . '_longitude'] = $longitude;
        $payload[$prefix . '_accuracy_meters'] = $accuracy;

        $nearest = $this->nearestGeofence(
            $employee,
            $latitude,
            $longitude
        );

        /*
         * Si no existe ninguna geocerca utilizable:
         * - Fijo: bloquea.
         * - Movil con revision: permite pendiente.
         * - Movil autorizado: permite aceptado porque si entrego GPS.
         */
        if (! $nearest) {
            $payload[$prefix . '_location_status'] = 'no_geofence';

            if ($policy === 'fixed') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'attendance' => 'No hay una geocerca autorizada disponible para este registro. Solicita a RRHH revisar tu configuración.',
                ]);
            }

            $payload['mobile_review_status'] =
                $policy === 'mobile_authorized'
                    ? 'accepted'
                    : 'pending';

            return $payload;
        }

        $location = $nearest['location'];
        $distance = (int) $nearest['distance_meters'];

        $accuracyRequired = $location->accuracy_required_meters
            ? (int) $location->accuracy_required_meters
            : null;

        if (
            ($nearest['geofence_type'] ?? 'circle') === 'polygon'
            && method_exists($location, 'polygonPoints')
        ) {
            $status = GeofenceDistance::polygonStatus(
                $latitude,
                $longitude,
                $location->polygonPoints(),
                $accuracy,
                $accuracyRequired,
            );
        } else {
            $status = GeofenceDistance::status(
                $nearest['distance_meters'],
                (int) $location->radius_meters,
                $accuracy,
                $accuracyRequired,
            );
        }

        $payload[$prefix . '_hr_attendance_location_id'] = $location->id;
        $payload[$prefix . '_distance_meters'] = $distance;
        $payload[$prefix . '_location_status'] = $status;

        /*
         * Cualquier geocerca permitida es valida.
         * NO es obligatorio salir en la misma geocerca de la entrada.
         */
        if ($status === 'inside') {
            $payload['mobile_review_status'] = 'accepted';

            return $payload;
        }

        /*
         * FIJO: cualquier resultado distinto de inside se bloquea.
         */
        if ($policy === 'fixed') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attendance' => 'Estás fuera de las geocercas autorizadas para tu perfil Fijo. No se registró la asistencia.',
            ]);
        }

        /*
         * GPS impreciso nunca se acepta automaticamente, incluso en
         * Movil autorizado.
         */
        if ($status === 'poor_accuracy') {
            $payload['mobile_review_status'] = 'pending';

            return $payload;
        }

        /*
         * MOVIL CON REVISION:
         * se permite y queda pendiente.
         *
         * MOVIL AUTORIZADO:
         * se permite y queda aceptado.
         */
        $payload['mobile_review_status'] =
            $policy === 'mobile_authorized'
                ? 'accepted'
                : 'pending';

        return $payload;
    }


    protected function appendNote(?string $current, string $line): string
    {
        return trim(trim((string) $current) . PHP_EOL . $line);
    }

    protected function locationNote(array $payload): string
    {
        $status = (string) (
            $payload['meal_out_location_status']
            ?? $payload['meal_in_location_status']
            ?? $payload['clock_in_location_status']
            ?? $payload['clock_out_location_status']
            ?? ''
        );

        $distance = $payload['meal_out_distance_meters']
            ?? $payload['meal_in_distance_meters']
            ?? $payload['clock_in_distance_meters']
            ?? $payload['clock_out_distance_meters']
            ?? null;

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

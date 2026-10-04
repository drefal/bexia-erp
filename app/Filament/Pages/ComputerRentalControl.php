<?php

namespace App\Filament\Pages;

use App\Models\ComputerRentalRate;
use App\Models\ComputerRentalSession;
use App\Models\ComputerRentalSessionLine;
use App\Models\ComputerRentalStation;
use App\Models\PosPoint;
use App\Models\Product;
use App\Support\ComputerRental\ComputerRentalCalculator;
use App\Support\ComputerRental\ComputerRentalSessionService;
use App\Support\ComputerRental\ComputerRentalToPosService;
use App\Support\ComputerRental\ComputerRentalPosStatusService;
use App\Support\ComputerRental\ComputerRentalAgentCommandService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ComputerRentalControl extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = 'Punto de Venta';

    protected static ?string $navigationLabel = 'Renta de equipos';

    protected static ?string $title = 'Renta de equipos';

    protected static ?int $navigationSort = 80;

    protected static string $view = 'filament.pages.computer-rental-control';

    public ?string $stationCode = null;
    public ?string $stationName = null;
    public ?int $stationPosPointId = null;
    public ?int $stationDefaultRateId = null;

    public ?string $rateName = null;
    public string $rateBillingMode = 'open';
    public ?float $rateHourlyRate = null;
    public int $rateMinimumMinutes = 1;
    public int $rateIncrementMinutes = 1;
    public int $rateCancellationGraceMinutes = 5;
    public ?int $ratePrepaidMinutes = null;
    public ?float $ratePrepaidPrice = null;
    public float $rateTaxRate = 0.16;
    public ?int $rateProductId = null;

    public array $selectedRates = [];

    /** CIBER4E2A1_POWER_CONFIRM_MODAL */
    public ?int $powerCommandStationId = null;
    public ?string $powerCommandType = null;

    /** CIBER3G1_FINALIZE_TO_POS */
    public ?int $finishSessionId = null;

    /** CIBER3H3B_RESEND_UI */
    public ?int $resendTicketSessionId = null;

    /** CIBER3F_EDIT_RATE */
    public ?int $editingRateId = null;
    public ?string $editRateName = null;
    public string $editRateBillingMode = 'open';
    public ?float $editRateHourlyRate = null;
    public int $editRateMinimumMinutes = 1;
    public int $editRateIncrementMinutes = 1;
    public int $editRateCancellationGraceMinutes = 5;
    public ?int $editRatePrepaidMinutes = null;
    public ?float $editRatePrepaidPrice = null;
    public float $editRateTaxRate = 0.16;
    public ?int $editRateProductId = null;
    public bool $editRateIsActive = true;

    /** CIBER3E_EDIT_STATION */
    public ?int $editingStationId = null;
    public ?string $editStationCode = null;
    public ?string $editStationName = null;
    public ?int $editStationPosPointId = null;
    public ?int $editStationDefaultRateId = null;
    public string $editStationStatus = 'available';
    public bool $editStationIsActive = true;
    public ?string $editStationNotes = null;

    /** CIBER3D_OPEN_ACCOUNT */
    public array $consumptionProductIds = [];
    public array $consumptionQuantities = [];

    /** CIBER3D3_REMOVE_MODAL */
    public ?int $removeConsumptionLineId = null;

    public ?int $cancelSessionId = null;
    public ?string $cancelReason = null;

    /**
     * CIBER3I1_STRICT_PAPELON_ACCESS
     *
     * Este módulo es exclusivo de Papelería Papelón.
     *
     * Requisitos:
     * - tenant actual = company_id 3;
     * - usuario con asignación real en company_id 3;
     * - permiso computer_rental.view.
     *
     * No usamos BexiaTenantPermission::can() aquí porque su bypass
     * administrativo es intencionalmente más amplio que este módulo.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return \App\Support\Navigation\BexiaMenuRuntime::shouldRegister(
            'pages.computerrentalcontrol',
            fn (): bool =>
                static::canComputerRentalPermission(
                    'computer_rental.view'
                ),
        );
    }

    protected static function currentRentalTenantId(): int
    {
        try {
            $tenant = Filament::getTenant();

            if (
                $tenant
                && method_exists(
                    $tenant,
                    'getKey'
                )
            ) {
                return (int)
                    $tenant->getKey();
            }

            if (
                $tenant
                && isset($tenant->id)
            ) {
                return (int)
                    $tenant->id;
            }
        } catch (\Throwable) {
            //
        }

        try {
            $routeTenant =
                request()->route('tenant');

            if (is_numeric($routeTenant)) {
                return (int) $routeTenant;
            }
        } catch (\Throwable) {
            //
        }

        return 0;
    }

    protected static function userBelongsToPapelon(
        object $user
    ): bool {
        $userId =
            method_exists(
                $user,
                'getAuthIdentifier'
            )
                ? (int)
                    $user->getAuthIdentifier()
                : (int)
                    ($user->id ?? 0);

        if ($userId <= 0) {
            return false;
        }

        if (
            ! \Illuminate\Support\Facades\Schema::hasTable(
                'model_has_roles'
            )
        ) {
            return false;
        }

        $query =
            \Illuminate\Support\Facades\DB::table(
                'model_has_roles'
            )
                ->where(
                    'model_id',
                    $userId
                );

        if (
            \Illuminate\Support\Facades\Schema::hasColumn(
                'model_has_roles',
                'model_type'
            )
        ) {
            $query->where(
                'model_type',
                get_class($user)
            );
        }

        if (
            \Illuminate\Support\Facades\Schema::hasColumn(
                'model_has_roles',
                'company_id'
            )
        ) {
            $query->where(
                'company_id',
                3
            );

            return $query->exists();
        }

        if (
            \Illuminate\Support\Facades\Schema::hasColumn(
                'model_has_roles',
                'team_id'
            )
        ) {
            $query->where(
                'team_id',
                3
            );

            return $query->exists();
        }

        return false;
    }

    protected static function canComputerRentalPermission(
        string $permission
    ): bool {
        if (
            ! auth()->check()
            || static::currentRentalTenantId()
                !== 3
        ) {
            return false;
        }

        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (
            ! static::userBelongsToPapelon(
                $user
            )
        ) {
            return false;
        }

        try {
            return method_exists(
                $user,
                'can'
            )
                && (bool)
                    $user->can(
                        $permission
                    );
        } catch (\Throwable) {
            return false;
        }
    }

    public static function canAccess(): bool
    {
        return static::canComputerRentalPermission(
            'computer_rental.view'
        );
    }

    public function canOperateComputerRental(): bool
    {
        return static::canComputerRentalPermission(
            'computer_rental.operate'
        );
    }

    public function canManageComputerRental(): bool
    {
        return static::canComputerRentalPermission(
            'computer_rental.manage'
        );
    }

    protected function authorizeComputerRentalOperation():
        void
    {
        abort_unless(
            $this->canOperateComputerRental(),
            403
        );
    }

    protected function authorizeComputerRentalManagement():
        void
    {
        abort_unless(
            $this->canManageComputerRental(),
            403
        );
    }
    protected function companyId(): int
    {
        $tenant = Filament::getTenant();

        if ($tenant && isset($tenant->id)) {
            return (int) $tenant->id;
        }

        return 0;
    }

    /**
     * CIBER4E2A1_POWER_CONFIRM_MODAL
     */
    public function beginStationPowerCommand(
        int $stationId,
        string $commandType
    ): void {
        $this->authorizeComputerRentalManagement();

        if (! in_array(
            $commandType,
            [
                'shutdown',
                'restart',
                'start_ui',
            ],
            true
        )) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'command' =>
                    'Comando remoto no permitido.',
            ]);
        }

        ComputerRentalStation::query()
            ->where(
                'company_id',
                $this->companyId()
            )
            ->whereKey(
                $stationId
            )
            ->where(
                'is_active',
                true
            )
            ->firstOrFail();

        $this->powerCommandStationId =
            $stationId;

        $this->powerCommandType =
            $commandType;
    }

    public function cancelStationPowerCommand(): void
    {
        $this->powerCommandStationId =
            null;

        $this->powerCommandType =
            null;
    }

    public function confirmStationPowerCommand(): void
    {
        $this->authorizeComputerRentalManagement();

        $stationId =
            (int) (
                $this->powerCommandStationId
                ?? 0
            );

        $commandType =
            (string) (
                $this->powerCommandType
                ?? ''
            );

        if (
            $stationId <= 0
            || ! in_array(
                $commandType,
                [
                    'shutdown',
                    'restart',
                    'start_ui',
                ],
                true
            )
        ) {
            $this->cancelStationPowerCommand();

            throw \Illuminate\Validation\ValidationException::withMessages([
                'command' =>
                    'No hay una orden remota válida para confirmar.',
            ]);
        }

        try {
            $this->queueStationPowerCommand(
                $stationId,
                $commandType
            );
        } finally {
            $this->cancelStationPowerCommand();
        }
    }

    public function powerCommandStation(): ?ComputerRentalStation
    {
        $stationId =
            (int) (
                $this->powerCommandStationId
                ?? 0
            );

        if ($stationId <= 0) {
            return null;
        }

        return ComputerRentalStation::query()
            ->where(
                'company_id',
                $this->companyId()
            )
            ->whereKey(
                $stationId
            )
            ->first();
    }

    /**
     * CIBER4E2A_REMOTE_POWER
     */
    public function queueStationPowerCommand(
        int $stationId,
        string $commandType
    ): void {
        $this->authorizeComputerRentalManagement();

        $station =
            ComputerRentalStation::query()
                ->where(
                    'company_id',
                    $this->companyId()
                )
                ->whereKey(
                    $stationId
                )
                ->where(
                    'is_active',
                    true
                )
                ->firstOrFail();

        app(
            ComputerRentalAgentCommandService::class
        )->queue(
            $station,
            $commandType,
            auth()->id()
                ? (int) auth()->id()
                : null
        );

        Notification::make()
            ->title(
                match ($commandType) {
                    'shutdown' =>
                        'Orden de apagado enviada',

                    'restart' =>
                        'Orden de reinicio enviada',

                    'start_ui' =>
                        'Reactivación de bloqueo enviada',

                    default =>
                        'Orden remota enviada',
                }
            )
            ->body(
                $station->code
                . ' recibirá la orden en su siguiente heartbeat.'
            )
            ->success()
            ->send();
    }

    public function latestStationCommand(
        int $stationId
    ): ?object {
        if (
            ! Schema::hasTable(
                'computer_rental_agent_commands'
            )
        ) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table(
            'computer_rental_agent_commands'
        )
            ->where(
                'company_id',
                $this->companyId()
            )
            ->where(
                'station_id',
                $stationId
            )
            ->orderByDesc('id')
            ->first();
    }

    public function getStationsProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return ComputerRentalStation::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['defaultRate'])
            ->orderBy('code')
            ->get();
    }

    public function getRatesProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return ComputerRentalRate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function getAllRatesProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return ComputerRentalRate::query()
            ->where('company_id', $companyId)
            ->with('product')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function rateStationCount(int $rateId): int
    {
        return ComputerRentalStation::query()
            ->where('company_id', $this->companyId())
            ->where('default_rate_id', $rateId)
            ->where('is_active', true)
            ->count();
    }

    /** CIBER3G2_PAYMENT_SYNC */
    public function syncPosStatuses(): void
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return;
        }

        app(ComputerRentalPosStatusService::class)
            ->syncCompany($companyId);
    }

    public function getPendingPosRentalsCountProperty(): int
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return 0;
        }

        return (int) \Illuminate\Support\Facades\DB::table(
            'computer_rental_sessions as crs'
        )
            ->join(
                'pos_orders as po',
                'po.id',
                '=',
                'crs.pos_order_id'
            )
            ->where(
                'crs.company_id',
                $companyId
            )
            ->where(
                'po.status',
                'pending_payment'
            )
            ->count();
    }

    public function rentalPosSummary(
        ComputerRentalSession $session
    ): array {
        if (! $session->pos_order_id) {
            return [
                'exists' => false,
                'number' => null,
                'status' => null,
                'status_label' => 'Sin ticket',
                'total' => 0.0,
                'paid' => 0.0,
                'balance' => 0.0,
                'paid_at' => null,
            ];
        }

        $order = \Illuminate\Support\Facades\DB::table(
            'pos_orders'
        )
            ->where(
                'id',
                (int) $session->pos_order_id
            )
            ->first();

        if (! $order) {
            return [
                'exists' => false,
                'number' => null,
                'status' => null,
                'status_label' => 'Ticket no encontrado',
                'total' => 0.0,
                'paid' => 0.0,
                'balance' => 0.0,
                'paid_at' => null,
            ];
        }

        $paid = 0.0;

        if (
            \Illuminate\Support\Facades\Schema::hasTable(
                'pos_order_payments'
            )
        ) {
            $paid = round(
                (float) \Illuminate\Support\Facades\DB::table(
                    'pos_order_payments'
                )
                    ->where(
                        'pos_order_id',
                        (int) $order->id
                    )
                    ->where(
                        'status',
                        'paid'
                    )
                    ->sum('amount'),
                2
            );
        }

        $total = round(
            (float) ($order->total ?? 0),
            2
        );

        /*
         * Si el ticket ya esta pagado, el total pagado
         * debe representar al menos el total del ticket,
         * aun si el esquema historico de pagos no tuviera
         * una linea individual.
         */
        if (
            (string) ($order->status ?? '')
            === 'paid'
            && $paid < $total
        ) {
            $paid = $total;
        }

        $balance = max(
            0,
            round($total - $paid, 2)
        );

        $status = strtolower(
            trim(
                (string) ($order->status ?? '')
            )
        );

        /** CIBER3H1_POS_SUMMARY_CANCELLED */
        $orderMetadata = [];

        if (! empty($order->metadata)) {
            $decoded = json_decode(
                (string) $order->metadata,
                true
            );

            if (is_array($decoded)) {
                $orderMetadata = $decoded;
            }
        }

        $label = match ($status) {
            'paid' => 'Pagado',
            'pending_payment' =>
                $paid > 0
                    ? 'Pago parcial'
                    : 'Pendiente de cobro',
            'cancelled', 'canceled' =>
                'Ticket cancelado',
            default =>
                ucfirst(
                    str_replace(
                        '_',
                        ' ',
                        $status ?: 'desconocido'
                    )
                ),
        };

        return [
            'exists' => true,
            'id' => (int) $order->id,
            'number' =>
                (string) ($order->number ?? ''),
            'status' => $status,
            'status_label' => $label,
            'total' => $total,
            'paid' => $paid,
            'balance' => $balance,
            'paid_at' => $order->paid_at ?? null,
            'cancelled_at' =>
                $order->cancelled_at
                    ?? ($orderMetadata['cancelled_at'] ?? null),
            'cancel_reason' =>
                $orderMetadata['cancel_reason']
                    ?? null,
            'cancelled_by_user_id' =>
                $orderMetadata['cancelled_by_user_id']
                    ?? null,
        ];
    }

    public function getRecentSessionsProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->with(['station', 'rate', 'posOrder'])
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    public function getPosPointsProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return PosPoint::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    public function getServiceProductsProperty()
    {
        $companyId = $this->companyId();

        if (
            $companyId <= 0
            || ! Schema::hasTable('products')
        ) {
            return collect();
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->where('product_type', 'service')
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    public function createRate(): void
    {
        $this->authorizeComputerRentalManagement();
        $companyId = $this->companyId();

        abort_if($companyId <= 0, 422, 'Empresa no válida.');

        $data = $this->validate([
            'rateName' => ['required', 'string', 'max:160'],
            'rateBillingMode' => ['required', 'in:open,prepaid'],
            'rateHourlyRate' => ['nullable', 'numeric', 'min:0'],
            'rateMinimumMinutes' => ['required', 'integer', 'min:1'],
            'rateIncrementMinutes' => ['required', 'integer', 'min:1'],
            'rateCancellationGraceMinutes' => [
                'required',
                'integer',
                'min:0',
                'max:60',
            ],
            'ratePrepaidMinutes' => ['nullable', 'integer', 'min:1'],
            'ratePrepaidPrice' => ['nullable', 'numeric', 'min:0'],
            'rateTaxRate' => ['required', 'numeric', 'min:0', 'max:1'],
            'rateProductId' => ['nullable', 'integer'],
        ]);

        if ($data['rateBillingMode'] === 'open' && (float) ($data['rateHourlyRate'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                'rateHourlyRate' => 'La tarifa por hora debe ser mayor a cero.',
            ]);
        }

        if ($data['rateBillingMode'] === 'prepaid') {
            if ((int) ($data['ratePrepaidMinutes'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'ratePrepaidMinutes' => 'Indica los minutos del paquete.',
                ]);
            }

            if ((float) ($data['ratePrepaidPrice'] ?? 0) <= 0) {
                throw ValidationException::withMessages([
                    'ratePrepaidPrice' => 'Indica el precio del paquete.',
                ]);
            }
        }

        $productId = $data['rateProductId'] ?: null;

        if ($productId) {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->where('id', $productId)
                ->where('product_type', 'service')
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'rateProductId' => 'El producto debe ser un servicio de esta empresa.',
                ]);
            }
        }

        ComputerRentalRate::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'name' => trim($data['rateName']),
            'billing_mode' => $data['rateBillingMode'],
            'hourly_rate' => $data['rateBillingMode'] === 'open'
                ? (float) $data['rateHourlyRate']
                : 0,
            'minimum_minutes' => $data['rateMinimumMinutes'],
            'billing_increment_minutes' => $data['rateIncrementMinutes'],
            'cancellation_grace_minutes' =>
                (int) $data['rateCancellationGraceMinutes'],
            'prepaid_minutes' => $data['rateBillingMode'] === 'prepaid'
                ? $data['ratePrepaidMinutes']
                : null,
            'prepaid_price' => $data['rateBillingMode'] === 'prepaid'
                ? $data['ratePrepaidPrice']
                : null,
            'tax_rate' => $data['rateTaxRate'],
            'is_active' => true,
        ]);

        $this->reset([
            'rateName',
            'rateHourlyRate',
            'ratePrepaidMinutes',
            'ratePrepaidPrice',
            'rateProductId',
        ]);

        $this->rateBillingMode = 'open';
        $this->rateMinimumMinutes = 1;
        $this->rateIncrementMinutes = 1;
        $this->rateCancellationGraceMinutes = 5;
        $this->rateTaxRate = 0.16;

        Notification::make()
            ->success()
            ->title('Tarifa creada')
            ->send();
    }

    public function createStation(): void
    {
        $this->authorizeComputerRentalManagement();
        $companyId = $this->companyId();

        abort_if($companyId <= 0, 422, 'Empresa no válida.');

        $data = $this->validate([
            'stationCode' => ['required', 'string', 'max:80'],
            'stationName' => ['required', 'string', 'max:160'],
            'stationPosPointId' => ['nullable', 'integer'],
            'stationDefaultRateId' => ['nullable', 'integer'],
        ]);

        $code = strtoupper(trim($data['stationCode']));

        if (
            ComputerRentalStation::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($code)])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'stationCode' => 'Ya existe una estación con ese código.',
            ]);
        }

        if ($data['stationDefaultRateId']) {
            $validRate = ComputerRentalRate::query()
                ->where('company_id', $companyId)
                ->where('id', $data['stationDefaultRateId'])
                ->exists();

            if (! $validRate) {
                throw ValidationException::withMessages([
                    'stationDefaultRateId' => 'La tarifa no corresponde a esta empresa.',
                ]);
            }
        }

        if ($data['stationPosPointId']) {
            $validPos = PosPoint::query()
                ->where('company_id', $companyId)
                ->where('id', $data['stationPosPointId'])
                ->exists();

            if (! $validPos) {
                throw ValidationException::withMessages([
                    'stationPosPointId' => 'El PDV no corresponde a esta empresa.',
                ]);
            }
        }

        ComputerRentalStation::create([
            'company_id' => $companyId,
            'pos_point_id' => $data['stationPosPointId'] ?: null,
            'default_rate_id' => $data['stationDefaultRateId'] ?: null,
            'code' => $code,
            'name' => trim($data['stationName']),
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->reset([
            'stationCode',
            'stationName',
            'stationPosPointId',
            'stationDefaultRateId',
        ]);

        Notification::make()
            ->success()
            ->title('Estación creada')
            ->send();
    }

    public function startRental(int $stationId): void
    {
        $this->authorizeComputerRentalOperation();
        $companyId = $this->companyId();

        $station = ComputerRentalStation::query()
            ->where('company_id', $companyId)
            ->findOrFail($stationId);

        $rateId = (int) ($this->selectedRates[$stationId] ?? $station->default_rate_id ?? 0);

        if ($rateId <= 0) {
            Notification::make()
                ->danger()
                ->title('Selecciona una tarifa')
                ->send();

            return;
        }

        $rate = ComputerRentalRate::query()
            ->where('company_id', $companyId)
            ->findOrFail($rateId);

        app(ComputerRentalSessionService::class)
            ->start(
                $station,
                $rate,
                auth()->id()
            );

        Notification::make()
            ->success()
            ->title('Renta iniciada')
            ->body($station->code . ' · ' . $rate->name)
            ->send();
    }

    public function beginResendCancelledTicket(
        int $sessionId
    ): void {
        $session =
            ComputerRentalSession::query()
                ->where(
                    'company_id',
                    $this->companyId()
                )
                ->where(
                    'status',
                    'ticket_cancelled'
                )
                ->findOrFail($sessionId);

        $this->resendTicketSessionId =
            (int) $session->id;
    }

    public function cancelResendCancelledTicket(): void
    {
        $this->resendTicketSessionId = null;
    }

    public function getResendingTicketSessionProperty():
        ?ComputerRentalSession
    {
        if (! $this->resendTicketSessionId) {
            return null;
        }

        return ComputerRentalSession::query()
            ->where(
                'company_id',
                $this->companyId()
            )
            ->where(
                'status',
                'ticket_cancelled'
            )
            ->with([
                'station',
                'posOrder',
            ])
            ->find(
                $this->resendTicketSessionId
            );
    }

    public function resendCancelledTicket(): void
    {
        $this->authorizeComputerRentalOperation();
        if (! $this->resendTicketSessionId) {
            return;
        }

        $session =
            ComputerRentalSession::query()
                ->where(
                    'company_id',
                    $this->companyId()
                )
                ->where(
                    'status',
                    'ticket_cancelled'
                )
                ->findOrFail(
                    $this->resendTicketSessionId
                );

        try {
            $result =
                app(
                    ComputerRentalToPosService::class
                )
                    ->resendCancelledTicket(
                        $session,
                        auth()->id()
                    );

        } catch (ValidationException $e) {

            $message =
                collect($e->errors())
                    ->flatten()
                    ->first()
                ?: $e->getMessage();

            Notification::make()
                ->danger()
                ->title(
                    'No se pudo generar el nuevo ticket'
                )
                ->body(
                    (string) $message
                )
                ->send();

            return;

        } catch (\Throwable $e) {

            report($e);

            Notification::make()
                ->danger()
                ->title(
                    'No se pudo generar el nuevo ticket'
                )
                ->body(
                    'La operación fue revertida. '
                    . $e->getMessage()
                )
                ->send();

            return;
        }

        $this->resendTicketSessionId = null;

        Notification::make()
            ->success()
            ->title(
                'Nuevo ticket generado'
            )
            ->body(
                (
                    $result['number']
                    ?: '#'
                        . $result[
                            'pos_order_id'
                        ]
                )
                . ' · $'
                . number_format(
                    (float)
                        $result['total'],
                    2
                )
            )
            ->send();
    }

    public function beginFinishRental(int $sessionId): void
    {
        $session = ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->where('status', 'active')
            ->findOrFail($sessionId);

        $this->finishSessionId =
            (int) $session->id;
    }

    public function cancelFinishRental(): void
    {
        $this->finishSessionId = null;
    }

    public function getFinishingSessionProperty(): ?ComputerRentalSession
    {
        if (! $this->finishSessionId) {
            return null;
        }

        return ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->where('status', 'active')
            ->with('station')
            ->find($this->finishSessionId);
    }

    public function finishRental(int $sessionId): void
    {
        $this->authorizeComputerRentalOperation();
        $companyId = $this->companyId();

        $session = ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->findOrFail($sessionId);

        try {
            $result = app(
                ComputerRentalToPosService::class
            )->finalizeAndSend(
                $session,
                auth()->id()
            );
        } catch (ValidationException $e) {

            $message = collect(
                $e->errors()
            )
                ->flatten()
                ->first()
                ?: $e->getMessage();

            Notification::make()
                ->danger()
                ->title(
                    'No se pudo finalizar la renta'
                )
                ->body((string) $message)
                ->send();

            return;

        } catch (\Throwable $e) {

            report($e);

            Notification::make()
                ->danger()
                ->title(
                    'No se pudo finalizar la renta'
                )
                ->body(
                    'La operación fue revertida. '
                    . 'La PC continúa en uso. '
                    . $e->getMessage()
                )
                ->send();

            return;
        }

        $this->finishSessionId = null;

        Notification::make()
            ->success()
            ->title(
                'Renta enviada al Punto de Venta'
            )
            ->body(
                'Ticket '
                . (
                    $result['number']
                    ?: '#'
                        . $result['pos_order_id']
                )
                . ' · $'
                . number_format(
                    (float) $result['total'],
                    2
                )
            )
            ->send();
    }

    /** CIBER3H2B_GRACE_UI */
    public function cancellationGraceInfo(
        ComputerRentalSession $session
    ): array {
        $graceMinutes = max(
            0,
            (int) (
                $session->cancellation_grace_minutes
                ?? 5
            )
        );

        $elapsedSeconds = $session->started_at
            ? max(
                0,
                now()->timestamp
                - $session->started_at->timestamp
            )
            : 0;

        $graceSeconds =
            $graceMinutes * 60;

        $remainingSeconds = max(
            0,
            $graceSeconds - $elapsedSeconds
        );

        $consumptionCount =
            \App\Models\ComputerRentalSessionLine::query()
                ->where(
                    'computer_rental_session_id',
                    $session->id
                )
                ->count();

        return [
            'grace_minutes' =>
                $graceMinutes,
            'elapsed_seconds' =>
                $elapsedSeconds,
            'remaining_seconds' =>
                $remainingSeconds,
            'allowed' =>
                $session->status === 'active'
                && $graceMinutes > 0
                && $elapsedSeconds <= $graceSeconds
                && $consumptionCount === 0,
            'has_consumptions' =>
                $consumptionCount > 0,
        ];
    }

    public function getCancellingSessionProperty(): ?ComputerRentalSession
    {
        if (! $this->cancelSessionId) {
            return null;
        }

        return ComputerRentalSession::query()
            ->where(
                'company_id',
                $this->companyId()
            )
            ->where('status', 'active')
            ->with('station')
            ->find($this->cancelSessionId);
    }

    public function rentalStatusLabel(
        ?string $status
    ): string {
        return match ((string) $status) {
            'active' =>
                'En uso',
            'pending_pos' =>
                'Pendiente de enviar a PDV',
            'sent_to_pos' =>
                'Pendiente de cobro',
            'paid' =>
                'Pagado',
            'cancelled' =>
                'Cancelada sin cobro',
            'ticket_cancelled' =>
                'Ticket cancelado en PDV',
            'finished' =>
                'Finalizada',
            default =>
                $status
                    ? ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $status
                        )
                    )
                    : 'Sin estado',
        };
    }

    public function openCancel(int $sessionId): void
    {
        $this->cancelSessionId = $sessionId;
        $this->cancelReason = null;
    }

    public function cancelRental(): void
    {
        $this->authorizeComputerRentalOperation();
        $companyId = $this->companyId();

        $data = $this->validate([
            'cancelSessionId' => ['required', 'integer'],
            'cancelReason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $session = ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->findOrFail($data['cancelSessionId']);

        app(ComputerRentalSessionService::class)
            ->cancel(
                $session,
                $data['cancelReason'],
                auth()->id()
            );

        $this->reset([
            'cancelSessionId',
            'cancelReason',
        ]);

        Notification::make()
            ->success()
            ->title('Renta cancelada')
            ->send();
    }

    public function getConsumptionServiceProductsProperty()
    {
        $companyId = $this->companyId();

        if ($companyId <= 0) {
            return collect();
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->where('product_type', 'service')
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('available_in_pos', true)
            ->where('sale_price', '>', 0)
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    public function consumptionLinesForSession(int $sessionId)
    {
        return ComputerRentalSessionLine::query()
            ->where('company_id', $this->companyId())
            ->where('computer_rental_session_id', $sessionId)
            ->with('product')
            ->orderBy('id')
            ->get();
    }

    public function consumptionTotalForSession(int $sessionId): float
    {
        return round(
            (float) ComputerRentalSessionLine::query()
                ->where('company_id', $this->companyId())
                ->where('computer_rental_session_id', $sessionId)
                ->sum('total'),
            4
        );
    }

    /**
     * CIBER3D1_GROSS_PRICE
     *
     * products.sale_price está almacenado sin IVA.
     * Para cuenta abierta / PDV mostramos y guardamos precio final con IVA.
     */
    public function consumptionGrossPrice(Product $product): float
    {
        $netPrice = round(
            (float) ($product->sale_price ?? 0),
            4
        );

        $taxRate = (float) ($product->sale_tax_rate ?? 0.16);

        if ($taxRate > 1) {
            $taxRate = $taxRate / 100;
        }

        if ($taxRate < 0) {
            $taxRate = 0;
        }

        return round(
            $netPrice * (1 + $taxRate),
            4
        );
    }

    public function addConsumption(int $sessionId): void
    {
        $this->authorizeComputerRentalOperation();
        $companyId = $this->companyId();

        $session = ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->findOrFail($sessionId);

        $productId = (int) ($this->consumptionProductIds[$sessionId] ?? 0);
        $quantity = (float) ($this->consumptionQuantities[$sessionId] ?? 1);

        if ($productId <= 0) {
            Notification::make()
                ->danger()
                ->title('Selecciona un servicio')
                ->send();

            return;
        }

        if ($quantity <= 0) {
            Notification::make()
                ->danger()
                ->title('La cantidad debe ser mayor a cero')
                ->send();

            return;
        }

        $product = Product::query()
            ->where('company_id', $companyId)
            ->where('id', $productId)
            ->where('product_type', 'service')
            ->where('is_active', true)
            ->where('can_be_sold', true)
            ->where('available_in_pos', true)
            ->first();

        if (! $product) {
            Notification::make()
                ->danger()
                ->title('Servicio no válido')
                ->send();

            return;
        }

        /*
         * CIBER3D1:
         * sale_price es precio sin IVA.
         * unitPrice debe ser el precio final mostrado/cobrado.
         */
        $unitPrice = $this->consumptionGrossPrice($product);

        if ($unitPrice <= 0) {
            Notification::make()
                ->danger()
                ->title('El servicio no tiene precio de venta')
                ->send();

            return;
        }

        $taxRate = (float) ($product->sale_tax_rate ?? 0.16);

        if ($taxRate > 1) {
            $taxRate = $taxRate / 100;
        }

        if ($taxRate < 0) {
            $taxRate = 0;
        }

        $total = round($quantity * $unitPrice, 4);

        $subtotal = $taxRate > 0
            ? round($total / (1 + $taxRate), 4)
            : $total;

        $taxTotal = round($total - $subtotal, 4);

        /*
         * Si el mismo servicio ya está en la cuenta con el mismo
         * precio/impuesto, incrementamos cantidad en vez de duplicar.
         */
        $existing = ComputerRentalSessionLine::query()
            ->where('company_id', $companyId)
            ->where('computer_rental_session_id', $session->id)
            ->where('product_id', $product->id)
            ->where('unit_price', $unitPrice)
            ->where('tax_rate', $taxRate)
            ->first();

        if ($existing) {
            $newQuantity = round(
                (float) $existing->quantity + $quantity,
                4
            );

            $newTotal = round($newQuantity * $unitPrice, 4);

            $newSubtotal = $taxRate > 0
                ? round($newTotal / (1 + $taxRate), 4)
                : $newTotal;

            $existing->update([
                'quantity' => $newQuantity,
                'subtotal' => $newSubtotal,
                'tax_total' => round($newTotal - $newSubtotal, 4),
                'total' => $newTotal,
            ]);
        } else {
            ComputerRentalSessionLine::create([
                'company_id' => $companyId,
                'computer_rental_session_id' => $session->id,
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'created_by_user_id' => auth()->id(),
                'metadata' => [
                    'source' => 'computer_rental_open_account',
                    'product_reference' =>
                        $product->internal_reference
                        ?? $product->sku
                        ?? null,
                    'product_type' => $product->product_type,
                    'price_without_tax' => round(
                        (float) ($product->sale_price ?? 0),
                        4
                    ),
                    'price_with_tax' => $unitPrice,
                    'price_snapshot_at' => now()->toISOString(),
                ],
            ]);
        }

        $this->consumptionProductIds[$sessionId] = null;
        $this->consumptionQuantities[$sessionId] = 1;

        Notification::make()
            ->success()
            ->title('Consumo agregado')
            ->body(
                $product->name
                . ' · '
                . number_format($quantity, 2)
                . ' · $'
                . number_format($total, 2)
            )
            ->send();
    }

    public function beginRemoveConsumption(int $lineId): void
    {
        $line = ComputerRentalSessionLine::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($lineId);

        $session = ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($line->computer_rental_session_id);

        if ($session->status !== 'active') {
            Notification::make()
                ->danger()
                ->title('La cuenta ya no está abierta')
                ->send();

            return;
        }

        $this->removeConsumptionLineId = (int) $line->id;
    }

    public function cancelRemoveConsumption(): void
    {
        $this->removeConsumptionLineId = null;
    }

    public function confirmRemoveConsumption(): void
    {
        if (! $this->removeConsumptionLineId) {
            return;
        }

        $lineId = (int) $this->removeConsumptionLineId;

        $this->removeConsumptionLineId = null;

        $this->removeConsumption($lineId);
    }

    public function removeConsumption(int $lineId): void
    {
        $line = ComputerRentalSessionLine::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($lineId);

        $session = ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($line->computer_rental_session_id);

        if ($session->status !== 'active') {
            Notification::make()
                ->danger()
                ->title('La cuenta ya no está abierta')
                ->send();

            return;
        }

        $description = $line->description;

        $line->delete();

        Notification::make()
            ->success()
            ->title('Consumo eliminado')
            ->body($description)
            ->send();
    }

    public function beginEditRate(int $rateId): void
    {
        $rate = ComputerRentalRate::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($rateId);

        /*
         * Evitar tener los dos modales abiertos a la vez.
         */
        if (method_exists($this, 'cancelEditStation')) {
            $this->cancelEditStation();
        }

        $this->editingRateId = (int) $rate->id;
        $this->editRateName = (string) $rate->name;
        $this->editRateBillingMode =
            (string) ($rate->billing_mode ?? 'open');

        $this->editRateHourlyRate =
            $rate->hourly_rate !== null
                ? (float) $rate->hourly_rate
                : null;

        $this->editRateMinimumMinutes =
            (int) ($rate->minimum_minutes ?? 1);

        $this->editRateIncrementMinutes =
            (int) ($rate->billing_increment_minutes ?? 1);

        $this->editRateCancellationGraceMinutes =
            (int) ($rate->cancellation_grace_minutes ?? 5);

        $this->editRatePrepaidMinutes =
            $rate->prepaid_minutes !== null
                ? (int) $rate->prepaid_minutes
                : null;

        $this->editRatePrepaidPrice =
            $rate->prepaid_price !== null
                ? (float) $rate->prepaid_price
                : null;

        $this->editRateTaxRate =
            (float) ($rate->tax_rate ?? 0.16);

        $this->editRateProductId =
            $rate->product_id
                ? (int) $rate->product_id
                : null;

        $this->editRateIsActive =
            (bool) $rate->is_active;

        $this->resetValidation();
    }

    public function cancelEditRate(): void
    {
        $this->reset([
            'editingRateId',
            'editRateName',
            'editRateHourlyRate',
            'editRatePrepaidMinutes',
            'editRatePrepaidPrice',
            'editRateProductId',
        ]);

        $this->editRateBillingMode = 'open';
        $this->editRateMinimumMinutes = 1;
        $this->editRateIncrementMinutes = 1;
        $this->editRateCancellationGraceMinutes = 5;
        $this->editRateTaxRate = 0.16;
        $this->editRateIsActive = true;

        $this->resetValidation();
    }

    public function saveEditRate(): void
    {
        $this->authorizeComputerRentalManagement();
        $companyId = $this->companyId();

        abort_if($companyId <= 0, 422, 'Empresa no válida.');

        if (! $this->editingRateId) {
            return;
        }

        $rate = ComputerRentalRate::query()
            ->where('company_id', $companyId)
            ->findOrFail($this->editingRateId);

        $data = $this->validate([
            'editRateName' => [
                'required',
                'string',
                'max:160',
            ],
            'editRateBillingMode' => [
                'required',
                'in:open,prepaid',
            ],
            'editRateHourlyRate' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'editRateMinimumMinutes' => [
                'required',
                'integer',
                'min:1',
            ],
            'editRateIncrementMinutes' => [
                'required',
                'integer',
                'min:1',
            ],
            'editRateCancellationGraceMinutes' => [
                'required',
                'integer',
                'min:0',
                'max:60',
            ],
            'editRatePrepaidMinutes' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'editRatePrepaidPrice' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'editRateTaxRate' => [
                'required',
                'numeric',
                'min:0',
                'max:1',
            ],
            'editRateProductId' => [
                'nullable',
                'integer',
            ],
            'editRateIsActive' => [
                'boolean',
            ],
        ]);

        if (
            $data['editRateBillingMode'] === 'open'
            && (float) ($data['editRateHourlyRate'] ?? 0) <= 0
        ) {
            throw ValidationException::withMessages([
                'editRateHourlyRate' =>
                    'La tarifa por hora debe ser mayor a cero.',
            ]);
        }

        if ($data['editRateBillingMode'] === 'prepaid') {
            if (
                (int) ($data['editRatePrepaidMinutes'] ?? 0) <= 0
            ) {
                throw ValidationException::withMessages([
                    'editRatePrepaidMinutes' =>
                        'Indica los minutos del paquete.',
                ]);
            }

            if (
                (float) ($data['editRatePrepaidPrice'] ?? 0) <= 0
            ) {
                throw ValidationException::withMessages([
                    'editRatePrepaidPrice' =>
                        'Indica el precio del paquete.',
                ]);
            }
        }

        $productId =
            ! empty($data['editRateProductId'])
                ? (int) $data['editRateProductId']
                : null;

        if ($productId) {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->where('id', $productId)
                ->where('product_type', 'service')
                ->where('is_active', true)
                ->where('can_be_sold', true)
                ->first();

            if (! $product) {
                throw ValidationException::withMessages([
                    'editRateProductId' =>
                        'El producto debe ser un servicio activo y vendible de esta empresa.',
                ]);
            }
        }

        /*
         * No dejar una PC activa apuntando a una tarifa inactiva.
         */
        if (! (bool) $data['editRateIsActive']) {
            $assignedStations =
                ComputerRentalStation::query()
                    ->where('company_id', $companyId)
                    ->where('default_rate_id', $rate->id)
                    ->where('is_active', true)
                    ->count();

            if ($assignedStations > 0) {
                throw ValidationException::withMessages([
                    'editRateIsActive' =>
                        "No puedes desactivar esta tarifa porque está asignada a {$assignedStations} PC(s) activa(s). Reasigna primero esas estaciones.",
                ]);
            }
        }

        $rate->update([
            'product_id' => $productId,
            'name' => trim(
                (string) $data['editRateName']
            ),
            'billing_mode' =>
                (string) $data['editRateBillingMode'],

            'hourly_rate' =>
                $data['editRateBillingMode'] === 'open'
                    ? (float) $data['editRateHourlyRate']
                    : 0,

            'minimum_minutes' =>
                (int) $data['editRateMinimumMinutes'],

            'billing_increment_minutes' =>
                (int) $data['editRateIncrementMinutes'],

            'cancellation_grace_minutes' =>
                (int) $data['editRateCancellationGraceMinutes'],

            'prepaid_minutes' =>
                $data['editRateBillingMode'] === 'prepaid'
                    ? (int) $data['editRatePrepaidMinutes']
                    : null,

            'prepaid_price' =>
                $data['editRateBillingMode'] === 'prepaid'
                    ? (float) $data['editRatePrepaidPrice']
                    : null,

            'tax_rate' =>
                (float) $data['editRateTaxRate'],

            'is_active' =>
                (bool) $data['editRateIsActive'],
        ]);

        Notification::make()
            ->success()
            ->title('Tarifa actualizada')
            ->body($rate->name)
            ->send();

        $this->cancelEditRate();
    }

    public function beginEditStation(int $stationId): void
    {
        $station = ComputerRentalStation::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($stationId);

        $this->editingStationId = (int) $station->id;
        $this->editStationCode = (string) $station->code;
        $this->editStationName = (string) $station->name;

        $this->editStationPosPointId =
            $station->pos_point_id
                ? (int) $station->pos_point_id
                : null;

        $this->editStationDefaultRateId =
            $station->default_rate_id
                ? (int) $station->default_rate_id
                : null;

        $this->editStationStatus =
            (string) ($station->status ?? 'available');

        $this->editStationIsActive =
            (bool) $station->is_active;

        $this->editStationNotes =
            $station->notes !== null
                ? (string) $station->notes
                : null;

        $this->resetValidation();
    }

    public function cancelEditStation(): void
    {
        $this->reset([
            'editingStationId',
            'editStationCode',
            'editStationName',
            'editStationPosPointId',
            'editStationDefaultRateId',
            'editStationNotes',
        ]);

        $this->editStationStatus = 'available';
        $this->editStationIsActive = true;

        $this->resetValidation();
    }

    public function editingStationHasActiveRental(): bool
    {
        if (! $this->editingStationId) {
            return false;
        }

        return ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->where('station_id', $this->editingStationId)
            ->where('status', 'active')
            ->exists();
    }

    public function saveEditStation(): void
    {
        $this->authorizeComputerRentalManagement();
        $companyId = $this->companyId();

        abort_if($companyId <= 0, 422, 'Empresa no válida.');

        if (! $this->editingStationId) {
            return;
        }

        $station = ComputerRentalStation::query()
            ->where('company_id', $companyId)
            ->findOrFail($this->editingStationId);

        $data = $this->validate([
            'editStationCode' => [
                'required',
                'string',
                'max:80',
            ],
            'editStationName' => [
                'required',
                'string',
                'max:160',
            ],
            'editStationPosPointId' => [
                'nullable',
                'integer',
            ],
            'editStationDefaultRateId' => [
                'nullable',
                'integer',
            ],
            'editStationStatus' => [
                'required',
                'in:available,reserved,maintenance,offline,in_use',
            ],
            'editStationIsActive' => [
                'boolean',
            ],
            'editStationNotes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $code = strtoupper(
            trim((string) $data['editStationCode'])
        );

        $duplicate = ComputerRentalStation::query()
            ->where('company_id', $companyId)
            ->whereRaw(
                'LOWER(code) = ?',
                [mb_strtolower($code)]
            )
            ->whereKeyNot($station->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'editStationCode' =>
                    'Ya existe otra estación con ese código.',
            ]);
        }

        $rateId =
            ! empty($data['editStationDefaultRateId'])
                ? (int) $data['editStationDefaultRateId']
                : null;

        if ($rateId) {
            $rateExists = ComputerRentalRate::query()
                ->where('company_id', $companyId)
                ->where('id', $rateId)
                ->where('is_active', true)
                ->exists();

            if (! $rateExists) {
                throw ValidationException::withMessages([
                    'editStationDefaultRateId' =>
                        'La tarifa seleccionada no es válida para esta empresa.',
                ]);
            }
        }

        $posPointId =
            ! empty($data['editStationPosPointId'])
                ? (int) $data['editStationPosPointId']
                : null;

        if ($posPointId) {
            $posExists = PosPoint::query()
                ->where('company_id', $companyId)
                ->where('id', $posPointId)
                ->where('status', 'active')
                ->exists();

            if (! $posExists) {
                throw ValidationException::withMessages([
                    'editStationPosPointId' =>
                        'El punto de venta seleccionado no es válido para esta empresa.',
                ]);
            }
        }

        $activeRental = ComputerRentalSession::query()
            ->where('company_id', $companyId)
            ->where('station_id', $station->id)
            ->where('status', 'active')
            ->exists();

        if ($activeRental) {
            /*
             * Durante una renta activa no permitimos mover la PC
             * a otro PDV, cambiar su estado operativo ni desactivarla.
             */
            $originalPosPointId =
                $station->pos_point_id
                    ? (int) $station->pos_point_id
                    : null;

            if ($posPointId !== $originalPosPointId) {
                throw ValidationException::withMessages([
                    'editStationPosPointId' =>
                        'No puedes cambiar el Punto de Venta mientras esta PC tiene una renta activa.',
                ]);
            }

            if (
                (string) $data['editStationStatus'] !== 'in_use'
            ) {
                throw ValidationException::withMessages([
                    'editStationStatus' =>
                        'La PC debe permanecer En uso mientras exista una renta activa.',
                ]);
            }

            if (! (bool) $data['editStationIsActive']) {
                throw ValidationException::withMessages([
                    'editStationIsActive' =>
                        'No puedes desactivar una PC mientras tiene una renta activa.',
                ]);
            }
        } else {
            /*
             * in_use solo lo controla el flujo de renta.
             * No debe asignarse manualmente.
             */
            if (
                (string) $data['editStationStatus'] === 'in_use'
            ) {
                throw ValidationException::withMessages([
                    'editStationStatus' =>
                        'El estado En uso se asigna automáticamente al iniciar una renta.',
                ]);
            }

            /*
             * Si se desactiva la estación, la dejamos offline.
             */
            if (! (bool) $data['editStationIsActive']) {
                $data['editStationStatus'] = 'offline';
            }
        }

        $station->update([
            'code' => $code,
            'name' => trim(
                (string) $data['editStationName']
            ),
            'pos_point_id' => $posPointId,
            'default_rate_id' => $rateId,
            'status' => (string) $data['editStationStatus'],
            'is_active' => (bool) $data['editStationIsActive'],
            'notes' => trim(
                (string) ($data['editStationNotes'] ?? '')
            ) ?: null,
        ]);

        Notification::make()
            ->success()
            ->title('PC actualizada')
            ->body(
                $station->code
                . ' · '
                . $station->name
            )
            ->send();

        $this->cancelEditStation();
    }

    public function activeSessionForStation(int $stationId): ?ComputerRentalSession
    {
        return ComputerRentalSession::query()
            ->where('company_id', $this->companyId())
            ->where('station_id', $stationId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();
    }

    public function liveEstimate(ComputerRentalSession $session): array
    {
        $snapshotRate = new ComputerRentalRate([
            'billing_mode' => $session->billing_mode,
            'hourly_rate' => $session->hourly_rate,
            'minimum_minutes' => $session->minimum_minutes,
            'billing_increment_minutes' => $session->billing_increment_minutes,
            'prepaid_minutes' => $session->prepaid_minutes,
            'prepaid_price' => $session->prepaid_price,
            'tax_rate' => $session->tax_rate,
        ]);

        return app(ComputerRentalCalculator::class)
            ->calculate(
                $snapshotRate,
                $session->started_at,
                now()
            );
    }
}

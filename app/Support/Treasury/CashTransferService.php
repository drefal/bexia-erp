<?php

namespace App\Support\Treasury;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashTransferService
{
    public function createRequest(array $data): object
    {
        return DB::transaction(function () use ($data): object {
            $companyId = (int) ($data['company_id'] ?? 0);
            $amount = round((float) ($data['amount'] ?? 0), 6);

            if ($companyId <= 0) {
                throw new RuntimeException('La empresa es obligatoria para la solicitud de efectivo.');
            }

            if ($amount <= 0) {
                throw new RuntimeException('El monto de la solicitud debe ser mayor a cero.');
            }

            $sourceAccountId = $data['source_treasury_account_id'] ?? null;
            $destinationAccountId = $data['destination_treasury_account_id'] ?? null;

            if (! $sourceAccountId && ! $destinationAccountId) {
                throw new RuntimeException('La solicitud debe tener al menos una caja origen o destino.');
            }

            if ($sourceAccountId) {
                $this->assertAccountBelongsToCompany((int) $sourceAccountId, $companyId);
            }

            if ($destinationAccountId) {
                $this->assertAccountBelongsToCompany((int) $destinationAccountId, $companyId);
            }

            $now = now();
            $status = (string) ($data['status'] ?? 'requested');

            $id = DB::table('treasury_cash_transfer_requests')->insertGetId([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'pos_point_id' => $data['pos_point_id'] ?? null,
                'pos_session_id' => $data['pos_session_id'] ?? null,
                'pos_cash_movement_id' => $data['pos_cash_movement_id'] ?? null,
                'source_treasury_account_id' => $sourceAccountId,
                'destination_treasury_account_id' => $destinationAccountId,
                'number' => $data['number'] ?? $this->nextNumber($companyId),
                'type' => $data['type'] ?? 'transfer',
                'status' => $status,
                'amount' => $amount,
                'currency_code' => $data['currency_code'] ?? 'MXN',
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'requested_by_user_id' => $data['requested_by_user_id'] ?? null,
                'requested_at' => $data['requested_at'] ?? $now,
                'metadata' => isset($data['metadata'])
                    ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $request = DB::table('treasury_cash_transfer_requests')->where('id', $id)->first();

            $this->logAction($request, 'created', null, $status, $data['requested_by_user_id'] ?? null, $data['notes'] ?? null);

            return $request;
        });
    }

    public function approve(int $requestId, ?int $userId = null, ?string $notes = null): object
    {
        return $this->changeStatus($requestId, 'approved', $userId, $notes, [
            'approved_by_user_id' => $userId,
            'approved_at' => now(),
        ], 'approve');
    }

    public function reject(int $requestId, ?int $userId = null, ?string $reason = null): object
    {
        return $this->changeStatus($requestId, 'rejected', $userId, $reason, [
            'rejected_by_user_id' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], 'reject');
    }

    public function markDelivered(int $requestId, ?int $userId = null, ?string $notes = null): object
    {
        return $this->changeStatus($requestId, 'delivered', $userId, $notes, [
            'delivered_by_user_id' => $userId,
            'delivered_at' => now(),
        ], 'deliver');
    }

    public function markReceived(int $requestId, ?int $userId = null, ?string $notes = null): object
    {
        return $this->changeStatus($requestId, 'received', $userId, $notes, [
            'received_by_user_id' => $userId,
            'received_at' => now(),
        ], 'receive');
    }

    public function cancel(int $requestId, ?int $userId = null, ?string $notes = null): object
    {
        return $this->changeStatus($requestId, 'cancelled', $userId, $notes, [
            'cancelled_at' => now(),
        ], 'cancel');
    }

    public function postApprovedTransfer(int $requestId, ?int $userId = null): array
    {
        return DB::transaction(function () use ($requestId, $userId): array {
            $request = DB::table('treasury_cash_transfer_requests')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if (! $request) {
                throw new RuntimeException('No se encontro la solicitud de efectivo.');
            }

            if (! in_array((string) $request->status, ['approved', 'delivered', 'received'], true)) {
                throw new RuntimeException('Solo se pueden contabilizar solicitudes aprobadas, entregadas o recibidas.');
            }

            if ($request->posted_at) {
                throw new RuntimeException('La solicitud ya fue contabilizada en Tesoreria.');
            }

            $amount = round((float) $request->amount, 6);

            if ($amount <= 0) {
                throw new RuntimeException('El monto debe ser mayor a cero.');
            }

            $sourceAccount = null;
            $destinationAccount = null;

            if ($request->source_treasury_account_id) {
                $sourceAccount = DB::table('treasury_accounts')
                    ->where('id', $request->source_treasury_account_id)
                    ->lockForUpdate()
                    ->first();

                if (! $sourceAccount) {
                    throw new RuntimeException('No se encontro la caja origen.');
                }

                if ((int) $sourceAccount->company_id !== (int) $request->company_id) {
                    throw new RuntimeException('La caja origen no pertenece a la empresa de la solicitud.');
                }

                $sourceBalance = round((float) $sourceAccount->current_balance, 6);

                if ($sourceBalance + 0.000001 < $amount) {
                    throw new RuntimeException('La caja origen no tiene saldo suficiente para el traspaso.');
                }
            }

            if ($request->destination_treasury_account_id) {
                $destinationAccount = DB::table('treasury_accounts')
                    ->where('id', $request->destination_treasury_account_id)
                    ->lockForUpdate()
                    ->first();

                if (! $destinationAccount) {
                    throw new RuntimeException('No se encontro la caja destino.');
                }

                if ((int) $destinationAccount->company_id !== (int) $request->company_id) {
                    throw new RuntimeException('La caja destino no pertenece a la empresa de la solicitud.');
                }
            }

            $now = now();
            $outflowMovementId = null;
            $inflowMovementId = null;

            if ($sourceAccount) {
                $outflowMovementId = DB::table('treasury_movements')->insertGetId([
                    'company_id' => $request->company_id,
                    'treasury_account_id' => $sourceAccount->id,
                    'source_treasury_account_id' => $sourceAccount->id,
                    'destination_treasury_account_id' => $destinationAccount?->id,
                    'pos_cash_movement_id' => $request->pos_cash_movement_id,
                    'pos_session_id' => $request->pos_session_id,
                    'pos_point_id' => $request->pos_point_id,
                    'branch_id' => $request->branch_id,
                    'warehouse_id' => $request->warehouse_id,
                    'type' => 'outflow',
                    'source_type' => 'treasury_cash_transfer_request',
                    'source_id' => $request->id,
                    'movement_date' => $now->toDateString(),
                    'amount' => $amount,
                    'currency_code' => $request->currency_code,
                    'reference' => $request->number,
                    'description' => 'Salida por traspaso de efectivo: ' . ($request->reason ?: $request->type),
                    'status' => 'posted',
                    'posted_at' => $now,
                    'created_by_user_id' => $userId ?? $request->approved_by_user_id ?? $request->requested_by_user_id,
                    'requested_by_user_id' => $request->requested_by_user_id,
                    'approved_by_user_id' => $request->approved_by_user_id,
                    'approved_at' => $request->approved_at,
                    'metadata' => json_encode([
                        'transfer_request_id' => $request->id,
                        'transfer_request_type' => $request->type,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('treasury_accounts')
                    ->where('id', $sourceAccount->id)
                    ->update([
                        'current_balance' => round(((float) $sourceAccount->current_balance) - $amount, 6),
                        'updated_at' => $now,
                    ]);
            }

            if ($destinationAccount) {
                $inflowMovementId = DB::table('treasury_movements')->insertGetId([
                    'company_id' => $request->company_id,
                    'treasury_account_id' => $destinationAccount->id,
                    'source_treasury_account_id' => $sourceAccount?->id,
                    'destination_treasury_account_id' => $destinationAccount->id,
                    'pos_cash_movement_id' => $request->pos_cash_movement_id,
                    'pos_session_id' => $request->pos_session_id,
                    'pos_point_id' => $request->pos_point_id,
                    'branch_id' => $request->branch_id,
                    'warehouse_id' => $request->warehouse_id,
                    'type' => 'inflow',
                    'source_type' => 'treasury_cash_transfer_request',
                    'source_id' => $request->id,
                    'movement_date' => $now->toDateString(),
                    'amount' => $amount,
                    'currency_code' => $request->currency_code,
                    'reference' => $request->number,
                    'description' => 'Entrada por traspaso de efectivo: ' . ($request->reason ?: $request->type),
                    'status' => 'posted',
                    'posted_at' => $now,
                    'created_by_user_id' => $userId ?? $request->approved_by_user_id ?? $request->requested_by_user_id,
                    'requested_by_user_id' => $request->requested_by_user_id,
                    'approved_by_user_id' => $request->approved_by_user_id,
                    'approved_at' => $request->approved_at,
                    'metadata' => json_encode([
                        'transfer_request_id' => $request->id,
                        'transfer_request_type' => $request->type,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('treasury_accounts')
                    ->where('id', $destinationAccount->id)
                    ->update([
                        'current_balance' => round(((float) $destinationAccount->current_balance) + $amount, 6),
                        'updated_at' => $now,
                    ]);
            }

            DB::table('treasury_cash_transfer_requests')
                ->where('id', $request->id)
                ->update([
                    'status' => 'posted',
                    'posted_at' => $now,
                    'outflow_treasury_movement_id' => $outflowMovementId,
                    'inflow_treasury_movement_id' => $inflowMovementId,
                    'updated_at' => $now,
                ]);

            if ($request->pos_cash_movement_id) {
                DB::table('pos_cash_movements')
                    ->where('id', $request->pos_cash_movement_id)
                    ->update([
                        'treasury_transfer_request_id' => $request->id,
                        'treasury_movement_id' => $outflowMovementId ?: $inflowMovementId,
                        'treasury_status' => 'posted',
                        'approved_by_user_id' => $userId ?? $request->approved_by_user_id,
                        'approved_at' => $now,
                        'updated_at' => $now,
                    ]);
            }

            $updated = DB::table('treasury_cash_transfer_requests')->where('id', $request->id)->first();
            $this->logAction($updated, 'post', $request->status, 'posted', $userId, 'Traspaso contabilizado en Tesoreria.');

            return [
                'request_id' => $request->id,
                'outflow_movement_id' => $outflowMovementId,
                'inflow_movement_id' => $inflowMovementId,
            ];
        });
    }

    private function changeStatus(int $requestId, string $newStatus, ?int $userId, ?string $notes, array $extra, string $action): object
    {
        return DB::transaction(function () use ($requestId, $newStatus, $userId, $notes, $extra, $action): object {
            $request = DB::table('treasury_cash_transfer_requests')
                ->where('id', $requestId)
                ->lockForUpdate()
                ->first();

            if (! $request) {
                throw new RuntimeException('No se encontro la solicitud de efectivo.');
            }

            if ($request->posted_at && ! in_array($newStatus, ['received'], true)) {
                throw new RuntimeException('La solicitud ya fue contabilizada y no puede modificarse a ese estado.');
            }

            $oldStatus = (string) $request->status;

            DB::table('treasury_cash_transfer_requests')
                ->where('id', $request->id)
                ->update(array_merge($extra, [
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]));

            $updated = DB::table('treasury_cash_transfer_requests')->where('id', $request->id)->first();

            $this->logAction($updated, $action, $oldStatus, $newStatus, $userId, $notes);

            return $updated;
        });
    }

    private function assertAccountBelongsToCompany(int $accountId, int $companyId): void
    {
        $account = DB::table('treasury_accounts')->where('id', $accountId)->first();

        if (! $account) {
            throw new RuntimeException('La cuenta/caja de Tesoreria no existe.');
        }

        if ((int) $account->company_id !== $companyId) {
            throw new RuntimeException('La cuenta/caja no pertenece a la empresa indicada.');
        }

        if (property_exists($account, 'is_active') && ! $account->is_active) {
            throw new RuntimeException('La cuenta/caja de Tesoreria no esta activa.');
        }
    }

    private function nextNumber(int $companyId): string
    {
        $prefix = 'TEF-' . now()->format('Ymd') . '-';

        $count = DB::table('treasury_cash_transfer_requests')
            ->where('company_id', $companyId)
            ->whereDate('created_at', now()->toDateString())
            ->count() + 1;

        return $prefix . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

    private function logAction(object $request, string $action, ?string $fromStatus, ?string $toStatus, ?int $userId, ?string $notes): void
    {
        $payload = [
            'company_id' => $request->company_id,
            'treasury_cash_transfer_request_id' => $request->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'user_id' => $userId,
            'signer_name' => $userId ? ('user_id:' . $userId) : null,
            'signature_hash' => hash('sha256', implode('|', [
                $request->id,
                $request->company_id,
                $action,
                $fromStatus ?? '',
                $toStatus ?? '',
                $userId ?? '',
                now()->toIso8601String(),
            ])),
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('treasury_cash_transfer_approval_logs')->insert($payload);
    }
}

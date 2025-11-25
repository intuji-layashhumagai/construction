<?php

namespace App\Actions\Sync;

use App\Services\Sync\SyncOperation;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SyncTransactionAction
{
    private static $dbTransaction;

    private static array $operations = [];

    private static bool $committed = false;

    public static function begin(): void
    {
        self::$dbTransaction = DB::beginTransaction();
        self::$operations = [];
        self::$committed = false;
    }

    public static function addOperation(SyncOperation $operation): void
    {
        if (self::$committed) {
            Log::error('Cannot add operations to committed transaction');
        }

        self::$operations[] = $operation;
    }

    public static function commit(): void
    {
        if (self::$committed) {
            Log::error('Transaction already committed');
        }

        try {
            // Pre-commit validation
            foreach (self::$operations as $operation) {
                $operation->validate();
            }

            // Execute all operations
            foreach (self::$operations as $operation) {
                $operation->execute();
            }

            // Commit database transaction
            self::$dbTransaction->commit();
            self::$committed = true;

        } catch (Exception $e) {
            self::rollback();
            Log::error('Transaction failed: '.$e->getMessage());
        }
    }

    public static function rollback(): void
    {
        if (self::$committed) {
            Log::warning('Attempting to rollback already committed transaction');

            return;
        }

        try {
            // Reverse operations in reverse order
            foreach (array_reverse(self::$operations) as $operation) {
                try {
                    $operation->rollback();
                } catch (Exception $rollbackException) {
                    Log::error('Rollback operation failed', [
                        'operation' => get_class($operation),
                        'error' => $rollbackException->getMessage(),
                    ]);
                }
            }
        } catch (Exception $rollbackException) {
            Log::error('Transaction rollback failed', [
                'error' => $rollbackException->getMessage(),
            ]);
        }

        // Rollback database transaction
        self::$dbTransaction->rollBack();
    }

    public static function isCommitted(): bool
    {
        return self::$committed;
    }

    public static function getOperationCount(): int
    {
        return count(self::$operations);
    }
}

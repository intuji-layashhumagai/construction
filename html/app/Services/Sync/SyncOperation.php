<?php

namespace App\Services\Sync;

interface SyncOperation
{
    public function validate(): void;

    public function execute(): void;

    public function rollback(): void;

    public function getDescription(): string;
}

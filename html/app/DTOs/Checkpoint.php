<?php

namespace App\DTOs;

use DateTime;

class Checkpoint
{
    public string $checkpointId;

    public int $sequenceNumber;

    public array $processedItems = [];

    public array $vectorClockState;

    public int $bytesTransferred;

    public DateTime $timestamp;

    public string $integrityHash;

    public function __construct(array $data = [])
    {
        $this->checkpointId = $data['checkpointId'] ?? $this->generateId();
        $this->sequenceNumber = $data['sequenceNumber'] ?? 0;
        $this->processedItems = $data['processedItems'] ?? [];
        $this->vectorClockState = $data['vectorClockState'] ?? [];
        $this->bytesTransferred = $data['bytesTransferred'] ?? 0;
        $this->timestamp = $data['timestamp'] ?? now();
        $this->integrityHash = $data['integrityHash'] ?? '';
    }

    private function generateId(): string
    {
        return 'cp_'.now()->format('Ymd_His').'_'.substr(md5(uniqid()), 0, 8);
    }

    public function toArray(): array
    {
        return [
            'checkpointId' => $this->checkpointId,
            'sequenceNumber' => $this->sequenceNumber,
            'processedItems' => $this->processedItems,
            'vectorClockState' => $this->vectorClockState,
            'bytesTransferred' => $this->bytesTransferred,
            'timestamp' => $this->timestamp->format('c'),
            'integrityHash' => $this->integrityHash,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'checkpointId' => $data['checkpointId'],
            'sequenceNumber' => $data['sequenceNumber'] ?? 0,
            'processedItems' => $data['processedItems'] ?? [],
            'vectorClockState' => $data['vectorClockState'] ?? [],
            'bytesTransferred' => $data['bytesTransferred'] ?? 0,
            'timestamp' => isset($data['timestamp']) ? new DateTime($data['timestamp']) : now(),
            'integrityHash' => $data['integrityHash'] ?? '',
        ]);
    }
}

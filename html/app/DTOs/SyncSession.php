<?php

namespace App\DTOs;

use App\Enums\SyncDirection;
use App\Enums\SyncPhase;
use DateTime;

class SyncSession
{
    public string $id;

    public string $deviceId;

    public SyncDirection $direction;

    public SyncPhase $phase;

    public ?Checkpoint $lastCheckpoint;

    public int $bytesTransferred = 0;

    public array $processedItems = [];

    public array $currentVectorClock = [];

    public int $sequenceNumber = 0;

    public ?string $deviceType = null;

    public function __construct(array $config = [])
    {
        $this->id = $config['sessionId'] ?? $this->generateSessionId();
        $this->deviceId = $config['deviceId'] ?? '';
        $this->direction = $config['direction'] instanceof SyncDirection
            ? $config['direction']
            : match (strtoupper($config['direction'] ?? 'BIDIRECTIONAL')) {
                'UPLOAD' => SyncDirection::UPLOAD,
                'DOWNLOAD' => SyncDirection::DOWNLOAD,
                'BIDIRECTIONAL' => SyncDirection::BIDIRECTIONAL,
                default => SyncDirection::BIDIRECTIONAL,
            };
        $this->phase = $config['phase'] ?? ($config['status'] ? SyncPhase::tryFrom($config['status']) ?? SyncPhase::HANDSHAKE : SyncPhase::HANDSHAKE);
        $this->lastCheckpoint = $config['lastCheckpoint'] ?? null;

        $this->bytesTransferred = $config['bytesTransferred'] ?? 0;
        $this->currentVectorClock = $config['vector_clock_state'] ?? [];
        $this->sequenceNumber = $config['sequenceNumber'] ?? 0;
        $this->deviceType = $config['deviceType'] ?? null;
    }

    private function generateSessionId(): string
    {
        return 'sync_'.now()->format('Ymd_His').'_'.substr(md5(uniqid()), 0, 8);
    }

    public function advancePhase(SyncPhase $newPhase): void
    {
        $this->phase = $newPhase;
        $this->sequenceNumber++;
    }

    public function addProcessedItem(SyncItem $item): void
    {
        $this->processedItems[] = $item->id;
    }

    public function updateBytesTransferred(int $bytes): void
    {
        $this->bytesTransferred += $bytes;
    }

    public function toArray(): array
    {
        return [
            'sessionId' => $this->id,
            'deviceId' => $this->deviceId,
            'direction' => $this->direction->value,
            'phase' => $this->phase->value,
            'lastCheckpoint' => $this->lastCheckpoint?->toArray(),
            'bytesTransferred' => $this->bytesTransferred,
            'processedItems' => $this->processedItems,
            'currentVectorClock' => $this->currentVectorClock,
            'sequenceNumber' => $this->sequenceNumber,
            'deviceType' => $this->deviceType,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self([
            'id' => $data['id'],
            'deviceId' => $data['device_id'],
            'direction' => match ($data['direction']) {
                'UPLOAD' => SyncDirection::UPLOAD,
                'DOWNLOAD' => SyncDirection::DOWNLOAD,
                default => SyncDirection::BIDIRECTIONAL,
            },
            'phase' => isset($data['phase']) ? match ($data['phase']) {
                'HANDSHAKE' => SyncPhase::HANDSHAKE,
                'DISCOVERY' => SyncPhase::DISCOVERY,
                'TRANSFER' => SyncPhase::TRANSFER,
                'VALIDATION' => SyncPhase::VALIDATION,
                'PROCESSING' => SyncPhase::PROCESSING,
                'COMPLETE' => SyncPhase::COMPLETE,
                'RESUME' => SyncPhase::RESUME,
                default => SyncPhase::HANDSHAKE,
            } : match ($data['status'] ?? 'handshake') {
                'handshake' => SyncPhase::HANDSHAKE,
                'discovery' => SyncPhase::DISCOVERY,
                'transfer' => SyncPhase::TRANSFER,
                'validation' => SyncPhase::VALIDATION,
                'processing' => SyncPhase::PROCESSING,
                'complete' => SyncPhase::COMPLETE,
                'resume' => SyncPhase::RESUME,
                default => SyncPhase::HANDSHAKE,
            },
            'lastCheckpoint' => isset($data['lastCheckpoint']) ? Checkpoint::fromArray($data['lastCheckpoint']) : null,
            'startTime' => new DateTime($data['start_time']),
            'bytesTransferred' => $data['bytes_transferred'],
            'currentVectorClock' => $data['vector_clock_state'],

        ]);
    }
}

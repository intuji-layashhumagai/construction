<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Generate offline context containing validation schema and business rules for worker/device
 */
final class GenerateOfflineContextAction
{
    /**
     * Generate offline context containing validation schema and business rules
     */
    public static function handle(): array
    {
        return [
            'valid_entity_type' => self::getAllowedEntityType(),
            'business_rules' => self::getBusinessRules(),
            'generated_at' => now()->toISOString(),
            'valid_until' => now()->addDays(30)->toISOString(),
        ];
    }

    /**
     * Get cached offline context with automatic cache management
     */
    public static function getCached(): array
    {
        return Cache::remember('offline_context', 3600, function () {
            return self::handle();
        });
    }

    /**
     * Invalidate the cached offline context (call when business rules change)
     */
    public static function invalidateCache(): bool
    {
        return Cache::forget('offline_context');
    }

    /**
     * Force refresh the cached offline context
     */
    public static function refreshCache(): array
    {
        self::invalidateCache();
        return self::getCached();
    }

    /**
     * Get business rules for offline operation using efficient JSONB aggregation query
     */
    private static function getBusinessRules(): array
    {
        // Use a single efficient JSONB query to aggregate all system business rules
        // This eliminates PHP loops and uses PostgreSQL's native JSONB functions
        $aggregatedRules = DB::selectOne('
            SELECT jsonb_object_agg(expanded.key, expanded.value) as business_rules
            FROM (
                SELECT
                    jsonb_object_keys(r.actions) as key,
                    r.actions->jsonb_object_keys(r.actions) as value
                FROM rules r
                WHERE r.entity_type IS NULL
                    AND r.event_type IS NULL
                    AND r.is_active = true
                    AND (r.effective_from IS NULL OR r.effective_from <= NOW())
                    AND (r.effective_until IS NULL OR r.effective_until >= NOW())
                ORDER BY r.priority
            ) as expanded
        ');

        $businessRules = [];

        // Extract the aggregated business rules from JSONB result
        if ($aggregatedRules && $aggregatedRules->business_rules) {
            $businessRules = json_decode($aggregatedRules->business_rules, true);
        }

        return $businessRules;
    }

    private static function getAllowedEntityType(): array
    {
        // Optimized single query using JSON aggregation to avoid nested map operations
        $entityTypes = DB::select('
            SELECT
                et.entity_type,
                et.id as entity_id,
                et.name,
                et.description,
                COALESCE(
                    json_agg(
                        json_build_object(
                            \'event_type\', evt.event_type,
                            \'name\', evt.name,
                            \'description\', evt.description,
                            \'is_initial_event\', eet.is_initial_event,
                            \'requires_approval\', eet.requires_approval,
                            \'priority\', eet.priority
                        ) ORDER BY eet.priority
                    ) FILTER (WHERE eet.id IS NOT NULL),
                    \'[]\'::json
                ) as allowed_events
            FROM entity_types et
            LEFT JOIN entity_event_types eet ON et.id = eet.entity_type_id
            LEFT JOIN event_types evt ON eet.event_type_id = evt.id
            WHERE et.is_active = true
            GROUP BY et.id, et.entity_type, et.name, et.description
            ORDER BY et.entity_type
        ');

        // Transform to expected array format
        return array_map(function ($entityType) {
            return [
                'entity_id' => $entityType->entity_id,
                'entity_type' => $entityType->entity_type,
                'name' => $entityType->name,
                'description' => $entityType->description,
                'allowed_events' => json_decode($entityType->allowed_events, true),
            ];
        }, $entityTypes);
    }
}

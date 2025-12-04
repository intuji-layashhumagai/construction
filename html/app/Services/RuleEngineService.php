<?php

namespace App\Services;

use App\Enums\RuleOperator;
use App\Models\Event;
use App\Models\Rule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class RuleEngineService
{

    /**
     * Validate an event against applicable business rules
     */
    public function validateEvent(object|array $event, array $context = []): ValidationResult
    {
        // Normalize the event data to ensure we have the required properties
        $normalizedEvent = $this->normalizeEvent($event);

        $violations = [];
        $applicableRules = $this->getApplicableRules(
            $normalizedEvent->entity_type,
            $normalizedEvent->event_type
        );

        foreach ($applicableRules as $rule) {
            $result = $this->evaluateRule($rule, $normalizedEvent, $context);
            if (! $result->passes) {
                $violations[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'message' => $result->message,
                    'severity' => $result->severity,
                ];
            }
        }

        return new ValidationResult(
            empty($violations),
            $violations
        );
    }

    /**
     * Normalize event data to ensure consistent interface
     */
    private function normalizeEvent(object|array $event): object
    {
        if ($event instanceof Event) {
            return $event;
        }

        if (is_array($event)) {
            return (object) $event;
        }

        // Already an object, return as-is
        return $event;
    }

    /**
     * Get rules applicable to an entity and event type
     */
    private function getApplicableRules(string $entityType, string $eventType): Collection
    {
        return Rule::active()
            ->where('entity_type', $entityType)
            ->where('event_type', $eventType)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Evaluate a single rule against an event
     */
    private function evaluateRule(Rule $rule, object $event, array $context): RuleEvaluationResult
    {
        $data = $this->buildEvaluationData($event, $context);
        
        try {
            $conditionMet = $this->evaluateConditions($rule->conditions, $data);

            if (! $conditionMet) {
                return new RuleEvaluationResult(true, null, null); // Rule not triggered
            }

            // Check if rule should reject the event
            if (isset($rule->actions['reject']) && $rule->actions['reject']) {
                return new RuleEvaluationResult(false, $rule->actions['message'] ?? 'Rule violation', 'error');
            }

            return new RuleEvaluationResult(true, null, null);

        } catch (\Exception $e) {
            info($e);
            // Log error and continue with other rules
            Log::error('Rule evaluation error', [
                'rule_id' => $rule->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return new RuleEvaluationResult(true, null, null); // Don't block on evaluation errors
        }
    }

    /**
     * Build data array for rule evaluation
     */
    private function buildEvaluationData(object $event, array $context): array
    {
        // Handle both Event models and raw event data objects
        $eventArray = method_exists($event, 'toArray') ? $event->toArray() : (array) $event;
        $eventData = $event->event_data ?? (isset($event->data) ? $event->data : []);

        return [
            'event' => $eventArray,
            'event_data' => $eventData,
            'worker' => $context['worker'] ?? null,
            'device' => $context['device'] ?? null,
            'current_time' => now(),
            'context' => $context,
        ];
    }

    /**
     * Evaluate JSON logic conditions
     */
    private function evaluateConditions(array $conditions, array $data): bool
    {
        // Evaluate each condition in the array (all must pass - AND logic)
        foreach ($conditions as $condition) {
            if (! $this->evaluateConditionTree($condition, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Recursively evaluate condition tree
     */
    private function evaluateConditionTree(array $condition, array $data): bool
    {
        if (isset($condition['and'])) {
            return collect($condition['and'])->every(fn ($c) => $this->evaluateConditionTree($c, $data));
        }

        if (isset($condition['or'])) {
            return collect($condition['or'])->contains(fn ($c) => $this->evaluateConditionTree($c, $data));
        }

        if (isset($condition['not'])) {
            return ! $this->evaluateConditionTree($condition['not'], $data);
        }

        // Simple comparison
        return $this->evaluateSimpleCondition($condition, $data);
    }

    /**
     * Evaluate simple conditions (equals, greaterThan, etc.)
     */
    private function evaluateSimpleCondition(array $condition, array $data): bool
    {

        $fact = data_get($data, $condition['fact']);
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Handle timestamp comparisons
        if (is_string($fact) && strtotime($fact) && $value instanceof Carbon) {
            $fact = Carbon::parse($fact);
        }

        switch ($operator) {
            case RuleOperator::EQUALS->value:
            case RuleOperator::EQ->value:
                return $fact == $value;
            case RuleOperator::NOT_EQUALS->value:
            case RuleOperator::NE->value:
                return $fact != $value;
            case RuleOperator::GREATER_THAN->value:
            case RuleOperator::GT->value:
                return $fact > $value;
            case RuleOperator::GREATER_THAN_OR_EQUAL->value:
            case RuleOperator::GTE->value:
                return $fact >= $value;
            case RuleOperator::LESS_THAN->value:
            case RuleOperator::LT->value:
                return $fact < $value;
            case RuleOperator::LESS_THAN_OR_EQUAL->value:
            case RuleOperator::LTE->value:
                return $fact <= $value;
            case RuleOperator::IN->value:
                return in_array($fact, (array) $value);
            case RuleOperator::NOT_IN->value:
                return ! in_array($fact, (array) $value);
            default:
                return false;
        }
    }

}

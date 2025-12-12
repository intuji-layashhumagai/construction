<?php

namespace App\Constants;

use Illuminate\Support\Facades\Config;

final class AuthorityLevel
{
    // Default hierarchy levels
    private const DEFAULT_HIERARCHY = [
        'worker' => 1,
        'supervisor' => 2,
        'manager' => 3,
    ];

    /**
     * Get the authority hierarchy configuration
     * Can be overridden via config('authority.hierarchy') or database
     */
    public static function getHierarchy(): array
    {
        // First try config file
        $configHierarchy = Config::get('authority.hierarchy');
        if ($configHierarchy) {
            return $configHierarchy;
        }

        // Fallback to default
        return self::DEFAULT_HIERARCHY;
    }

    /**
     * Get authority level value
     */
    public static function getLevel(string $authority): int
    {
        return self::getHierarchy()[$authority] ?? 0;
    }

    /**
     * Get all authority levels
     */
    public static function getAllLevels(): array
    {
        return array_keys(self::getHierarchy());
    }

    /**
     * Get the highest authority level name
     */
    public static function getHighestLevel(): string
    {
        $hierarchy = self::getHierarchy();

        return array_search(max($hierarchy), $hierarchy);
    }

    /**
     * Get the lowest authority level name
     */
    public static function getLowestLevel(): string
    {
        $hierarchy = self::getHierarchy();

        return array_search(min($hierarchy), $hierarchy);
    }

    /**
     * Check if authority level exists
     */
    public static function exists(string $authority): bool
    {
        return isset(self::getHierarchy()[$authority]);
    }

    /**
     * Compare two authority levels
     * Returns: 1 if $authority1 > $authority2, -1 if $authority1 < $authority2, 0 if equal
     */
    public static function compare(string $authority1, string $authority2): int
    {
        $level1 = self::getLevel($authority1);
        $level2 = self::getLevel($authority2);

        return $level1 <=> $level2;
    }

    /**
     * Check if authority1 has higher or equal level than authority2
     */
    public static function hasAuthority(string $authority1, string $authority2): bool
    {
        return self::compare($authority1, $authority2) >= 0;
    }

    /**
     * Get authorities higher than the given level
     */
    public static function getHigherAuthorities(string $authority): array
    {
        $currentLevel = self::getLevel($authority);
        $hierarchy = self::getHierarchy();

        return array_filter($hierarchy, function ($level) use ($currentLevel) {
            return $level > $currentLevel;
        });
    }

    /**
     * Get authorities lower than or equal to the given level
     */
    public static function getLowerOrEqualAuthorities(string $authority): array
    {
        $currentLevel = self::getLevel($authority);
        $hierarchy = self::getHierarchy();

        return array_filter($hierarchy, function ($level) use ($currentLevel) {
            return $level <= $currentLevel;
        });
    }

    /**
     * Validate authority level exists
     */
    public static function validate(string $authority): bool
    {
        return self::exists($authority);
    }

    /**
     * Get modification limits for authority levels
     */
    public static function getModificationLimits(): array
    {
        return Config::get('authority.modification_limits', [
            'supervisor' => ['max_change' => 2.0, 'requires_justification' => true],
            'manager' => ['max_change' => null, 'requires_justification' => true],
            'worker' => ['max_change' => 0, 'requires_justification' => false],
        ]);
    }

    /**
     * Check if modification is within authority limits
     */
    public static function canModifyHours(string $authorityLevel, float $originalHours, float $modifiedHours): bool
    {
        $limits = self::getModificationLimits()[$authorityLevel] ?? ['max_change' => 0];

        if ($limits['max_change'] === null) {
            return true; // No limits
        }

        $change = abs($modifiedHours - $originalHours);

        return $change <= $limits['max_change'];
    }

    /**
     * Get default authority level
     */
    public static function getDefaultLevel(): string
    {
        return Config::get('authority.default_level', 'worker');
    }

    /**
     * Check if authority level can perform emergency overrides
     */
    public static function canEmergencyOverride(string $authorityLevel): bool
    {
        $emergencyAuthorities = Config::get('authority.emergency_override', ['manager']);

        return in_array($authorityLevel, $emergencyAuthorities);
    }

    /**
     * Get all authority levels sorted by hierarchy
     */
    public static function getAllLevelsSorted(): array
    {
        $hierarchy = self::getHierarchy();
        asort($hierarchy);

        return array_keys($hierarchy);
    }

    /**
     * Get authority level display name (for UI)
     */
    public static function getDisplayName(string $authority): string
    {
        return ucfirst($authority);
    }

    /**
     * Validate authority level and throw exception if invalid
     */
    public static function validateOrFail(string $authority): void
    {
        if (! self::exists($authority)) {
            throw new \InvalidArgumentException("Invalid authority level: {$authority}");
        }
    }
}

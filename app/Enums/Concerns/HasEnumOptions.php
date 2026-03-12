<?php

namespace App\Enums\Concerns;

trait HasEnumOptions
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<string, array{label: string, dot: ?string}>
     */
    public static function optionsWithColors(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = [
                'label' => $case->label(),
                'dot' => method_exists($case, 'dotClass') ? $case->dotClass() : null,
            ];
        }

        return $options;
    }
}

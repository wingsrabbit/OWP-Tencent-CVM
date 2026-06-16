<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class Templates
{
    /**
     * @param array<string, mixed> $params
     */
    public static function selectedNameFromParams(array $params): string
    {
        $name = isset($params['configoption1']) ? trim((string) $params['configoption1']) : '';

        return $name !== '' ? $name : 'unassigned';
    }

    /**
     * @return list<array<string, string>>
     */
    public static function placeholderRows(): array
    {
        return [
            [
                'name' => 'cn-gz-basic-2c4g',
                'region' => 'ap-guangzhou',
                'zone' => 'Guangzhou Zone 3',
                'state' => 'planned',
            ],
        ];
    }
}

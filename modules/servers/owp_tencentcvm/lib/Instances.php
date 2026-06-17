<?php

declare(strict_types=1);

namespace OwpTencentCvm;

final class Instances
{
    /**
     * @param array<string, mixed> $params
     */
    public static function serviceId(array $params): int
    {
        return isset($params['serviceid']) ? (int) $params['serviceid'] : 0;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public static function placeholderFromParams(array $params): array
    {
        return [
            'service_id' => self::serviceId($params),
            'template_name' => Templates::selectedNameFromParams($params),
            'instance_id' => '',
            'state' => 'not_created',
        ];
    }
}

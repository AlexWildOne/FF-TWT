<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

final class DestinationTypes {
    public const VERSION = '1.2.0';

    public static function definitions(): array {
        return [
            'twt_azure' => [
                'label' => 'Azure TWT',
                'delivery_phase' => '5A',
                'capability' => 'primary_hub',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 0,
                'execution_mode' => 'live_primary',
            ],
            'client_azure' => [
                'label' => 'Azure do Cliente',
                'delivery_phase' => '5B',
                'capability' => 'replica',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 1,
                'execution_mode' => 'relay_5b',
            ],
            'external_api' => [
                'label' => 'API Externa',
                'delivery_phase' => '5C.5',
                'capability' => 'api',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 1,
                'execution_mode' => 'shadow',
                'connector_contract' => 'fieldflow.connector.external_api.v1',
            ],
            'client_powerbi' => [
                'label' => 'Power BI do Cliente',
                'delivery_phase' => '5C.1',
                'capability' => 'reporting',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 0,
                'execution_mode' => 'shadow',
                'connector_contract' => 'fieldflow.connector.client_powerbi.v1',
            ],
            'webhook' => [
                'label' => 'Webhook',
                'delivery_phase' => '5C.5',
                'capability' => 'webhook',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 1,
                'execution_mode' => 'shadow',
                'connector_contract' => 'fieldflow.connector.webhook.v1',
            ],
            'data_warehouse' => [
                'label' => 'Azure SQL / Data Warehouse',
                'delivery_phase' => '5C.1',
                'capability' => 'warehouse',
                'supports_endpoint' => 1,
                'requires_secret_ref' => 1,
                'execution_mode' => 'shadow',
                'connector_contract' => 'fieldflow.connector.data_warehouse.v1',
            ],
        ];
    }

    public static function exists(string $type): bool {
        return isset(self::definitions()[sanitize_key($type)]);
    }

    public static function get(string $type): array {
        $type = sanitize_key($type);
        return (array)(self::definitions()[$type] ?? []);
    }
}

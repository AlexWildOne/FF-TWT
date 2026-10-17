<?php
namespace RoutesPro\Cloud;

use RoutesPro\Services\IntegrationPlatform;

if (!defined('ABSPATH')) exit;

final class MultiDestinationFoundation {
    public const VERSION = '1.1.0';
    private static bool $registered = false;

    public static function registerHooks(): void {
        if (self::$registered) return;
        self::$registered = true;
        add_action('plugins_loaded', [self::class, 'ensureFoundation'], 20);
        add_action('fieldflow_core_event', [self::class, 'observeRouting'], 30, 4);
    }

    public static function ensureFoundation(): void {
        DestinationRegistry::ensureFoundation();
    }

    public static function observeRouting(string $eventName, array $payload, array $context, string $eventUuid): void {
        if (!DestinationRegistry::ready() || !DestinationRegistry::hasActiveExternalDestinations()) return;
        $plan = DestinationRouter::routePlan($eventName, $context, $eventUuid);
        $external = array_values(array_filter((array)$plan['destinations'], static function(array $destination): bool {
            return (string)($destination['type_key'] ?? '') !== 'twt_azure';
        }));
        if (!$external) return;
        $mode = class_exists('\\RoutesPro\\Cloud\\ClientAzureDelivery') ? ClientAzureDelivery::mode() : 'shadow';
        IntegrationPlatform::log('multi_destination', $mode === 'shadow' ? 'routing_shadow' : 'routing_plan', 'planned', [
            'event_name'=>(string)$plan['event_name'],
            'event_uuid'=>(string)$plan['event_uuid'],
            'client_id'=>(int)$plan['client_id'],
            'project_id'=>(int)$plan['project_id'],
            'mode'=>$mode,
            'external_destinations'=>array_map(static function(array $destination): array {
                return [
                    'destination_uuid'=>(string)$destination['destination_uuid'],
                    'type_key'=>(string)$destination['type_key'],
                    'delivery_key'=>(string)$destination['delivery_key'],
                    'connector'=>(string)$destination['connector'],
                    'delivery_ready'=>!empty($destination['delivery_ready']),
                ];
            }, $external),
        ], $mode === 'shadow'
            ? 'Multi-Destination em shadow mode. Nenhum conector externo é executado.'
            : 'Routing Multi-Destination resolvido. O Azure TWT mantém o canal primário independente.');
    }

    public static function health(): array {
        $registry = DestinationRegistry::health();
        $azure = AzureSyncFoundation::health();
        $clientAzure = class_exists('\\RoutesPro\\Cloud\\ClientAzureDelivery') ? ClientAzureDelivery::health() : [];
        $mode = (string)($clientAzure['mode'] ?? 'shadow');
        return [
            'version'=>self::VERSION,
            'status'=>!empty($registry['ready']) && !empty($registry['primary_twt']) && (($clientAzure['status'] ?? 'ready') !== 'critical') ? 'ready' : 'attention',
            'mode'=>$mode,
            'registry'=>$registry,
            'primary_delivery'=>'AzureSyncFoundation',
            'legacy_azure'=>$azure,
            'client_azure'=>$clientAzure,
            'external_connectors_enabled'=>$mode === 'live' && !empty($clientAzure['relay']['configured']),
        ];
    }

    public static function localProbe(): array {
        $registry = DestinationRegistry::health();
        $eventUuid = '00000000-0000-4000-8000-00000000005a';
        $key1 = DestinationRouter::deliveryKey($eventUuid, DestinationRegistry::primaryDestinationUuid());
        $key2 = DestinationRouter::deliveryKey($eventUuid, DestinationRegistry::primaryDestinationUuid());
        $plan = DestinationRouter::routePlan('submission.created', [], $eventUuid);
        $hasPrimary = false;
        foreach ((array)$plan['destinations'] as $destination) {
            if ((string)($destination['type_key'] ?? '') === 'twt_azure') {
                $hasPrimary = true;
                break;
            }
        }
        $checks = [
            'tables_ready'=>!empty($registry['ready']),
            'types_seeded'=>(int)($registry['types'] ?? 0) >= 6,
            'primary_twt_registered'=>!empty($registry['primary_twt']),
            'primary_route_resolves'=>$hasPrimary,
            'delivery_key_deterministic'=>$key1 === $key2 && strpos($key1, 'ff_del_') === 0,
            'legacy_azure_listener'=>has_action('fieldflow_core_event', [AzureSyncFoundation::class, 'captureEvent']) !== false,
        ];
        return [
            'ok'=>!in_array(false, $checks, true),
            'checks'=>$checks,
            'plan'=>$plan,
            'health'=>self::health(),
        ];
    }
}

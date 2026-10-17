<?php
namespace RoutesPro\Cloud;

if (!defined('ABSPATH')) exit;

final class DestinationRouter {
    public const VERSION = '1.0.0';

    public static function resolve(string $eventName, array $context = []): array {
        global $wpdb;
        if (!DestinationRegistry::ready()) return [];
        $eventName = DestinationRegistry::normaliseEventPattern($eventName);
        $clientId = absint($context['client_id'] ?? 0);
        $projectId = absint($context['project_id'] ?? 0);
        $tenantId = TenantIdentity::tenantId();
        $destinations = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . DestinationRegistry::destinationsTable() . ' WHERE tenant_id=%s AND is_active=1 ORDER BY (mode="primary") DESC,id ASC',
            $tenantId
        ), ARRAY_A) ?: [];
        $out = [];
        foreach ($destinations as $destination) {
            if (!self::scopeMatches($destination, $clientId, $projectId)) continue;
            $isPrimary = (string)($destination['type_key'] ?? '') === 'twt_azure' && (string)($destination['mode'] ?? '') === 'primary';
            $included = $isPrimary ? true : self::ruleDecision((int)$destination['id'], $eventName, $clientId, $projectId);
            if (!$included) continue;
            $typeKey = (string)($destination['type_key'] ?? '');
            $destination['connector_phase'] = (string)(DestinationTypes::get($typeKey)['delivery_phase'] ?? '');
            if ($isPrimary) {
                $destination['delivery_connector'] = 'legacy_azure_sync';
                $destination['delivery_ready'] = AzureConnectionManager::syncEnabled() && AzureSyncFoundation::eventEnabled($eventName);
            } elseif ($typeKey === 'client_azure') {
                $destination['delivery_connector'] = 'client_azure_relay';
                $destination['delivery_ready'] = class_exists('\RoutesPro\Cloud\ClientAzureRelayConnection') && ClientAzureRelayConnection::ready();
            } elseif (ConnectorFoundation::is5cType($typeKey)) {
                $destination = array_merge($destination, ConnectorFoundation::routeMetadata($destination));
            } else {
                $destination['delivery_connector'] = 'foundation_only';
                $destination['delivery_ready'] = false;
            }
            $out[] = $destination;
        }
        return $out;
    }

    public static function deliveryKey(string $eventUuid, string $destinationUuid): string {
        $eventUuid = strtolower(trim($eventUuid));
        $destinationUuid = strtolower(trim($destinationUuid));
        return 'ff_del_' . substr(hash('sha256', 'fieldflow-delivery-v1|' . $eventUuid . '|' . $destinationUuid), 0, 40);
    }

    public static function routePlan(string $eventName, array $context, string $eventUuid = ''): array {
        $eventUuid = trim($eventUuid) !== '' ? sanitize_text_field($eventUuid) : wp_generate_uuid4();
        $destinations = self::resolve($eventName, $context);
        $plan = [];
        foreach ($destinations as $destination) {
            $plan[] = [
                'destination_id'=>(int)$destination['id'],
                'destination_uuid'=>(string)$destination['destination_uuid'],
                'name'=>(string)$destination['name'],
                'type_key'=>(string)$destination['type_key'],
                'mode'=>(string)$destination['mode'],
                'delivery_key'=>self::deliveryKey($eventUuid, (string)$destination['destination_uuid']),
                'connector'=>(string)$destination['delivery_connector'],
                'delivery_ready'=>!empty($destination['delivery_ready']),
                'execution_mode'=>(string)($destination['execution_mode'] ?? (!empty($destination['delivery_ready']) ? 'live' : 'shadow')),
                'connector_contract'=>(string)($destination['connector_contract'] ?? ''),
                'connector_transport'=>(string)($destination['connector_transport'] ?? ''),
                'auth_strategy'=>(string)($destination['auth_strategy'] ?? ''),
                'future_phase'=>(string)($destination['future_phase'] ?? ''),
            ];
        }
        return [
            'event_uuid'=>$eventUuid,
            'event_name'=>DestinationRegistry::normaliseEventPattern($eventName),
            'tenant_id'=>TenantIdentity::tenantId(),
            'client_id'=>absint($context['client_id'] ?? 0),
            'project_id'=>absint($context['project_id'] ?? 0),
            'destinations'=>$plan,
        ];
    }

    private static function scopeMatches(array $row, int $clientId, int $projectId): bool {
        $destinationClient = absint($row['client_id'] ?? 0);
        $destinationProject = absint($row['project_id'] ?? 0);
        if ($destinationClient > 0 && $destinationClient !== $clientId) return false;
        if ($destinationProject > 0 && $destinationProject !== $projectId) return false;
        return true;
    }

    private static function ruleDecision(int $destinationId, string $eventName, int $clientId, int $projectId): bool {
        global $wpdb;
        $rules = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . DestinationRegistry::rulesTable() . ' WHERE destination_id=%d AND is_active=1 ORDER BY priority DESC,id DESC',
            $destinationId
        ), ARRAY_A) ?: [];
        $best = null;
        $bestScore = -1;
        foreach ($rules as $rule) {
            $ruleClient = absint($rule['client_id'] ?? 0);
            $ruleProject = absint($rule['project_id'] ?? 0);
            if ($ruleClient > 0 && $ruleClient !== $clientId) continue;
            if ($ruleProject > 0 && $ruleProject !== $projectId) continue;
            if (!self::eventMatches((string)($rule['event_pattern'] ?? '*'), $eventName)) continue;
            $scopeScore = $ruleProject > 0 ? 20000 : ($ruleClient > 0 ? 10000 : 0);
            $score = $scopeScore + max(0, min(9999, absint($rule['priority'] ?? 0)));
            if ($score > $bestScore) {
                $best = $rule;
                $bestScore = $score;
            }
        }
        return $best !== null && (string)($best['action_name'] ?? '') === 'include';
    }

    private static function eventMatches(string $pattern, string $eventName): bool {
        $pattern = DestinationRegistry::normaliseEventPattern($pattern);
        if ($pattern === '*' || $pattern === $eventName) return true;
        $regex = '/^' . str_replace('\\*', '.*', preg_quote($pattern, '/')) . '$/i';
        return preg_match($regex, $eventName) === 1;
    }
}

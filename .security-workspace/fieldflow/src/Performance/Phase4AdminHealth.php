<?php
namespace RoutesPro\Performance;

use RoutesPro\Integrations\ApiKeyService;
use RoutesPro\Integrations\WebhookService;

if (!defined('ABSPATH')) exit;

/**
 * 9DI Phase 4D: render-only, bounded summaries for System Health.
 * The old dashboard fetched up to 500 API keys (including key hashes), 200
 * webhook configurations, and 500 complete delivery records just to count.
 * These lightweight reads preserve the same oldest/newest boundaries and
 * never load credentials, payloads or other integration record contents.
 */
final class Phase4AdminHealth {
    public const CONTRACT = 'fieldflow-phase4-admin-health-snapshot-reuse-9di-v1';

    public static function integrationCounts(): array {
        global $wpdb;
        $result = [
            'api_keys'=>0, 'webhooks'=>0, 'deliveries'=>0,
            'failed_deliveries'=>0, 'read_only'=>true,
        ];
        if (ApiKeyService::tableReady()) {
            $ids = $wpdb->get_col('SELECT id FROM ' . ApiKeyService::table() . ' ORDER BY id DESC LIMIT 500');
            $result['api_keys'] = is_array($ids) ? count($ids) : 0;
        }
        if (WebhookService::tablesReady()) {
            $ids = $wpdb->get_col('SELECT id FROM ' . WebhookService::hooksTable() . ' ORDER BY id DESC LIMIT 200');
            $result['webhooks'] = is_array($ids) ? count($ids) : 0;
            $statuses = $wpdb->get_col('SELECT status FROM ' . WebhookService::deliveriesTable() . ' ORDER BY id DESC LIMIT 500');
            $statuses = is_array($statuses) ? $statuses : [];
            $result['deliveries'] = count($statuses);
            foreach ($statuses as $status) {
                if ((string)$status === 'failed') $result['failed_deliveries']++;
            }
        }
        return $result;
    }
}

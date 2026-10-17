<?php
namespace RoutesPro\Shopping;

use RoutesPro\Support\Permissions;

if (!defined('ABSPATH')) exit;

final class ShoppingResultsService {
    public static function query(array $filters = [], int $userId = 0, bool $paginate = true): array {
        global $wpdb;
        $userId = $userId ?: get_current_user_id();
        if (!$userId) throw new \RuntimeException('Inicia sessão para consultar os resultados Shopping.');

        $page = max(1, absint($filters['page'] ?? 1));
        $perPage = max(1, min(100, absint($filters['per_page'] ?? 20)));
        $where = ["s.status='submitted'"];
        $args = [];
        self::appendScope($where, $args, $filters, $userId);
        self::appendFilters($where, $args, $filters);

        $joins = self::joinsSql();
        $whereSql = implode(' AND ', $where);
        $countSql = 'SELECT COUNT(*) FROM ' . ShoppingService::submissionsTable() . " s {$joins} WHERE {$whereSql}";
        $total = (int)$wpdb->get_var($args ? $wpdb->prepare($countSql, ...$args) : $countSql);

        $summarySql = 'SELECT COUNT(DISTINCT s.id) submissions, COUNT(DISTINCT COALESCE(s.location_id,a.source_store_name)) stores, COUNT(DISTINCT s.user_id) merchandisers, SUM(CASE WHEN s.notification_status IN (\'sent\',\'partial\') THEN 1 ELSE 0 END) emails_sent FROM ' . ShoppingService::submissionsTable() . " s {$joins} WHERE {$whereSql}";
        $summary = $wpdb->get_row($args ? $wpdb->prepare($summarySql, ...$args) : $summarySql, ARRAY_A) ?: [];

        $select = "SELECT s.id,s.batch_id,s.assignment_id,s.client_id,s.project_id,s.location_id,s.user_id,s.status,s.notes,s.notification_status,s.notification_recipients,s.notification_attempts,s.notification_last_sent_at,s.submitted_at,s.updated_at,s.prices_json,
            b.title,b.week_no,b.year_no,b.products_json,b.source_file_name,b.notify_client,b.recipient_user_ids,b.extra_emails,
            a.source_store_name,COALESCE(NULLIF(s.insignia,''),NULLIF(a.insignia,''),a.source_store_name) AS insignia,COALESCE(l.name,a.source_store_name) AS location_name,l.city,
            c.name AS client_name,p.name AS project_name,u.display_name AS merchandiser_name,u.user_email AS merchandiser_email";
        $sql = $select . ' FROM ' . ShoppingService::submissionsTable() . " s {$joins} WHERE {$whereSql} ORDER BY s.submitted_at DESC,s.id DESC";
        $queryArgs = $args;
        if ($paginate) {
            $sql .= ' LIMIT %d OFFSET %d';
            $queryArgs[] = $perPage;
            $queryArgs[] = ($page - 1) * $perPage;
        }
        $rows = $wpdb->get_results($queryArgs ? $wpdb->prepare($sql, ...$queryArgs) : $sql, ARRAY_A) ?: [];
        foreach ($rows as &$row) {
            $prices = self::decodeJson((string)($row['prices_json'] ?? ''));
            $filled = 0;
            foreach ($prices as $price) {
                if (!is_array($price)) continue;
                if (($price['regular'] ?? null) !== null && (string)($price['regular'] ?? '') !== '') $filled++;
                if (($price['promo'] ?? null) !== null && (string)($price['promo'] ?? '') !== '') $filled++;
            }
            $row['id'] = (int)$row['id'];
            $row['batch_id'] = (int)$row['batch_id'];
            $row['assignment_id'] = (int)$row['assignment_id'];
            $row['client_id'] = (int)$row['client_id'];
            $row['project_id'] = (int)$row['project_id'];
            $row['location_id'] = (int)$row['location_id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['week_no'] = (int)$row['week_no'];
            $row['year_no'] = (int)$row['year_no'];
            $row['filled_fields'] = $filled;
            $row['notification_attempts'] = (int)($row['notification_attempts'] ?? 0);
            $row['notify_client'] = (int)($row['notify_client'] ?? 0);
            $row['product_count'] = count(self::decodeJson((string)($row['products_json'] ?? '')));
            unset($row['prices_json'], $row['products_json']);
        }
        unset($row);

        return [
            'items' => array_values($rows),
            'summary' => [
                'submissions' => (int)($summary['submissions'] ?? 0),
                'stores' => (int)($summary['stores'] ?? 0),
                'merchandisers' => (int)($summary['merchandisers'] ?? 0),
                'emails_sent' => (int)($summary['emails_sent'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int)ceil($total / $perPage)),
            ],
        ];
    }

    public static function detail(int $submissionId, int $userId = 0): array {
        if ($submissionId <= 0) throw new \RuntimeException('Submissão Shopping inválida.');
        $result = self::query(['submission_id'=>$submissionId, 'page'=>1, 'per_page'=>1], $userId, false);
        $row = $result['items'][0] ?? null;
        if (!$row) throw new \RuntimeException('Submissão Shopping não encontrada ou sem acesso.');

        global $wpdb;
        $raw = $wpdb->get_row($wpdb->prepare(
            'SELECT s.prices_json,b.products_json FROM ' . ShoppingService::submissionsTable() . ' s INNER JOIN ' . ShoppingService::batchesTable() . ' b ON b.id=s.batch_id WHERE s.id=%d LIMIT 1',
            $submissionId
        ), ARRAY_A) ?: [];
        $prices = self::decodeJson((string)($raw['prices_json'] ?? ''));
        $products = self::decodeJson((string)($raw['products_json'] ?? ''));
        $items = [];
        foreach ($products as $product) {
            if (!is_array($product)) continue;
            $key = (string)($product['key'] ?? '');
            if ($key === '') continue;
            $price = is_array($prices[$key] ?? null) ? $prices[$key] : [];
            $items[] = [
                'key' => $key,
                'type' => (string)($product['type'] ?? ''),
                'name' => (string)($product['name'] ?? ''),
                'regular' => self::priceValue($price['regular'] ?? null),
                'promo' => self::priceValue($price['promo'] ?? null),
            ];
        }
        $row['prices'] = $items;
        return $row;
    }

    public static function exportDataset(array $filters = [], int $userId = 0): array {
        $filters['page'] = 1;
        $filters['per_page'] = 100;
        $result = self::query($filters, $userId, false);
        $summaryRows = [];
        $priceRows = [];
        foreach ($result['items'] as $row) {
            $detail = self::detail((int)$row['id'], $userId);
            $summaryRows[] = [
                (int)$row['id'],
                self::formatDate((string)$row['submitted_at']),
                (string)$row['client_name'],
                (string)$row['project_name'],
                (string)$row['title'],
                (int)$row['week_no'],
                (int)$row['year_no'],
                (string)($row['insignia'] ?? ''),
                (string)$row['location_name'],
                (string)$row['city'],
                (string)$row['merchandiser_name'],
                (string)$row['merchandiser_email'],
                (int)$row['filled_fields'],
                (string)$row['notification_status'],
                (string)$row['notification_recipients'],
                (string)$row['notes'],
            ];
            foreach ((array)($detail['prices'] ?? []) as $price) {
                $priceRows[] = [
                    (int)$row['id'],
                    self::formatDate((string)$row['submitted_at']),
                    (string)$row['client_name'],
                    (string)$row['project_name'],
                    (int)$row['week_no'],
                    (int)$row['year_no'],
                    (string)($row['insignia'] ?? ''),
                    (string)$row['location_name'],
                    (string)$row['merchandiser_name'],
                    (string)($price['type'] ?? ''),
                    (string)($price['name'] ?? ''),
                    $price['regular'] === null ? '' : (float)$price['regular'],
                    $price['promo'] === null ? '' : (float)$price['promo'],
                    (string)$row['notes'],
                ];
            }
        }
        return [
            'summary' => $result['summary'],
            'submissions' => $summaryRows,
            'prices' => $priceRows,
            'filters' => self::cleanFilters($filters),
        ];
    }

    public static function exportUrl(string $format, array $filters = []): string {
        $args = array_merge([
            'action' => 'fieldflow_shopping_export',
            'format' => sanitize_key($format),
            '_wpnonce' => wp_create_nonce('fieldflow_shopping_export'),
        ], self::cleanFilters($filters));
        return add_query_arg($args, admin_url('admin-post.php'));
    }

    private static function appendScope(array &$where, array &$args, array $filters, int $userId): void {
        $clientId = absint($filters['client_id'] ?? 0);
        $projectId = absint($filters['project_id'] ?? 0);
        if (Permissions::is_manager($userId)) return;
        if ($clientId && !Permissions::is_allowed_client($clientId, $userId)) throw new \RuntimeException('Sem acesso ao cliente selecionado.');
        if ($projectId && !Permissions::is_allowed_project($projectId, $userId)) throw new \RuntimeException('Sem acesso ao projeto selecionado.');

        if (user_can($userId, 'routespro_execute')) {
            $where[] = 's.user_id=%d';
            $args[] = $userId;
            return;
        }
        $scope = Permissions::get_scope($userId);
        $projectIds = array_values(array_filter(array_map('absint', (array)($scope['project_ids'] ?? []))));
        $clientIds = array_values(array_filter(array_map('absint', (array)($scope['client_ids'] ?? []))));
        if ($projectId || $clientId) return;
        if ($projectIds) {
            $where[] = 's.project_id IN (' . implode(',', array_fill(0, count($projectIds), '%d')) . ')';
            array_push($args, ...$projectIds);
        } elseif ($clientIds) {
            $where[] = 's.client_id IN (' . implode(',', array_fill(0, count($clientIds), '%d')) . ')';
            array_push($args, ...$clientIds);
        } else {
            $where[] = '1=0';
        }
    }

    private static function appendFilters(array &$where, array &$args, array $filters): void {
        foreach (['submission_id'=>'s.id','batch_id'=>'s.batch_id','client_id'=>'s.client_id','project_id'=>'s.project_id','location_id'=>'s.location_id','user_id'=>'s.user_id'] as $key=>$column) {
            $value = absint($filters[$key] ?? 0);
            if (!$value) continue;
            $where[] = $column . '=%d';
            $args[] = $value;
        }
        $from = self::dateValue((string)($filters['from'] ?? ''));
        $to = self::dateValue((string)($filters['to'] ?? ''));
        if ($from !== '') { $where[] = 's.submitted_at >= %s'; $args[] = $from . ' 00:00:00'; }
        if ($to !== '') { $where[] = 's.submitted_at <= %s'; $args[] = $to . ' 23:59:59'; }
        $q = sanitize_text_field((string)($filters['q'] ?? ''));
        if ($q !== '') {
            global $wpdb;
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = "(COALESCE(l.name,a.source_store_name) LIKE %s OR COALESCE(NULLIF(s.insignia,''),NULLIF(a.insignia,''),a.source_store_name) LIKE %s OR b.title LIKE %s OR u.display_name LIKE %s OR p.name LIKE %s)";
            array_push($args, $like, $like, $like, $like, $like);
        }
    }

    private static function joinsSql(): string {
        global $wpdb;
        $px = $wpdb->prefix . 'routespro_';
        return 'INNER JOIN ' . ShoppingService::batchesTable() . ' b ON b.id=s.batch_id '
            . 'INNER JOIN ' . ShoppingService::assignmentsTable() . ' a ON a.id=s.assignment_id '
            . "LEFT JOIN {$px}locations l ON l.id=s.location_id "
            . "LEFT JOIN {$px}clients c ON c.id=s.client_id "
            . "LEFT JOIN {$px}projects p ON p.id=s.project_id "
            . "LEFT JOIN {$wpdb->users} u ON u.ID=s.user_id";
    }

    private static function cleanFilters(array $filters): array {
        $clean = [];
        foreach (['client_id','project_id','location_id','user_id','batch_id'] as $key) {
            $value = absint($filters[$key] ?? 0);
            if ($value) $clean[$key] = $value;
        }
        foreach (['from','to'] as $key) {
            $value = self::dateValue((string)($filters[$key] ?? ''));
            if ($value !== '') $clean[$key] = $value;
        }
        $q = sanitize_text_field((string)($filters['q'] ?? ''));
        if ($q !== '') $clean['q'] = $q;
        return $clean;
    }

    private static function dateValue(string $value): string {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private static function decodeJson(string $json): array {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function priceValue($value): ?float {
        if ($value === null || $value === '' || !is_numeric($value)) return null;
        return round((float)$value, 2);
    }

    private static function formatDate(string $value): string {
        $timestamp = strtotime($value);
        return $timestamp ? wp_date('d/m/Y H:i', $timestamp) : $value;
    }
}

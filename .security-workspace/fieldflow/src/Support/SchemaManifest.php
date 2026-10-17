<?php
namespace RoutesPro\Support;

if (!defined('ABSPATH')) exit;

/**
 * Canonical, read-only schema manifest generated from the 2.2.266 activation
 * definitions. It is deliberately separate from the installer so the audit
 * can detect drift without executing dbDelta or altering production data.
 */
final class SchemaManifest {
    public const VERSION = '1.11.0';

    public static function tables(): array {
        return [
            'clients' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'taxid' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'email' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'phone' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                ],
            ],
            'email_logs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'email_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'context_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sender_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'recipient_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'recipient_email' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'recipient_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'message_kind' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'subject' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'body' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'mail_result' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'email_type' => [
                        'unique' => false,
                        'columns' => ['email_type'],
                    ],
                    'context_key' => [
                        'unique' => false,
                        'columns' => ['context_key'],
                    ],
                    'client' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'route' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'sender' => [
                        'unique' => false,
                        'columns' => ['sender_user_id'],
                    ],
                    'recipient_user' => [
                        'unique' => false,
                        'columns' => ['recipient_user_id'],
                    ],
                    'created_at' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'mail_outbox' => [
                'required' => true,
                'source' => 'activator+migration',
                'label' => 'Outbox persistente de email FieldFlow',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'message_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'idempotency_key' => ['type'=>'varchar(80)','nullable'=>true,'auto_increment'=>false],
                    'source' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'email_type' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'route_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'entity_type' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'entity_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'to_json' => ['type'=>'longtext','nullable'=>false,'auto_increment'=>false],
                    'cc_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'bcc_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'subject' => ['type'=>'varchar(255)','nullable'=>false,'auto_increment'=>false],
                    'body' => ['type'=>'longtext','nullable'=>false,'auto_increment'=>false],
                    'headers_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'attachments_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'attempt_count' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'max_attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'next_attempt_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'locked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'locked_by' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'last_dispatch_id' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'last_error' => ['type'=>'text','nullable'=>true,'auto_increment'=>false],
                    'provider_message_id' => ['type'=>'varchar(191)','nullable'=>true,'auto_increment'=>false],
                    'attempt_log_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'meta_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'sent_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'cancelled_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'message_id_idx' => ['unique'=>true,'columns'=>['message_id']],
                    'idempotency_key_idx' => ['unique'=>true,'columns'=>['idempotency_key']],
                    'status_next_idx' => ['unique'=>false,'columns'=>['status','next_attempt_at']],
                    'client_idx' => ['unique'=>false,'columns'=>['client_id']],
                    'project_idx' => ['unique'=>false,'columns'=>['project_id']],
                    'route_idx' => ['unique'=>false,'columns'=>['route_id']],
                    'source_idx' => ['unique'=>false,'columns'=>['source']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                ],
            ],
            'report_schedules' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'weekday' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'send_time' => [
                        'type' => 'time',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'start_date' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'end_date' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'cumulative_start' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'periods_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'formats_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'to_team' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'to_clients' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'extra_emails' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'test_email' => [
                        'type' => 'varchar(190)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'test_scope' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'test_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'subject_template' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'intro_text' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'brand_primary' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'next_run_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_run_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_status' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_message' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_test_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_test_status' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_test_message' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'scope_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'project_id'],
                    ],
                    'active_next_idx' => [
                        'unique' => false,
                        'columns' => ['is_active', 'next_run_at'],
                    ],
                    'created_by_idx' => [
                        'unique' => false,
                        'columns' => ['created_by'],
                    ],
                ],
            ],
            'projects' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                ],
            ],
            'locations' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'address' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'lat' => [
                        'type' => 'decimal(10,7)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'lng' => [
                        'type' => 'decimal(10,7)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'window_start' => [
                        'type' => 'time',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'window_end' => [
                        'type' => 'time',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'service_time_min' => [
                        'type' => 'int',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'tags' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'external_ref' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'latlng' => [
                        'unique' => false,
                        'columns' => ['lat', 'lng'],
                    ],
                ],
            ],
            'location_profiles' => [
                'required' => true,
                'source' => 'activator',
                'label' => 'Ficha operacional das lojas',
                'columns' => [
                    'id' => ['type' => 'bigint unsigned', 'nullable' => false, 'auto_increment' => true],
                    'location_id' => ['type' => 'bigint unsigned', 'nullable' => false, 'auto_increment' => false],
                    'operational_email' => ['type' => 'varchar(255)', 'nullable' => true, 'auto_increment' => false],
                    'operational_phone' => ['type' => 'varchar(64)', 'nullable' => true, 'auto_increment' => false],
                    'responsible_name' => ['type' => 'varchar(255)', 'nullable' => true, 'auto_increment' => false],
                    'store_format' => ['type' => 'varchar(96)', 'nullable' => true, 'auto_increment' => false],
                    'assortment_profile' => ['type' => 'varchar(64)', 'nullable' => false, 'auto_increment' => false],
                    'assortment_profile_source' => ['type' => 'varchar(32)', 'nullable' => false, 'auto_increment' => false],
                    'assortment_profile_confidence' => ['type' => 'tinyint unsigned', 'nullable' => false, 'auto_increment' => false],
                    'pending_status' => ['type' => 'varchar(24)', 'nullable' => false, 'auto_increment' => false],
                    'pending_actions' => ['type' => 'text', 'nullable' => true, 'auto_increment' => false],
                    'store_notes' => ['type' => 'longtext', 'nullable' => true, 'auto_increment' => false],
                    'revision_no' => ['type' => 'int unsigned', 'nullable' => false, 'auto_increment' => false],
                    'created_by' => ['type' => 'bigint unsigned', 'nullable' => true, 'auto_increment' => false],
                    'updated_by' => ['type' => 'bigint unsigned', 'nullable' => true, 'auto_increment' => false],
                    'created_at' => ['type' => 'datetime', 'nullable' => true, 'auto_increment' => false],
                    'updated_at' => ['type' => 'datetime', 'nullable' => true, 'auto_increment' => false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique' => true, 'columns' => ['id']],
                    'location_unique' => ['unique' => true, 'columns' => ['location_id']],
                    'pending_idx' => ['unique' => false, 'columns' => ['pending_status']],
                    'assortment_profile_idx' => ['unique' => false, 'columns' => ['assortment_profile']],
                    'store_format_idx' => ['unique' => false, 'columns' => ['store_format']],
                    'updated_idx' => ['unique' => false, 'columns' => ['updated_at']],
                    'updated_by_idx' => ['unique' => false, 'columns' => ['updated_by']],
                ],
            ],
            'routes' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'date' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'owner_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'date_idx' => [
                        'unique' => false,
                        'columns' => ['date'],
                    ],
                    'owner' => [
                        'unique' => false,
                        'columns' => ['owner_user_id'],
                    ],
                    'client' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                ],
            ],
            'route_stops' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'seq' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'planned_arrival' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'planned_departure' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'arrived_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'departed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'duration_s' => [
                        'type' => 'int',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'note' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'fail_reason' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'photo_url' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'signature_data' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'qty' => [
                        'type' => 'double',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'weight' => [
                        'type' => 'double',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'volume' => [
                        'type' => 'double',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'real_lat' => [
                        'type' => 'decimal(10,7)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'real_lng' => [
                        'type' => 'decimal(10,7)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'route' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'location' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'seq' => [
                        'unique' => false,
                        'columns' => ['seq'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                ],
            ],
            'project_assignments' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'role' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_project_user' => [
                        'unique' => true,
                        'columns' => ['project_id', 'user_id'],
                    ],
                    'project' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'user' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'role_idx' => [
                        'unique' => false,
                        'columns' => ['role'],
                    ],
                ],
            ],
            'assignments' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'role' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_route_user' => [
                        'unique' => true,
                        'columns' => ['route_id', 'user_id'],
                    ],
                    'route' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'user' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'role_idx' => [
                        'unique' => false,
                        'columns' => ['role'],
                    ],
                ],
            ],
            'forms' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'schema_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'settings_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'theme_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'updated_idx' => [
                        'unique' => false,
                        'columns' => ['updated_at'],
                    ],
                ],
            ],
            'form_submissions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'form_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'submitted_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'form_idx' => [
                        'unique' => false,
                        'columns' => ['form_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'submitted_idx' => [
                        'unique' => false,
                        'columns' => ['submitted_at'],
                    ],
                ],
            ],
            'form_submission_answers' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'question_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'question_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_text' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_number' => [
                        'type' => 'double',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'submission_idx' => [
                        'unique' => false,
                        'columns' => ['submission_id'],
                    ],
                    'question_key' => [
                        'unique' => false,
                        'columns' => ['question_key'],
                    ],
                ],
            ],
            'form_records' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'record_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'form_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'current_version_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'current_version_no' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'first_submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'latest_submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'latest_binding_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'latest_route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'latest_route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'first_submitted_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_submitted_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'version_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'record_key_idx' => [
                        'unique' => true,
                        'columns' => ['record_key'],
                    ],
                    'form_idx' => [
                        'unique' => false,
                        'columns' => ['form_id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'current_version_idx' => [
                        'unique' => false,
                        'columns' => ['current_version_id'],
                    ],
                    'latest_submission_idx' => [
                        'unique' => false,
                        'columns' => ['latest_submission_id'],
                    ],
                    'submitted_idx' => [
                        'unique' => false,
                        'columns' => ['last_submitted_at'],
                    ],
                ],
            ],
            'form_record_versions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'record_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'version_no' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'binding_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'owner_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'submitted_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'parent_version_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'change_summary_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_record_version' => [
                        'unique' => true,
                        'columns' => ['record_id', 'version_no'],
                    ],
                    'record_idx' => [
                        'unique' => false,
                        'columns' => ['record_id'],
                    ],
                    'submission_idx' => [
                        'unique' => false,
                        'columns' => ['submission_id'],
                    ],
                    'binding_idx' => [
                        'unique' => false,
                        'columns' => ['binding_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'route_stop_idx' => [
                        'unique' => false,
                        'columns' => ['route_stop_id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'owner_idx' => [
                        'unique' => false,
                        'columns' => ['owner_user_id'],
                    ],
                    'submitted_idx' => [
                        'unique' => false,
                        'columns' => ['submitted_at'],
                    ],
                ],
            ],
            'form_record_values' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'record_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'version_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'question_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'question_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_text' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_number' => [
                        'type' => 'double',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'value_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'record_idx' => [
                        'unique' => false,
                        'columns' => ['record_id'],
                    ],
                    'version_idx' => [
                        'unique' => false,
                        'columns' => ['version_id'],
                    ],
                    'question_idx' => [
                        'unique' => false,
                        'columns' => ['question_key'],
                    ],
                    'record_question_idx' => [
                        'unique' => false,
                        'columns' => ['record_id', 'question_key'],
                    ],
                ],
            ],
            'form_analytics_bindings' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'form_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'question_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'metric_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'metric_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'chart_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'aggregation' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'dimension' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'scope_mode' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'settings_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_metric' => [
                        'unique' => true,
                        'columns' => ['form_id', 'question_key', 'metric_key'],
                    ],
                    'form_idx' => [
                        'unique' => false,
                        'columns' => ['form_id'],
                    ],
                    'question_idx' => [
                        'unique' => false,
                        'columns' => ['question_key'],
                    ],
                    'chart_idx' => [
                        'unique' => false,
                        'columns' => ['chart_type'],
                    ],
                    'agg_idx' => [
                        'unique' => false,
                        'columns' => ['aggregation'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                ],
            ],
            'analytics_dashboards' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'description' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'visibility' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'layout_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'ai_enabled' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'ai_objective' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'ai_settings_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'ai_last_insights_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'ai_last_generated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                    'sort_idx' => [
                        'unique' => false,
                        'columns' => ['sort_order'],
                    ],
                ],
            ],
            'analytics_store_groups' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'group_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'rule_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                ],
            ],
            'analytics_store_group_items' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'group_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['group_id', 'location_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                ],
            ],
            'form_bindings' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'form_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'mode' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'priority' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'form_idx' => [
                        'unique' => false,
                        'columns' => ['form_id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'stop_idx' => [
                        'unique' => false,
                        'columns' => ['stop_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                    'priority_idx' => [
                        'unique' => false,
                        'columns' => ['priority'],
                    ],
                ],
            ],
            'system_logs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'log_level' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'context_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'message' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'level_idx' => [
                        'unique' => false,
                        'columns' => ['log_level'],
                    ],
                    'context_idx' => [
                        'unique' => false,
                        'columns' => ['context_key'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'process_runs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'run_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'process_key' => [
                        'type' => 'varchar(96)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'hook_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'instance_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'process_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'reference_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'attempt' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'scheduled_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'started_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'duration_ms' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'message' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'error_code' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'recovery_attempt' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'recovery_status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'next_retry_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'parent_run_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'terminal_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'args_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'run_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['run_uuid'],
                    ],
                    'process_status_idx' => [
                        'unique' => false,
                        'columns' => ['process_key', 'status'],
                    ],
                    'hook_idx' => [
                        'unique' => false,
                        'columns' => ['hook_name'],
                    ],
                    'instance_idx' => [
                        'unique' => false,
                        'columns' => ['instance_hash'],
                    ],
                    'scheduled_idx' => [
                        'unique' => false,
                        'columns' => ['scheduled_at'],
                    ],
                    'started_idx' => [
                        'unique' => false,
                        'columns' => ['started_at'],
                    ],
                    'completed_idx' => [
                        'unique' => false,
                        'columns' => ['completed_at'],
                    ],
                    'recovery_idx' => [
                        'unique' => false,
                        'columns' => ['recovery_status', 'next_retry_at'],
                    ],
                    'parent_run_idx' => [
                        'unique' => false,
                        'columns' => ['parent_run_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'process_circuits' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'process_key' => [
                        'type' => 'varchar(96)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'state' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'failure_count' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'opened_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'retry_after' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_failure_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_success_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_error_code' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_message' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'process_key_idx' => [
                        'unique' => true,
                        'columns' => ['process_key'],
                    ],
                    'state_retry_idx' => [
                        'unique' => false,
                        'columns' => ['state', 'retry_after'],
                    ],
                ],
            ],
            'queue_jobs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'job_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'queue_name' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'hook_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'priority' => [
                        'type' => 'smallint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'attempts' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'max_attempts' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'available_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'locked_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'lock_token' => [
                        'type' => 'char(36)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'started_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'duration_ms' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'active_key' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'idempotency_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'args_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_error_code' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_error_message' => [
                        'type' => 'varchar(500)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'driver' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'job_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['job_uuid'],
                    ],
                    'active_key_idx' => [
                        'unique' => true,
                        'columns' => ['active_key'],
                    ],
                    'status_available_idx' => [
                        'unique' => false,
                        'columns' => ['status', 'available_at', 'priority'],
                    ],
                    'queue_status_idx' => [
                        'unique' => false,
                        'columns' => ['queue_name', 'status', 'available_at'],
                    ],
                    'hook_status_idx' => [
                        'unique' => false,
                        'columns' => ['hook_name', 'status'],
                    ],
                    'locked_idx' => [
                        'unique' => false,
                        'columns' => ['status', 'locked_at'],
                    ],
                    'completed_idx' => [
                        'unique' => false,
                        'columns' => ['completed_at'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'core_events' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'event_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'event_name' => [
                        'type' => 'varchar(96)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'event_version' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'entity_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'entity_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'actor_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'briefing_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'correlation_id' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'payload_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'error_message' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'attempts' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'available_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'processed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'event_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['event_uuid'],
                    ],
                    'event_name_idx' => [
                        'unique' => false,
                        'columns' => ['event_name'],
                    ],
                    'status_available_idx' => [
                        'unique' => false,
                        'columns' => ['status', 'available_at'],
                    ],
                    'entity_idx' => [
                        'unique' => false,
                        'columns' => ['entity_type', 'entity_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'submission_idx' => [
                        'unique' => false,
                        'columns' => ['submission_id'],
                    ],
                    'correlation_idx' => [
                        'unique' => false,
                        'columns' => ['correlation_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'cloud_sync_jobs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'sync_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'installation_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'destination' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'event_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'event_name' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'max_attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'available_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'locked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'lock_token' => ['type'=>'char(36)','nullable'=>true,'auto_increment'=>false],
                    'completed_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_http_code' => ['type'=>'smallint unsigned','nullable'=>true,'auto_increment'=>false],
                    'last_error_code' => ['type'=>'varchar(96)','nullable'=>true,'auto_increment'=>false],
                    'last_error_message' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'payload_json' => ['type'=>'longtext','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'sync_uuid_idx' => ['unique'=>true,'columns'=>['sync_uuid']],
                    'destination_event_idx' => ['unique'=>true,'columns'=>['destination','event_uuid']],
                    'status_available_idx' => ['unique'=>false,'columns'=>['status','available_at']],
                    'tenant_status_idx' => ['unique'=>false,'columns'=>['tenant_id','status','available_at']],
                    'client_project_idx' => ['unique'=>false,'columns'=>['client_id','project_id']],
                    'event_name_idx' => ['unique'=>false,'columns'=>['event_name']],
                    'completed_idx' => ['unique'=>false,'columns'=>['completed_at']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                ],
            ],
            'cloud_delivery_jobs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'delivery_key' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'installation_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'destination_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'destination_uuid' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'event_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'event_name' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'max_attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'base_delay_seconds' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'available_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'locked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'lock_token' => ['type'=>'char(36)','nullable'=>true,'auto_increment'=>false],
                    'completed_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_http_code' => ['type'=>'smallint unsigned','nullable'=>true,'auto_increment'=>false],
                    'last_error_code' => ['type'=>'varchar(96)','nullable'=>true,'auto_increment'=>false],
                    'last_error_message' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'payload_json' => ['type'=>'longtext','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'delivery_key_idx' => ['unique'=>true,'columns'=>['delivery_key']],
                    'destination_status_idx' => ['unique'=>false,'columns'=>['destination_uuid','status','available_at']],
                    'tenant_status_idx' => ['unique'=>false,'columns'=>['tenant_id','status','available_at']],
                    'scope_idx' => ['unique'=>false,'columns'=>['client_id','project_id']],
                    'event_idx' => ['unique'=>false,'columns'=>['event_name','event_uuid']],
                    'completed_idx' => ['unique'=>false,'columns'=>['completed_at']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                ],
            ],
            'cloud_connector_jobs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'delivery_key' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'installation_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'destination_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'destination_uuid' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'connector_type' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'event_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'event_name' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'execution_mode' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'max_attempts' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'base_delay_seconds' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'available_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'locked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'lock_token' => ['type'=>'char(36)','nullable'=>true,'auto_increment'=>false],
                    'completed_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_http_code' => ['type'=>'smallint unsigned','nullable'=>true,'auto_increment'=>false],
                    'last_error_code' => ['type'=>'varchar(96)','nullable'=>true,'auto_increment'=>false],
                    'last_error_message' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'payload_sha256' => ['type'=>'char(64)','nullable'=>false,'auto_increment'=>false],
                    'payload_json' => ['type'=>'longtext','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'delivery_key_idx' => ['unique'=>true,'columns'=>['delivery_key']],
                    'destination_status_idx' => ['unique'=>false,'columns'=>['destination_uuid','status','available_at']],
                    'connector_status_idx' => ['unique'=>false,'columns'=>['connector_type','status','available_at']],
                    'tenant_status_idx' => ['unique'=>false,'columns'=>['tenant_id','status','available_at']],
                    'scope_idx' => ['unique'=>false,'columns'=>['client_id','project_id']],
                    'event_idx' => ['unique'=>false,'columns'=>['event_name','event_uuid']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                ],
            ],
            'cloud_destination_types' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'type_key' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'label' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'delivery_phase' => ['type'=>'varchar(16)','nullable'=>false,'auto_increment'=>false],
                    'capability' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'supports_endpoint' => ['type'=>'tinyint(1)','nullable'=>false,'auto_increment'=>false],
                    'requires_secret_ref' => ['type'=>'tinyint(1)','nullable'=>false,'auto_increment'=>false],
                    'is_active' => ['type'=>'tinyint(1)','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['type_key']],
                    'active_idx' => ['unique'=>false,'columns'=>['is_active']],
                    'phase_idx' => ['unique'=>false,'columns'=>['delivery_phase']],
                ],
            ],
            'cloud_destinations' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'destination_uuid' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'type_key' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'name' => ['type'=>'varchar(191)','nullable'=>false,'auto_increment'=>false],
                    'is_active' => ['type'=>'tinyint(1)','nullable'=>false,'auto_increment'=>false],
                    'mode' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'endpoint_ref' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'secret_ref' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'retry_policy_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'last_success_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_error_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_error_code' => ['type'=>'varchar(96)','nullable'=>true,'auto_increment'=>false],
                    'last_error_message' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'created_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'destination_uuid_idx' => ['unique'=>true,'columns'=>['destination_uuid']],
                    'tenant_active_idx' => ['unique'=>false,'columns'=>['tenant_id','is_active','status']],
                    'scope_idx' => ['unique'=>false,'columns'=>['tenant_id','client_id','project_id']],
                    'type_status_idx' => ['unique'=>false,'columns'=>['type_key','status']],
                    'updated_idx' => ['unique'=>false,'columns'=>['updated_at']],
                ],
            ],
            'cloud_routing_rules' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'rule_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'destination_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'event_pattern' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'action_name' => ['type'=>'varchar(16)','nullable'=>false,'auto_increment'=>false],
                    'is_active' => ['type'=>'tinyint(1)','nullable'=>false,'auto_increment'=>false],
                    'priority' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'created_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'rule_uuid_idx' => ['unique'=>true,'columns'=>['rule_uuid']],
                    'destination_active_idx' => ['unique'=>false,'columns'=>['destination_id','is_active','priority']],
                    'scope_idx' => ['unique'=>false,'columns'=>['tenant_id','client_id','project_id']],
                    'event_pattern_idx' => ['unique'=>false,'columns'=>['event_pattern']],
                    'updated_idx' => ['unique'=>false,'columns'=>['updated_at']],
                ],
            ],
            'client_onboarding_plans' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'plan_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'template_key' => ['type'=>'varchar(48)','nullable'=>false,'auto_increment'=>false],
                    'destination_type' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'destination_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'routing_rule_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'current_step' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'target_tenant_id' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'resource_scope' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'endpoint_ref' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'secret_ref' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'auth_strategy' => ['type'=>'varchar(32)','nullable'=>true,'auto_increment'=>false],
                    'event_pattern' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'hourly_limit' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'safe_mode' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'config_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'readiness_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'created_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'updated_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'plan_uuid_idx' => ['unique'=>true,'columns'=>['plan_uuid']],
                    'client_status_idx' => ['unique'=>false,'columns'=>['client_id','status']],
                    'project_status_idx' => ['unique'=>false,'columns'=>['project_id','status']],
                    'destination_idx' => ['unique'=>false,'columns'=>['destination_id']],
                    'template_idx' => ['unique'=>false,'columns'=>['template_key']],
                    'updated_idx' => ['unique'=>false,'columns'=>['updated_at']],
                ],
            ],
            'license_tenants' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'tenant_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'plan_slug' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'customer_code' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'max_installations' => ['type'=>'smallint unsigned','nullable'=>false,'auto_increment'=>false],
                    'expires_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'remote_license_id' => ['type'=>'varchar(128)','nullable'=>true,'auto_increment'=>false],
                    'key_fingerprint_sha256' => ['type'=>'char(64)','nullable'=>true,'auto_increment'=>false],
                    'masked_key' => ['type'=>'varchar(191)','nullable'=>true,'auto_increment'=>false],
                    'binding_fingerprint_sha256' => ['type'=>'char(64)','nullable'=>false,'auto_increment'=>false],
                    'capabilities_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'limits_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'notes' => ['type'=>'text','nullable'=>true,'auto_increment'=>false],
                    'created_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'updated_by' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'issued_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'revoked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'last_sync_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'tenant_uuid_idx' => ['unique'=>true,'columns'=>['tenant_uuid']],
                    'client_idx' => ['unique'=>true,'columns'=>['client_id']],
                    'plan_status_idx' => ['unique'=>false,'columns'=>['plan_slug','status']],
                    'remote_license_idx' => ['unique'=>false,'columns'=>['remote_license_id']],
                    'updated_idx' => ['unique'=>false,'columns'=>['updated_at']],
                ],
            ],
            'license_installations' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'installation_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'tenant_id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'domain' => ['type'=>'varchar(191)','nullable'=>false,'auto_increment'=>false],
                    'domain_fingerprint' => ['type'=>'varchar(128)','nullable'=>false,'auto_increment'=>false],
                    'site_url' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'activation_id' => ['type'=>'varchar(128)','nullable'=>true,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'plugin_version' => ['type'=>'varchar(32)','nullable'=>true,'auto_increment'=>false],
                    'last_seen_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'activated_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'revoked_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                    'meta_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                    'updated_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'installation_uuid_idx' => ['unique'=>true,'columns'=>['installation_uuid']],
                    'tenant_domain_idx' => ['unique'=>true,'columns'=>['tenant_id','domain_fingerprint']],
                    'tenant_status_idx' => ['unique'=>false,'columns'=>['tenant_id','status']],
                    'activation_idx' => ['unique'=>false,'columns'=>['activation_id']],
                    'seen_idx' => ['unique'=>false,'columns'=>['last_seen_at']],
                    'updated_idx' => ['unique'=>false,'columns'=>['updated_at']],
                ],
            ],
            'governance_ledger' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'entry_uuid' => ['type'=>'char(36)','nullable'=>false,'auto_increment'=>false],
                    'event_name' => ['type'=>'varchar(96)','nullable'=>false,'auto_increment'=>false],
                    'entity_type' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'entity_ref' => ['type'=>'varchar(191)','nullable'=>true,'auto_increment'=>false],
                    'actor_user_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'source' => ['type'=>'varchar(32)','nullable'=>false,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'destination_uuid' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'correlation_id' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'reason' => ['type'=>'varchar(500)','nullable'=>true,'auto_increment'=>false],
                    'details_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'previous_hash' => ['type'=>'char(64)','nullable'=>false,'auto_increment'=>false],
                    'entry_hash' => ['type'=>'char(64)','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>false,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'entry_uuid_idx' => ['unique'=>true,'columns'=>['entry_uuid']],
                    'entry_hash_idx' => ['unique'=>true,'columns'=>['entry_hash']],
                    'event_idx' => ['unique'=>false,'columns'=>['event_name']],
                    'entity_idx' => ['unique'=>false,'columns'=>['entity_type','entity_ref']],
                    'actor_idx' => ['unique'=>false,'columns'=>['actor_user_id']],
                    'scope_idx' => ['unique'=>false,'columns'=>['client_id','project_id']],
                    'destination_idx' => ['unique'=>false,'columns'=>['destination_uuid']],
                    'correlation_idx' => ['unique'=>false,'columns'=>['correlation_id']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                ],
            ],
            'audit_log' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'action_name' => [
                        'type' => 'varchar(96)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'entity_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'entity_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'actor_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'submission_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'correlation_id' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'before_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'after_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'action_idx' => [
                        'unique' => false,
                        'columns' => ['action_name'],
                    ],
                    'entity_idx' => [
                        'unique' => false,
                        'columns' => ['entity_type', 'entity_id'],
                    ],
                    'actor_idx' => [
                        'unique' => false,
                        'columns' => ['actor_user_id'],
                    ],
                    'route_idx' => [
                        'unique' => false,
                        'columns' => ['route_id'],
                    ],
                    'submission_idx' => [
                        'unique' => false,
                        'columns' => ['submission_id'],
                    ],
                    'correlation_idx' => [
                        'unique' => false,
                        'columns' => ['correlation_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'reconciliation_runs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'run_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'run_mode' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'initiated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'summary_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'details_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'started_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'type_mode_idx' => [
                        'unique' => false,
                        'columns' => ['run_type', 'run_mode'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'initiated_idx' => [
                        'unique' => false,
                        'columns' => ['initiated_by'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'ai_usage_logs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'module_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'model_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'duration_ms' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'input_chars' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'output_chars' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'input_tokens' => [
                        'type' => 'int unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'output_tokens' => [
                        'type' => 'int unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'usage_source' => [
                        'type' => 'varchar(16)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'estimated_cost_eur' => [
                        'type' => 'decimal(14,6)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'prompt_version' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'risk_flags_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'feature_key' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'request_kind' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'error_code' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'error_message' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'module_idx' => [
                        'unique' => false,
                        'columns' => ['module_key'],
                    ],
                    'provider_idx' => [
                        'unique' => false,
                        'columns' => ['provider'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'prompt_version_idx' => [
                        'unique' => false,
                        'columns' => ['prompt_version'],
                    ],
                    'client_created_idx' => [
                        'unique' => false,
                        'columns' => ['client_id','created_at'],
                    ],
                    'project_created_idx' => [
                        'unique' => false,
                        'columns' => ['project_id','created_at'],
                    ],
                    'feature_created_idx' => [
                        'unique' => false,
                        'columns' => ['feature_key','created_at'],
                    ],
                    'request_kind_idx' => [
                        'unique' => false,
                        'columns' => ['request_kind','created_at'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'privacy_requests' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'request_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'request_type' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'subject_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'subject_email' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'notes' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'result_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'requested_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'completed_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'requested_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'request_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['request_uuid'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status', 'requested_at'],
                    ],
                    'subject_user_idx' => [
                        'unique' => false,
                        'columns' => ['subject_user_id'],
                    ],
                    'subject_email_idx' => [
                        'unique' => false,
                        'columns' => ['subject_email'],
                    ],
                    'scope_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'project_id'],
                    ],
                ],
            ],
            'privacy_retention_runs' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'run_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'run_mode' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'eligible_count' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'affected_count' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'summary_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'initiated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'started_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'run_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['run_uuid'],
                    ],
                    'mode_created_idx' => [
                        'unique' => false,
                        'columns' => ['run_mode', 'created_at'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'initiated_idx' => [
                        'unique' => false,
                        'columns' => ['initiated_by'],
                    ],
                ],
            ],
            'briefings' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'year_no' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'week_no' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'valid_from' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'valid_to' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'version_no' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_filename' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source_hash' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'import_summary_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'published_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'published_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'week_idx' => [
                        'unique' => false,
                        'columns' => ['year_no', 'week_no'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'published_idx' => [
                        'unique' => false,
                        'columns' => ['published_at'],
                    ],
                ],
            ],
            'briefing_sections' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'briefing_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'retailer_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'section_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'content_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'briefing_idx' => [
                        'unique' => false,
                        'columns' => ['briefing_id'],
                    ],
                    'retailer_idx' => [
                        'unique' => false,
                        'columns' => ['retailer_key'],
                    ],
                    'type_idx' => [
                        'unique' => false,
                        'columns' => ['section_type'],
                    ],
                    'sort_idx' => [
                        'unique' => false,
                        'columns' => ['sort_order'],
                    ],
                ],
            ],
            'briefing_store_aliases' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'retailer_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_alias' => [
                        'unique' => true,
                        'columns' => ['client_id', 'project_id', 'retailer_key', 'normalized_name'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'retailer_idx' => [
                        'unique' => false,
                        'columns' => ['retailer_key'],
                    ],
                ],
            ],
            'briefing_store_items' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'briefing_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'retailer_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_store_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_store_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'alias_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'item_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'details_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'valid_from' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'valid_to' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'briefing_idx' => [
                        'unique' => false,
                        'columns' => ['briefing_id'],
                    ],
                    'alias_idx' => [
                        'unique' => false,
                        'columns' => ['alias_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'retailer_idx' => [
                        'unique' => false,
                        'columns' => ['retailer_key'],
                    ],
                    'type_idx' => [
                        'unique' => false,
                        'columns' => ['item_type'],
                    ],
                ],
            ],
            'briefing_recipients' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'briefing_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'version_no' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_ids_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'retailer_keys_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'first_viewed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_viewed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'acknowledged_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_recipient' => [
                        'unique' => true,
                        'columns' => ['briefing_id', 'user_id', 'version_no'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'briefing_idx' => [
                        'unique' => false,
                        'columns' => ['briefing_id'],
                    ],
                    'ack_idx' => [
                        'unique' => false,
                        'columns' => ['acknowledged_at'],
                    ],
                ],
            ],
            'implementation_documents' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'version_no' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'valid_from' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'valid_to' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'notes_text' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source_filename' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source_hash' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'import_summary_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'published_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'published_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'validity_idx' => [
                        'unique' => false,
                        'columns' => ['valid_from', 'valid_to'],
                    ],
                    'published_idx' => [
                        'unique' => false,
                        'columns' => ['published_at'],
                    ],
                ],
            ],
            'implementation_sections' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'document_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'section_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'title' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'summary_text' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'metadata_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'document_idx' => [
                        'unique' => false,
                        'columns' => ['document_id'],
                    ],
                    'section_key_idx' => [
                        'unique' => false,
                        'columns' => ['section_key'],
                    ],
                    'sort_idx' => [
                        'unique' => false,
                        'columns' => ['sort_order'],
                    ],
                ],
            ],
            'implementation_store_aliases' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'alias_key' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_store_code' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_alias' => [
                        'unique' => true,
                        'columns' => ['client_id', 'project_id', 'alias_key'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'code_idx' => [
                        'unique' => false,
                        'columns' => ['external_store_code'],
                    ],
                    'name_idx' => [
                        'unique' => false,
                        'columns' => ['normalized_name'],
                    ],
                ],
            ],
            'implementation_items' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'document_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'section_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'alias_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'external_store_code' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_store_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_store_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'sales_rep' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'implementation_type' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'planned_date' => [
                        'type' => 'date',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'planned_date_text' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'observations' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'materials_proposed' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'materials_missing' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'materials_implemented' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'details_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sort_order' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'document_idx' => [
                        'unique' => false,
                        'columns' => ['document_id'],
                    ],
                    'section_idx' => [
                        'unique' => false,
                        'columns' => ['section_id'],
                    ],
                    'alias_idx' => [
                        'unique' => false,
                        'columns' => ['alias_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status_key'],
                    ],
                    'code_idx' => [
                        'unique' => false,
                        'columns' => ['external_store_code'],
                    ],
                    'sort_idx' => [
                        'unique' => false,
                        'columns' => ['sort_order'],
                    ],
                ],
            ],
            'expenses' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'expense_date' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'category' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'merchant_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'issuer_tax_id' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'document_no' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'atcud' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'description' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'payment_method' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'amount_net' => [
                        'type' => 'decimal(14,2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'tax_amount' => [
                        'type' => 'decimal(14,2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'amount_total' => [
                        'type' => 'decimal(14,2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'currency' => [
                        'type' => 'varchar(3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'capture_source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'verification_state' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'qr_payload' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'qr_fields_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'receipt_path' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'receipt_mime' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'receipt_original_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'receipt_size' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'duplicate_hash' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'duplicate_of_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'reviewed_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'reviewed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'date_idx' => [
                        'unique' => false,
                        'columns' => ['expense_date'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'category_idx' => [
                        'unique' => false,
                        'columns' => ['category'],
                    ],
                    'duplicate_idx' => [
                        'unique' => false,
                        'columns' => ['duplicate_hash'],
                    ],
                    'duplicate_of_idx' => [
                        'unique' => false,
                        'columns' => ['duplicate_of_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'stock_batches' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'insignia' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'snapshot_date' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_filename' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sheet_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'header_row' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'row_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'store_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'product_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'matched_store_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'unmatched_row_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'total_stock' => [
                        'type' => 'decimal(18,3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'zero_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'low_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'negative_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'import_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'mapping_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'published_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'import_hash_idx' => [
                        'unique' => true,
                        'columns' => ['import_hash'],
                    ],
                    'scope_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'project_id'],
                    ],
                    'snapshot_idx' => [
                        'unique' => false,
                        'columns' => ['snapshot_date'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'insignia_idx' => [
                        'unique' => false,
                        'columns' => ['insignia'],
                    ],
                ],
            ],
            'stock_items' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'batch_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_store_key' => [
                        'type' => 'char(40)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'store_external_ref' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'store_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'locality' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'article_ref' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'ean' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'product_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'unit' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'conversion_factor' => [
                        'type' => 'decimal(14,4)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'stock_total' => [
                        'type' => 'decimal(18,3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'match_confidence' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'match_method' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'cardex_item_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'product_match_confidence' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'product_match_method' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'batch_idx' => [
                        'unique' => false,
                        'columns' => ['batch_id'],
                    ],
                    'scope_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'project_id'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'store_key_idx' => [
                        'unique' => false,
                        'columns' => ['source_store_key'],
                    ],
                    'ean_idx' => [
                        'unique' => false,
                        'columns' => ['ean'],
                    ],
                    'article_idx' => [
                        'unique' => false,
                        'columns' => ['article_ref'],
                    ],
                    'cardex_item_idx' => [
                        'unique' => false,
                        'columns' => ['cardex_item_id'],
                    ],
                    'stock_idx' => [
                        'unique' => false,
                        'columns' => ['stock_total'],
                    ],
                ],
            ],
            'stock_store_mappings' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'insignia' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_store_key' => [
                        'type' => 'char(40)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_store_ref' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source_store_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'confidence' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'match_method' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_scope_store' => [
                        'unique' => true,
                        'columns' => ['client_id', 'project_id', 'insignia', 'source_store_key'],
                    ],
                    'location_idx' => [
                        'unique' => false,
                        'columns' => ['location_id'],
                    ],
                    'project_idx' => [
                        'unique' => false,
                        'columns' => ['project_id'],
                    ],
                ],
            ],
            'stock_views' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'batch_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'first_viewed_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'last_viewed_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'view_count' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_batch_user' => [
                        'unique' => true,
                        'columns' => ['batch_id', 'user_id'],
                    ],
                    'batch_idx' => [
                        'unique' => false,
                        'columns' => ['batch_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'viewed_idx' => [
                        'unique' => false,
                        'columns' => ['last_viewed_at'],
                    ],
                ],
            ],
            'mapping_records' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'mapping_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'entity_type' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_module' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_scope_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_record_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_ref' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_label' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'target_type' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'target_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'confidence' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'match_method' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_locked' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'candidate_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_synced_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'mapping_uuid_idx' => [
                        'unique' => true,
                        'columns' => ['mapping_uuid'],
                    ],
                    'source_identity' => [
                        'unique' => true,
                        'columns' => ['source_module', 'client_id', 'project_id', 'source_key'],
                    ],
                    'scope_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'project_id'],
                    ],
                    'entity_idx' => [
                        'unique' => false,
                        'columns' => ['entity_type'],
                    ],
                    'module_idx' => [
                        'unique' => false,
                        'columns' => ['source_module'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'target_idx' => [
                        'unique' => false,
                        'columns' => ['target_type', 'target_id'],
                    ],
                    'confidence_idx' => [
                        'unique' => false,
                        'columns' => ['confidence'],
                    ],
                ],
            ],
            'mapping_aliases' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'entity_type' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'alias_label' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'normalized_alias' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'target_type' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'target_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'confidence' => [
                        'type' => 'tinyint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source_module' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'use_count' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'alias_scope_idx' => [
                        'unique' => true,
                        'columns' => ['entity_type', 'client_id', 'project_id', 'normalized_alias'],
                    ],
                    'target_idx' => [
                        'unique' => false,
                        'columns' => ['target_type', 'target_id'],
                    ],
                    'use_idx' => [
                        'unique' => false,
                        'columns' => ['use_count'],
                    ],
                ],
            ],
            'mapping_history' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'mapping_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'action_name' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'before_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'after_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'reason_text' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'batch_uuid' => [
                        'type' => 'char(36)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'actor_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'mapping_idx' => [
                        'unique' => false,
                        'columns' => ['mapping_id'],
                    ],
                    'action_idx' => [
                        'unique' => false,
                        'columns' => ['action_name'],
                    ],
                    'batch_idx' => [
                        'unique' => false,
                        'columns' => ['batch_uuid'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'form_drafts' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'form_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'binding_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'route_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'location_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'report_day' => [
                        'type' => 'date',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'schema_revision' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'revision' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_instance_id' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_updated_at' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'payload_json' => [
                        'type' => 'longtext',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'uniq_draft_scope' => [
                        'unique' => true,
                        'columns' => ['user_id', 'form_id', 'binding_id', 'client_id', 'project_id', 'route_id', 'route_stop_id', 'location_id', 'report_day'],
                    ],
                    'user_updated_idx' => [
                        'unique' => false,
                        'columns' => ['user_id', 'updated_at'],
                    ],
                    'expires_idx' => [
                        'unique' => false,
                        'columns' => ['expires_at'],
                    ],
                    'location_day_idx' => [
                        'unique' => false,
                        'columns' => ['location_id', 'report_day'],
                    ],
                ],
            ],
            'form_draft_files' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'draft_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'token' => [
                        'type' => 'char(48)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'input_name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'input_ordinal' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'file_index' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'original_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'mime_type' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'file_size' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'file_signature' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'storage_path' => [
                        'type' => 'text',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'token_idx' => [
                        'unique' => true,
                        'columns' => ['token'],
                    ],
                    'draft_input_idx' => [
                        'unique' => false,
                        'columns' => ['draft_id', 'input_name', 'input_ordinal'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'expires_idx' => [
                        'unique' => false,
                        'columns' => ['expires_at'],
                    ],
                ],
            ],
            'events' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'route_stop_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'event_type' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'payload_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'stop' => [
                        'unique' => false,
                        'columns' => ['route_stop_id'],
                    ],
                    'user' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'type' => [
                        'unique' => false,
                        'columns' => ['event_type'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'mobile_sessions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'device_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'device_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'platform' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'app_version' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'access_token_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'refresh_token_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'access_expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'refresh_expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'revoked_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_seen_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_ip' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'user_agent' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'access_hash_idx' => [
                        'unique' => true,
                        'columns' => ['access_token_hash'],
                    ],
                    'refresh_hash_idx' => [
                        'unique' => true,
                        'columns' => ['refresh_token_hash'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'device_idx' => [
                        'unique' => false,
                        'columns' => ['device_id'],
                    ],
                    'refresh_expiry_idx' => [
                        'unique' => false,
                        'columns' => ['refresh_expires_at'],
                    ],
                ],
            ],
            'mobile_mutations' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'mutation_id' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'device_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'mutation_type' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'response_code' => [
                        'type' => 'int',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'response_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'mutation_user_idx' => [
                        'unique' => true,
                        'columns' => ['mutation_id', 'user_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'device_idx' => [
                        'unique' => false,
                        'columns' => ['device_id'],
                    ],
                    'expires_idx' => [
                        'unique' => false,
                        'columns' => ['expires_at'],
                    ],
                ],
            ],
            'mobile_sync_devices' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'device_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'platform' => [
                        'type' => 'varchar(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'app_version' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'cache_version' => [
                        'type' => 'varchar(128)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'queue_pending' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'queue_failed' => [
                        'type' => 'int unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'last_cache_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_sync_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_error' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_seen_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'user_device_idx' => [
                        'unique' => true,
                        'columns' => ['user_id', 'device_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'device_idx' => [
                        'unique' => false,
                        'columns' => ['device_id'],
                    ],
                    'seen_idx' => [
                        'unique' => false,
                        'columns' => ['last_seen_at'],
                    ],
                    'sync_idx' => [
                        'unique' => false,
                        'columns' => ['last_sync_at'],
                    ],
                ],
            ],
            'saas_plans' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'slug' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'name' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'description' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'monthly_price' => [
                        'type' => 'decimal(12,2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'annual_price' => [
                        'type' => 'decimal(12,2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'currency' => [
                        'type' => 'varchar(3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'capabilities_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'limits_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'is_system' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'slug_idx' => [
                        'unique' => true,
                        'columns' => ['slug'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                ],
            ],
            'client_subscriptions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'plan_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'starts_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'trial_ends_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'renews_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'capability_overrides_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'limit_overrides_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status_reason' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'notes' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_idx' => [
                        'unique' => true,
                        'columns' => ['client_id'],
                    ],
                    'plan_idx' => [
                        'unique' => false,
                        'columns' => ['plan_id'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                    'renew_idx' => [
                        'unique' => false,
                        'columns' => ['renews_at'],
                    ],
                ],
            ],
            'usage_counters' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'metric' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'period_key' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'quantity' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_metric_period' => [
                        'unique' => true,
                        'columns' => ['client_id', 'metric', 'period_key'],
                    ],
                    'metric_idx' => [
                        'unique' => false,
                        'columns' => ['metric'],
                    ],
                    'period_idx' => [
                        'unique' => false,
                        'columns' => ['period_key'],
                    ],
                ],
            ],
            'billing_prices' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'plan_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_interval' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_product_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'external_price_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'amount_minor' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'currency' => [
                        'type' => 'varchar(3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'is_active' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'plan_env_interval_idx' => [
                        'unique' => true,
                        'columns' => ['plan_id', 'provider', 'environment', 'billing_interval'],
                    ],
                    'price_idx' => [
                        'unique' => false,
                        'columns' => ['external_price_id'],
                    ],
                    'active_idx' => [
                        'unique' => false,
                        'columns' => ['is_active'],
                    ],
                ],
            ],
            'billing_customers' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_customer_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'email' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'billing_name' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'tax_id' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'country' => [
                        'type' => 'varchar(2)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_provider_env_idx' => [
                        'unique' => true,
                        'columns' => ['client_id', 'provider', 'environment'],
                    ],
                    'external_customer_idx' => [
                        'unique' => false,
                        'columns' => ['external_customer_id'],
                    ],
                    'status_idx' => [
                        'unique' => false,
                        'columns' => ['status'],
                    ],
                ],
            ],
            'billing_subscriptions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_customer_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'external_subscription_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'plan_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_interval' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'current_period_start' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'current_period_end' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'trial_end' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'cancel_at_period_end' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'grace_until' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'latest_invoice_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'client_provider_env_idx' => [
                        'unique' => true,
                        'columns' => ['client_id', 'provider', 'environment'],
                    ],
                    'external_subscription_idx' => [
                        'unique' => false,
                        'columns' => ['external_subscription_id'],
                    ],
                    'status_grace_idx' => [
                        'unique' => false,
                        'columns' => ['status', 'grace_until'],
                    ],
                    'plan_idx' => [
                        'unique' => false,
                        'columns' => ['plan_id'],
                    ],
                    'billing_user_idx' => [
                        'unique' => false,
                        'columns' => ['billing_user_id'],
                    ],
                    'billing_user_status_idx' => [
                        'unique' => false,
                        'columns' => ['billing_user_id', 'status'],
                    ],
                    'client_environment_status_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'environment', 'status'],
                    ],
                ],
            ],
            'billing_invoices' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'external_invoice_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_subscription_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'invoice_number' => [
                        'type' => 'varchar(96)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'currency' => [
                        'type' => 'varchar(3)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'subtotal_minor' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'tax_minor' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'total_minor' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'amount_paid_minor' => [
                        'type' => 'bigint',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'hosted_invoice_url' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'invoice_pdf' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'due_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'paid_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'period_start' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'period_end' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'external_invoice_idx' => [
                        'unique' => true,
                        'columns' => ['environment', 'external_invoice_id'],
                    ],
                    'client_status_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'status'],
                    ],
                    'subscription_idx' => [
                        'unique' => false,
                        'columns' => ['external_subscription_id'],
                    ],
                    'paid_idx' => [
                        'unique' => false,
                        'columns' => ['paid_at'],
                    ],
                ],
            ],
            'billing_events' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider_event_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'event_type' => [
                        'type' => 'varchar(96)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'payload_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'payload_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'attempts' => [
                        'type' => 'smallint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'error_message' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'processed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'provider_event_idx' => [
                        'unique' => true,
                        'columns' => ['provider', 'environment', 'provider_event_id'],
                    ],
                    'type_status_idx' => [
                        'unique' => false,
                        'columns' => ['event_type', 'status'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'billing_checkout_sessions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'session_token_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'provider' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'external_session_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'plan_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_interval' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'checkout_url' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_by' => [
                        'type' => 'bigint unsigned',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'token_idx' => [
                        'unique' => true,
                        'columns' => ['session_token_hash'],
                    ],
                    'external_session_idx' => [
                        'unique' => false,
                        'columns' => ['external_session_id'],
                    ],
                    'client_status_idx' => [
                        'unique' => false,
                        'columns' => ['client_id', 'status'],
                    ],
                    'billing_user_idx' => [
                        'unique' => false,
                        'columns' => ['billing_user_id'],
                    ],
                    'expires_idx' => [
                        'unique' => false,
                        'columns' => ['expires_at'],
                    ],
                ],
            ],
            'billing_signup_intents' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'token_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'plan_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'billing_interval' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'environment' => [
                        'type' => 'varchar(16)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'email' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'checkout_session_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'expires_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'completed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'token_idx' => [
                        'unique' => true,
                        'columns' => ['token_hash'],
                    ],
                    'user_status_idx' => [
                        'unique' => false,
                        'columns' => ['user_id', 'status'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'plan_idx' => [
                        'unique' => false,
                        'columns' => ['plan_id'],
                    ],
                    'expires_idx' => [
                        'unique' => false,
                        'columns' => ['expires_at'],
                    ],
                ],
            ],
            'billing_consents' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'intent_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'terms_version' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'privacy_version' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'terms_url' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'privacy_url' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'marketing_consent' => [
                        'type' => 'tinyint(1)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'source' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'ip_hash' => [
                        'type' => 'char(32)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'user_agent' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'accepted_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'intent_idx' => [
                        'unique' => true,
                        'columns' => ['intent_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'accepted_idx' => [
                        'unique' => false,
                        'columns' => ['accepted_at'],
                    ],
                ],
            ],
            'billing_email_log' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'message_key' => [
                        'type' => 'varchar(191)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'template_key' => [
                        'type' => 'varchar(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'recipient' => [
                        'type' => 'varchar(255)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'subject' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'status' => [
                        'type' => 'varchar(24)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'error_message' => [
                        'type' => 'text',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'intent_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'meta_json' => [
                        'type' => 'longtext',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'sent_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'message_key_idx' => [
                        'unique' => true,
                        'columns' => ['message_key'],
                    ],
                    'template_status_idx' => [
                        'unique' => false,
                        'columns' => ['template_key', 'status'],
                    ],
                    'client_idx' => [
                        'unique' => false,
                        'columns' => ['client_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'created_idx' => [
                        'unique' => false,
                        'columns' => ['created_at'],
                    ],
                ],
            ],
            'api_usage_logs' => [
                'required' => true,
                'source' => 'activator',
                'label' => 'Consumo e custos estimados de APIs',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'provider' => ['type'=>'varchar(48)','nullable'=>false,'auto_increment'=>false],
                    'operation' => ['type'=>'varchar(64)','nullable'=>false,'auto_increment'=>false],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'cache_status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'request_count' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'units' => ['type'=>'decimal(18,4)','nullable'=>false,'auto_increment'=>false],
                    'estimated_cost_eur' => ['type'=>'decimal(18,8)','nullable'=>false,'auto_increment'=>false],
                    'avoided_cost_eur' => ['type'=>'decimal(18,8)','nullable'=>false,'auto_increment'=>false],
                    'duration_ms' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'http_status' => ['type'=>'smallint unsigned','nullable'=>true,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'route_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'user_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'meta_json' => ['type'=>'longtext','nullable'=>true,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'provider_idx' => ['unique'=>false,'columns'=>['provider']],
                    'operation_idx' => ['unique'=>false,'columns'=>['operation']],
                    'status_idx' => ['unique'=>false,'columns'=>['status']],
                    'cache_idx' => ['unique'=>false,'columns'=>['cache_status']],
                    'client_idx' => ['unique'=>false,'columns'=>['client_id']],
                    'project_idx' => ['unique'=>false,'columns'=>['project_id']],
                    'route_idx' => ['unique'=>false,'columns'=>['route_id']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                    'provider_created_idx' => ['unique'=>false,'columns'=>['provider','created_at']],
                ],
            ],
            'media_telemetry' => [
                'required' => true,
                'source' => 'activator',
                'label' => 'Telemetria técnica de fotografias e uploads',
                'columns' => [
                    'id' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>true],
                    'status' => ['type'=>'varchar(24)','nullable'=>false,'auto_increment'=>false],
                    'error_code' => ['type'=>'varchar(64)','nullable'=>true,'auto_increment'=>false],
                    'form_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'client_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'project_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'route_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'location_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'user_id' => ['type'=>'bigint unsigned','nullable'=>true,'auto_increment'=>false],
                    'file_count' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'original_bytes' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'optimized_bytes' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'server_received_bytes' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'saved_bytes' => ['type'=>'bigint unsigned','nullable'=>false,'auto_increment'=>false],
                    'optimized_files' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'duplicate_files' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'retry_count' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'processing_ms' => ['type'=>'int unsigned','nullable'=>false,'auto_increment'=>false],
                    'created_at' => ['type'=>'datetime','nullable'=>true,'auto_increment'=>false],
                ],
                'indexes' => [
                    'PRIMARY' => ['unique'=>true,'columns'=>['id']],
                    'status_idx' => ['unique'=>false,'columns'=>['status']],
                    'error_idx' => ['unique'=>false,'columns'=>['error_code']],
                    'form_idx' => ['unique'=>false,'columns'=>['form_id']],
                    'client_idx' => ['unique'=>false,'columns'=>['client_id']],
                    'project_idx' => ['unique'=>false,'columns'=>['project_id']],
                    'route_idx' => ['unique'=>false,'columns'=>['route_id']],
                    'created_idx' => ['unique'=>false,'columns'=>['created_at']],
                    'status_created_idx' => ['unique'=>false,'columns'=>['status','created_at']],
                ],
            ],
            'mobile_web_sessions' => [
                'required' => true,
                'source' => 'activator',
                'columns' => [
                    'id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => true,
                    ],
                    'ticket_hash' => [
                        'type' => 'char(64)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'mobile_session_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'user_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'device_id' => [
                        'type' => 'varchar(191)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'target' => [
                        'type' => 'varchar(32)',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'client_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'project_id' => [
                        'type' => 'bigint unsigned',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'ticket_expires_at' => [
                        'type' => 'datetime',
                        'nullable' => false,
                        'auto_increment' => false,
                    ],
                    'consumed_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'web_token_hash' => [
                        'type' => 'char(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'web_expires_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'revoked_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'last_seen_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_ip' => [
                        'type' => 'varchar(64)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'user_agent' => [
                        'type' => 'varchar(255)',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'created_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                    'updated_at' => [
                        'type' => 'datetime',
                        'nullable' => true,
                        'auto_increment' => false,
                    ],
                ],
                'indexes' => [
                    'PRIMARY' => [
                        'unique' => true,
                        'columns' => ['id'],
                    ],
                    'ticket_hash_idx' => [
                        'unique' => true,
                        'columns' => ['ticket_hash'],
                    ],
                    'web_token_idx' => [
                        'unique' => false,
                        'columns' => ['web_token_hash'],
                    ],
                    'mobile_session_idx' => [
                        'unique' => false,
                        'columns' => ['mobile_session_id'],
                    ],
                    'user_idx' => [
                        'unique' => false,
                        'columns' => ['user_id'],
                    ],
                    'target_idx' => [
                        'unique' => false,
                        'columns' => ['target'],
                    ],
                    'ticket_expiry_idx' => [
                        'unique' => false,
                        'columns' => ['ticket_expires_at'],
                    ],
                    'web_expiry_idx' => [
                        'unique' => false,
                        'columns' => ['web_expires_at'],
                    ],
                ],
            ],
            'report_context_questions' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Perguntas contextuais',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'categories' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Categorias operacionais',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'route_location_snapshot' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Snapshot de rotas',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'location_import_batches' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Lotes de importação',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'integration_logs' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Logs de integrações',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'campaign_locations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Campanhas e PDVs',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'analytics_alerts' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Alertas analíticos',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'intelligence_recommendations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Recomendações Intelligence',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'intelligence_feedback_events' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Feedback e outcomes Intelligence',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'security_events' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Eventos de segurança',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'observability_events' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Eventos de observabilidade',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'system_alerts' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Alertas do sistema',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'mobile_device_controls' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Controlo de dispositivos',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'api_keys' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Chaves de API',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'webhooks' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Webhooks',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'webhook_deliveries' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Entregas de webhooks',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'scope_memberships' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Índice de permissões',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'scope_index_state' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Estado do índice de permissões',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'performance_samples' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Amostras de performance',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Cardex de produtos',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_items' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Itens de cardex',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_locations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Locais de cardex',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_item_locations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Produtos por local',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_store_overrides' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Exceções de cardex por loja',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_store_state' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Estado vivo do cardex por loja',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_store_revisions' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Revisões do cardex por loja',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'product_cardex_store_revision_items' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Diferenças versionadas do cardex por loja',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'schema_migrations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Histórico de migrações',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'migration_batches' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Migrações em lotes',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'shopping_batches' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Lotes Shopping de verificação de preços',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'shopping_assignments' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Atribuições Shopping por loja',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'shopping_submissions' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Submissões Shopping',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
            'shopping_insignia_locations' => [
                'required' => true,
                'source' => 'module',
                'label' => 'Mapeamento aprendido de lojas por insígnia Shopping',
                'columns' => [
                ],
                'indexes' => [
                ],
            ],
        ];
    }

    public static function table(string $suffix): array {
        $tables = self::tables();
        return $tables[$suffix] ?? [];
    }

    public static function requiredSuffixes(): array {
        return array_keys(array_filter(self::tables(), static fn(array $table): bool => !empty($table['required'])));
    }

    public static function criticalSuffixes(): array {
        return [
            'clients','projects','locations','location_profiles','routes','route_stops','forms',
            'form_submissions','form_submission_answers','campaign_locations',
            'mobile_sessions','mobile_mutations','queue_jobs','mail_outbox','schema_migrations',
        ];
    }
}

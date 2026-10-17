<?php
define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/src/Support/Plugin.php';

use RoutesPro\Support\Plugin;

function media_case(string $name, array $server, array $request, bool $expected): void {
    $_SERVER = $server;
    $_REQUEST = $request;
    $actual = Plugin::isWordPressMediaProcessingRequest();
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL ' . $name . ' expected=' . ($expected ? 'true' : 'false') . ' actual=' . ($actual ? 'true' : 'false') . "\n");
        exit(1);
    }
    echo 'PASS ' . $name . "\n";
}

media_case('async_upload', ['SCRIPT_NAME'=>'/wp-admin/async-upload.php','REQUEST_URI'=>'/wp-admin/async-upload.php'], [], true);
media_case('ajax_upload_attachment', ['SCRIPT_NAME'=>'/wp-admin/admin-ajax.php','REQUEST_URI'=>'/wp-admin/admin-ajax.php'], ['action'=>'upload-attachment'], true);
media_case('ajax_image_editor', ['SCRIPT_NAME'=>'/wp-admin/admin-ajax.php','REQUEST_URI'=>'/wp-admin/admin-ajax.php'], ['action'=>'image-editor'], true);
media_case('rest_media', ['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/wp-json/wp/v2/media'], [], true);
media_case('rest_route_media', ['SCRIPT_NAME'=>'/index.php','REQUEST_URI'=>'/?rest_route=/wp/v2/media'], ['rest_route'=>'/wp/v2/media'], true);
media_case('fieldflow_submit', ['SCRIPT_NAME'=>'/wp-admin/admin-post.php','REQUEST_URI'=>'/wp-admin/admin-post.php'], ['action'=>'routespro_submit_form'], false);
media_case('unrelated_admin', ['SCRIPT_NAME'=>'/wp-admin/admin-ajax.php','REQUEST_URI'=>'/wp-admin/admin-ajax.php'], ['action'=>'heartbeat'], false);

echo "WORDPRESS MEDIA ISOLATION 9AR RUNTIME OK\n";

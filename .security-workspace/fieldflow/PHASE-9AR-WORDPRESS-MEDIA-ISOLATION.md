# FieldFlow 2.2.562, WordPress Media Isolation 9AR

Contract: `fieldflow-wordpress-media-isolation-v1`

`SOURCE_RELEASE` is `2.2.561` and `STABLE_RELEASE` is `2.2.562`.

## Objective

Keep FieldFlow evidence media fully protected while ensuring native WordPress and third-party site media processing does not inherit FieldFlow runtime cost or upload policy.

## Changes

- Native `wp-admin/async-upload.php`, `upload-attachment`, `image-editor` and WordPress REST `/wp/v2/media` requests use a lightweight FieldFlow bootstrap path.
- The lightweight path returns before FieldFlow operational modules, admin AJAX handlers and front/runtime composition are registered, leaving more PHP memory headroom for GD or Imagick image sub-size generation.
- `UploadGuard` registers its WordPress upload filters only when the current request belongs to FieldFlow.
- Core WordPress media processing is explicitly excluded from FieldFlow upload policy.
- The generic standalone request marker `form_id` is removed from FieldFlow upload scope because Elementor and other plugins can use the same field name.
- FieldFlow actions, REST namespaces, `routespro_form_id`, route-stop context and offline replay continue to activate the FieldFlow upload guard.

## Preserved contracts

- `IMAGE_SIZE_POLICY = 'no-fixed-image-limit'` remains unchanged.
- Resumable evidence media, SHA-256 integrity, private temporary storage, MIME validation and rate limiting remain unchanged for FieldFlow-owned uploads.
- No database schema change.
- `SubmissionPipeline::BUILD` remains `2.2.485`.
- No routing, mobility, mail, conditional-runtime or offline protocol change.

## Certification

Release Gate 2.09.0 adds WordPress Media Isolation 9AR. The Release Manager package validator checks the bootstrap isolation, strict UploadGuard scoping, immutable schema/submission baselines and the standalone runtime verification tool.

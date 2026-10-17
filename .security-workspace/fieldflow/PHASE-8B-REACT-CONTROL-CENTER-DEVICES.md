# Phase 8B · React Control Center Devices

Release: **2.2.487**

## Objective

Extend the progressive React backoffice foundation from the Mobile Control Center publication card to the operational **Devices** control surface, without changing the field runtime.

Contract: `fieldflow-react-control-center-devices-v1`

## Scope

- React island: `device-control-table`
- Page: `routespro-devices`
- Runtime: WordPress `wp-element`
- Backend source: existing `RoutesPro\Mobile\DeviceManager`
- Transport: authenticated WordPress admin-AJAX with capability check and nonce
- Full PHP fallback remains server rendered

## React behaviour

The Devices page supports, without a full page reload:

- search by user, email, device, platform or app version
- platform filter
- 25 / 50 / 100 rows per page
- first / previous / next / last pagination
- health, queue, network, storage and trust state
- Trust / Remove trust
- Enable / Disable offline
- Clear cache generation
- Block / Unblock
- Force logout

All device actions call the same `DeviceManager::performAdminAction()` used by the PHP fallback, so audit and EventBus behaviour remain shared.

## Progressive fallback

The complete PHP filters, pagination, table and POST forms are still rendered inside the island root. React only replaces them after a successful mount. If `wp-element`, the React bundle, AJAX or the kill switch are unavailable, the original page remains usable.

## Safety

- No database migration
- No REST contract change
- No IndexedDB change
- No FlutterFlow change
- No field-form runtime change
- No bundled React or ReactDOM dependency
- Existing `mobile-release-status` React pilot remains available
- SubmissionPipeline component build remains `2.2.485` because that component is unchanged

## Release Manager

The package gate must report `react_devices_control_ok=true` before publication.

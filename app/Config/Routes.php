<?php

use CodeIgniter\Router\RouteCollection;

$routes->setAutoRoute(false);

/**
 * @var RouteCollection $routes
 */
$routes->group('', ['filter' => 'session'], function($routes) {
    $routes->GET('admin', 'UsersController::index');
    $routes->GET('dashboard', 'Dashboard::index');
    $routes->GET('privacy', 'PagesController::privacyPolicy');
    $routes->GET('terms', 'PagesController::termsOfService');
});


$routes->GET('map', 'Map::index');
$routes->GET('map/status', 'Map::status');
$routes->GET('weathermap-config', 'Map::weathermapConfig');
$routes->GET('topology', 'Topology::index');
$routes->GET('topology/diagram', 'Topology::diagram');
// Kept at its historical .html URL (and outside the session filter, like /live) so the
// page can carry a mtime-derived version for network_diagram_live.js. Served by PHP on
// purpose - see Topology::networkDiagram.
$routes->GET('graphify-out/network_diagram.html', 'Topology::networkDiagram');
$routes->GET('topology/devices', 'Topology::devices');
$routes->GET('topology/status', 'Topology::status');

// service('auth')->routes($routes);

$routes->GET('endpoint', 'PfSenseController::endpoint');

$routes->POST('endpoint', 'PfSenseController::endpoint'); 

// Device identity for the pfCentral header pack (add_header.sh on devices).
// Outside the session filter on purpose - callers are firewalls holding their
// DEVICEKEY header, not browser sessions (same as /endpoint and /backup).
$routes->GET('headeridentity', 'PfSenseController::headerIdentity');
$routes->POST('headeridentity', 'PfSenseController::headerIdentity');

// Device key rotation, called once by install.sh while it is
// minting the device's random credential. POST only - it rewrites a row.
$routes->POST('devicekey/rebind', 'PfSenseController::rebindDeviceKey');

// Add Header
$routes->GET('addheader/(:num)', 'AddHeaderController::addHeader/$1');

// Test Receive POST 
// $routes->POST('agent/(:num)', 'PfSenseController::agent/$1'); // Test Receive POST - DEVICEKEY 

$routes->GET('backup', 'PfSenseController::backup');
$routes->POST('backup', 'PfSenseController::backup');

$routes->delete('devices/delete/(:num)', 'DevicesController::delete/$1');

// The device-configuration page: describe a branch, download its pfSense export. The form posts to the
// page it is on, so the address an operator sees is the one that generated the file they are holding.
$routes->GET('config-generator', 'NewConfigController::index');
$routes->POST('config-generator', 'NewConfigController::generate');

// Where that page used to live, twice: `/newconfig` (a subnet typed in directly) and `/newdevice`
// (driven by a location lookup). Install notes, runbooks and bookmarks name them, so they answer.
$routes->GET('newconfig', 'NewConfigController::wasAt/newconfig');
$routes->GET('newdevice', 'NewConfigController::wasAt/newdevice');

$routes->POST('generate-public-key', 'NewConfigController::generatePublicKey');
$routes->POST('get-device-by-location', 'NewConfigController::getDeviceByLocation');
$routes->POST('device-config/save', 'NewConfigController::saveDeviceConfig');
$routes->POST('device-config/delete', 'NewConfigController::deleteDeviceConfig');

$routes->GET('login', '\App\Controllers\LoginController::loginView');
$routes->POST('login', '\App\Controllers\LoginController::loginAction');
$routes->GET('test-ldap', '\App\Controllers\LoginController::testLdap');

service('auth')->routes($routes);

// LoginContoller
$routes->setDefaultController('LoginController');
$routes->GET('/', 'LoginController::index');

// Users & Groups ......
//
// The whole users area is one group, and every action is a POST of its own: the list's modals post
// here for JSON, the profile page and the row buttons post ordinary forms. There is no `register`
// route any more — that override pointed at a controller method that never existed (so a POST to it
// was a 500), and accounts are provisioned here or synced from Active Directory, so Shield's own
// registration routes are switched off in Config\Auth rather than shadowed.
$routes->group('users', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'UsersController::index');
    $routes->get('show/(:num)', 'UsersController::show/$1');
    $routes->post('add', 'UsersController::add');
    $routes->post('update/(:num)', 'UsersController::update/$1');
    $routes->post('delete/(:num)', 'UsersController::delete/$1');
    $routes->post('change-password/(:num)', 'UsersController::changePassword/$1');
    $routes->post('(:num)/toggle-ban', 'UsersController::toggleBan/$1');
    $routes->post('(:num)/toggle-active', 'UsersController::toggleActive/$1');
});

//pfAdminhub
$routes->GET('pflist', 'PfSenseController::index');
// PfSenseController Routes
// Group routes for PfSenseController
$routes->group('pfsense', function ($routes) {
    // Default route for listing firewalls
    $routes->GET('/', 'PfSenseController::index');

    // CRUD operations for devices
    $routes->POST('add', 'PfSenseController::add'); // Add a new device
    $routes->GET('edit/(:num)', 'PfSenseController::edit/$1'); // Edit a device (fetch data)
    $routes->POST('update/(:num)', 'PfSenseController::update/$1'); // Update a device
    // $routes->GET('delete/(:num)', 'PfSenseController::delete/$1'); // Delete a device
    $routes->POST('delete/(:num)', 'PfSenseController::delete/$1'); // Delete a device

    // API and password views
    $routes->GET('apiview/(:num)', 'PfSenseController::apiview/$1'); // View API details
    $routes->GET('creds/(:num)', 'PfSenseController::creds/$1'); // View password

    // Ping a device
    $routes->GET('ping', 'PfSenseController::ping'); // Ping a device (requires IP as query parameter)
    // Traceroute a device
    $routes->GET('trace', 'PfSenseController::trace'); // Ping a device (requires IP as query parameter)

    // Firewall-specific actions
    $routes->GET('pfopen/(:num)', 'PfSenseController::pfopen/$1'); // Open firewall dashboard
    $routes->GET('sshClient/(:num)', 'PfSenseController::sshClient/$1'); // SSH client for a device
    $routes->GET('pfsquid/(:num)', 'PfSenseController::pfsquid/$1'); // Open Squid dashboard
    $routes->GET('bandwidthd/(:num)', 'PfSenseController::bandwidthd/$1'); // Open BandwidthD dashboard
    $routes->GET('pfdarkstat/(:num)', 'PfSenseController::pfdarkstat/$1'); // Open Darkstat dashboard
    $routes->GET('pfdetails/(:num)', 'PfSenseController::pfdetails/$1'); // View firewall details
    $routes->GET('pingDevice/(:num)', 'PfSenseController::pingDevice/$1'); // Real-time device latency and socket probe
    $routes->GET('ping/(:num)', 'PfSenseController::pingDevice/$1'); // Alias for heartbeat probe

    $routes->POST('ajaxList', 'PfSenseController::ajaxList');
    $routes->GET('actionModal/(:num)', 'PfSenseController::actionModal/$1');
    $routes->GET('viewbackup/(:num)', 'PfSenseController::viewDeviceBackups/$1');

    $routes->GET('download-backup/(:num)', 'PfSenseController::downloadBackup/$1');
    
    $routes->POST('search', 'PfSenseController::search');

    // Firewall Backup
    $routes->GET('backupPfSenseConfig/(:num)', 'PfSenseController::backupPfSenseConfig/$1'); // View firewall details
    $routes->POST('purge-backups/(:num)', 'PfSenseController::purgeBackups/$1'); // Purge backups
    $routes->POST('delete-backup/(:num)', 'PfSenseController::deleteSingleBackup/$1'); // Delete single backup
    $routes->GET('preview-backup/(:num)', 'PfSenseController::previewBackup/$1'); // Preview/Inspect backup XML
    $routes->POST('diff-backups', 'PfSenseController::diffBackups'); // Compare two backups
    $routes->GET('dr-package/(:num)', 'PfSenseController::downloadDrPackage/$1'); // One-Click DR Disaster Recovery Package
    $routes->GET('quick-devices', 'PfSenseController::quickDevices'); // Global Palette device index

});

$routes->group('devices', function ($routes) {
    // Default route for listing firewalls
    $routes->GET('/', 'DevicesController::index');

    // CRUD operations for devices
    $routes->POST('add-unknown', 'DevicesController::unknownupdate'); // Add a Unknown Device
    $routes->GET('deviceCard/(:num)', 'DevicesController::deviceCard/$1');

    // Group CRUD operations
    $routes->GET('groups', 'DevicesController::getGroups'); // Get list of groups as JSON
    $routes->POST('groups/store', 'DevicesController::storeGroup'); // Add a new group
    $routes->POST('groups/update/(:num)', 'DevicesController::updateGroup/$1'); // Update group
    $routes->DELETE('groups/delete/(:num)', 'DevicesController::deleteGroup/$1'); // Delete group
});

$routes->group('location', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->GET('/', 'LocationController::index'); // Show DataTable Page
    $routes->GET('getLocations', 'LocationController::getLocations'); // Fetch all locations
    $routes->POST('store', 'LocationController::store'); // Add new location
    $routes->GET('edit/(:num)', 'LocationController::edit/$1'); // GET location details
    $routes->POST('update/(:num)', 'LocationController::update/$1'); // Update location
    $routes->DELETE('delete/(:num)', 'LocationController::delete/$1'); // Delete location
    $routes->POST('clear-flap-count', 'LocationController::clearFlapCount'); // Clear Flap Count

    // Group CRUD operations
    $routes->GET('groups', 'DevicesController::getGroups');
    $routes->POST('groups/store', 'DevicesController::storeGroup');
    $routes->POST('groups/update/(:num)', 'DevicesController::updateGroup/$1');
    $routes->DELETE('groups/delete/(:num)', 'DevicesController::deleteGroup/$1');
});



$routes->group('configpush', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'ConfigPushController::index');
    $routes->post('deploy', 'ConfigPushController::deploy');
    $routes->post('preview', 'ConfigPushController::preview');
    $routes->get('history', 'ConfigPushController::history');
    $routes->post('history', 'ConfigPushController::history');
    $routes->post('rollback', 'ConfigPushController::rollback');
    $routes->get('job/(:num)', 'ConfigPushController::job/$1');
    $routes->get('jobs', 'ConfigPushController::jobs');
    $routes->post('jobs', 'ConfigPushController::jobs');
    $routes->post('approve', 'ConfigPushController::approve');
    $routes->post('cancel', 'ConfigPushController::cancel');

    // Device-initiated transport (Phase 2). These are called by branch firewalls, not browsers:
    // they have no session and no CSRF token, and authenticate with their own HMAC signature.
    $routes->post('pickup', 'ConfigAgentController::pickup');
    $routes->post('jobresult', 'ConfigAgentController::report');
    // The device's own configuration read, for the verification window on this channel.
    $routes->post('watchreport', 'ConfigAgentController::watchReport');
});

$routes->group('configjobs', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'ConfigJobsController::index');
    $routes->get('rows', 'ConfigJobsController::rows');
    $routes->post('rows', 'ConfigJobsController::rows');
    $routes->get('detail/(:num)', 'ConfigJobsController::detail/$1');
    $routes->get('diff/(:num)', 'ConfigJobsController::diff/$1');
    $routes->post('retry', 'ConfigJobsController::retry');
    $routes->post('reconcile', 'ConfigJobsController::reconcile');
    $routes->post('verify', 'ConfigJobsController::verify');
    $routes->get('alerts', 'ConfigJobsController::alerts');
    $routes->post('alerts', 'ConfigJobsController::alerts');
    $routes->post('resolvealert', 'ConfigJobsController::resolveAlert');
});

// Fleet-wide verification report: every applied change, its last confirmation and its drift, with a
// CSV export of whatever the filters currently describe. The export is a GET so it can be linked,
// bookmarked and handed to somebody else.
$routes->group('configreports', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'ConfigVerificationReportController::index');
    $routes->get('rows', 'ConfigVerificationReportController::rows');
    $routes->post('rows', 'ConfigVerificationReportController::rows');
    $routes->get('export', 'ConfigVerificationReportController::export');
});

// Fleet-wide configuration search: every device's *stored* configuration, queryable at once. The export
// is a GET so the slice can be linked — and so "everything mentioning 10.20.0" is a URL.
$routes->group('configsearch', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'ConfigSearchController::index');
    $routes->get('rows', 'ConfigSearchController::rows');
    $routes->post('rows', 'ConfigSearchController::rows');
    $routes->get('export', 'ConfigSearchController::export');
});

// Per-branch availability: what each site's link is doing and every time it was not. Separate from the
// change pipeline on purpose — most outages nobody caused, and the report has to be readable without a
// change having happened.
$routes->group('availability', ['namespace' => 'App\Controllers'], function ($routes) {
    $routes->get('/', 'AvailabilityController::index');
    $routes->get('rows', 'AvailabilityController::rows');
    $routes->post('rows', 'AvailabilityController::rows');
    $routes->get('export', 'AvailabilityController::export');
    $routes->get('export-xlsx', 'AvailabilityController::exportXlsx');
    $routes->get('export-pdf', 'AvailabilityController::exportPdf');
    $routes->get('device-report/(:num)', 'AvailabilityController::deviceReport/$1');
    $routes->get('device-xlsx/(:num)', 'AvailabilityController::deviceXlsx/$1');
    $routes->post('probe', 'AvailabilityController::probeAll');
    $routes->post('probe/(:num)', 'AvailabilityController::probeDevice/$1');
    $routes->post('prune', 'AvailabilityController::prune');
    $routes->get('db-stats', 'AvailabilityController::dbStats');
});

$routes->GET('settings', 'CoreSettings::index');
$routes->GET('docs', 'DocsController::index');
$routes->POST('settingsupdate', 'CoreSettings::update');
$routes->POST('settings/verify-master-password', 'CoreSettings::verifyMasterPassword');
$routes->POST('settings/change-master-password', 'CoreSettings::changeMasterPassword');
$routes->POST('settings/upload-diagram-icon', 'CoreSettings::uploadDiagramIcon');
$routes->POST('settings/remove-diagram-icon', 'CoreSettings::removeDiagramIcon');

$routes->GET('roles', 'RolesController::index');
$routes->POST('roles/addGroup', 'RolesController::addGroup');
$routes->POST('roles/addPermission', 'RolesController::addPermission');
$routes->POST('roles/updateMatrix', 'RolesController::updateMatrix');
$routes->POST('roles/deleteGroup', 'RolesController::deleteGroup');
$routes->POST('roles/deletePermission', 'RolesController::deletePermission');

$routes->GET('wgreceive', 'WgUptimeController::receive');
$routes->POST('wgreceive', 'WgUptimeController::receive');

$routes->GET('smsDownAlert', 'WgUptimeController::smsDownAlert');

$routes->GET('live', 'WgUptimeController::live');
$routes->GET('loggedlive', 'WgUptimeController::loggedlive');

$routes->GET('interface-stats/(:segment)/(:segment)', 'WgUptimeController::interfaceStats/$1/$2');

$routes->get('compliance', 'ComplianceController::index');
$routes->get('compliance/export-csv', 'ComplianceController::exportCsv');
$routes->get('compliance/device-report/(:num)', 'ComplianceController::deviceReport/$1');
$routes->get('compliance/cve-matrix/(:num)', 'ComplianceController::cveMatrix/$1');
$routes->match(['get', 'post'], 'compliance/sync-cve', 'ComplianceController::syncCve');
$routes->post('compliance/save-mitigation', 'ComplianceController::saveMitigation');
$routes->get('pfsense/ping/(:num)', 'PfSenseController::pingDevice/$1');

$routes->get('api/txrxdata', 'TxRxDataController::index');
// $routes->GET('/admin/(:segment)', 'AdminCtl::index/$1', ['filter' => 'group:superadmin,admin']);




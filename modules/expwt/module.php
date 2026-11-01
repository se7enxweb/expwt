<?php
/**
 * The module expwt: the sidebar's session end point and its policies (decision F6).
 *
 *   expwt/api              the sidebar's actions as JSON (GET: the reading ones, POST with the CSRF token: the others);
 *                          the REST data layer of the browser (expwtApi)
 *   expwt/panels/<sa>/<role>  Setup > Website sidebar panels: order and visibility of the panels per role and siteaccess (E9)
 *   expwt/reauth/<action>  "Verify it's you" for an action, in a small window the sidebar opens; tells the sidebar
 *                          when the user has answered
 *
 * Policies, one per area of the sidebar; every action also checks the kernel's policy of what it changes:
 *   use        the sidebar itself, the Me panel and the Page panel (reading)
 *   edit       editing on a draft, preview, publish now or later, soft locks
 *   create     new content below or beside the page
 *   structure  moving, adding locations, hiding and revealing, the trash, sorting
 *   layouts, templates, shop, admin   the panels of later phases (defined now so roles can be prepared)
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$Module = array( 'name' => 'Website sidebar', 'variable_params' => false );

$ViewList = array();
$ViewList['api'] = array(
    'functions' => array( 'use' ),
    'script' => 'api.php',
    'ui_context' => 'ajax',
    'params' => array() );

$ViewList['reauth'] = array(
    'functions' => array( 'use' ),
    'script' => 'reauth.php',
    // the small "Verify it's you" window is no page a later redirect returns to
    'ui_context' => 'authentication',
    'params' => array( 'Action' ) );

// One field of a draft in the editor the installation has for its datatype (decision 12, F1): the sidebar shows it
// in a frame; ezxmltext in ezoe (TinyMCE 8) or exp_oe_tiptap, eztags in the tag picker, images with their upload ...
$ViewList['field'] = array(
    'functions' => array( 'edit' ),
    'script' => 'field.php',
    // a page inside the sidebar's frame: never the session's last page (LastAccessesURI, LastAccessedModifyingURI),
    // which content/edit's discard and publish return to
    'ui_context' => 'ajax',
    'params' => array( 'ObjectID', 'Version', 'Language', 'Identifier' ) );

// Setup > Website sidebar panels (E9): the panels of the sidebar per role and siteaccess, their order and which are
// shown. Also needs the kernel's settings module (settings/view to see it, settings/edit to change it).
$ViewList['panels'] = array(
    'functions' => array( 'admin' ),
    'script' => 'panels.php',
    'default_navigation_part' => 'ezsetupnavigationpart',
    'ui_context' => 'administration',
    'params' => array( 'SiteAccess', 'RoleID' ) );

$FunctionList = array();
// The sidebar on the site: the tab, the Me panel and the Page panel (reading)
$FunctionList['use'] = array();
// Edit content on a draft from the sidebar or on the page, preview, publish now or at a date, soft locks
$FunctionList['edit'] = array();
// Create content below or beside the page from the sidebar
$FunctionList['create'] = array();
// Move, add locations, hide and reveal, move to the trash and sort from the sidebar
$FunctionList['structure'] = array();
// The layouts of the page (Exponential Layouts) from the sidebar
$FunctionList['layouts'] = array();
// Templates and overrides from the sidebar (templates are code: give it to developers only)
$FunctionList['templates'] = array();
// The shop's panels of the sidebar
$FunctionList['shop'] = array();
// The administration's panels of the sidebar (caches, users, settings)
$FunctionList['admin'] = array();
// The Insights panel: the page's views and the site's searches from the kernel's own statistics (F25)
$FunctionList['insights'] = array();
// The Performance panel: how the page was rendered (time, SQL, caches, layout blocks) from the kernel's debug bar data
// (E12); for administrators and developers
$FunctionList['performance'] = array();

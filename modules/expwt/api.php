<?php
/**
 * expwt/api: the sidebar's actions as JSON for the browser's session (the REST data layer; expwtApi).
 *
 *   GET  ?action=boot&input={...}            a reading action
 *   POST action=<name>&input={...}           any action; the CSRF token (ezformtoken) in the header X-CSRF-Token
 *
 * The fields are form fields, not a JSON body, so "Verify it's you" (expwtReauthTriggerHandler) sees the action.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string)$_SERVER['REQUEST_METHOD'] ) : 'GET';
$source = $method === 'POST' ? $_POST : $_GET;
$action = isset( $source['action'] ) && is_string( $source['action'] ) ? $source['action'] : '';
$input = isset( $source['input'] ) && is_string( $source['input'] ) ? json_decode( $source['input'], true ) : array();
$settings = expwtSettings::fromIni();
$api = new expwtApi( new expwtGateway( $settings ), $settings );
$answer = is_array( $input ) ? $api->call( $action, $input, $method ) : expwtApi::error( 400, 'bad_input', 'The input is not JSON.' );

if ( !headers_sent() )
{
    http_response_code( $answer['status'] );
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Cache-Control: private, no-store' );
    header( 'X-Content-Type-Options: nosniff' );
}
// the JSON alone: no pagelayout and no debug report after it (as graphql/query)
echo json_encode( $answer['body'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
eZExecution::cleanExit();

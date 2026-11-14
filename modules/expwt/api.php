<?php
/**
 * expwt/api: the sidebar's actions as JSON for the browser's session (the REST data layer; expwtApi).
 *
 *   GET  expwt/api/<action>/(input)/<base64url JSON>   a reading action; (input) may be left out when it is empty
 *   POST expwt/api/<action>/  input=<JSON>              any action; the CSRF token (ezformtoken) in the header X-CSRF-Token
 *
 * The views take view parameters, never query parameters. The input of a GET is its JSON in base64url (RFC 4648
 * section 5, no padding): raw JSON can hold a "/", and an encoded slash in a path is refused by the web server.
 * The action is in the path for POST too, so "Verify it's you" (expwtReauthTriggerHandler) sees it as the view's
 * first parameter; the input of a POST stays a form field. The browser closes the address of a POST with a slash, so
 * an action such as state.save does not end like a backup file (.save), which request shields and firewalls refuse.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string)$_SERVER['REQUEST_METHOD'] ) : 'GET';
$action = isset( $Params['Action'] ) && is_string( $Params['Action'] ) ? $Params['Action'] : '';
$input = expwtApi::requestInput( $method, isset( $Params['Input'] ) ? $Params['Input'] : false, $_POST );
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

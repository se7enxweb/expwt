<?php
/**
 * The class of an action an extension registers for the website sidebar (expwtRegistry, [Action_<extension>.<name>]).
 * expwtApi calls run() after its own checks (the action is registered, a writing one only by POST with the CSRF token,
 * the expwt function and kernel policies of its registration); run() checks the kernel's policy of what it changes, as
 * the sidebar's own actions do, and answers plain data (it becomes the answer's data, JSON).
 *
 * A refusal is an expwtApiError( status, code, message ): the status and the message reach the panel as they are.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

interface expwtActionHandler
{
    /**
     * @param string $action the registered name, <extension>.<name>
     * @param array $input the panel's input (decoded JSON; check every value)
     * @param expwtGateway $gateway the sidebar's gateway (the current user, url())
     * @return array the answer's data
     */
    public function run( $action, array $input, expwtGateway $gateway );
}

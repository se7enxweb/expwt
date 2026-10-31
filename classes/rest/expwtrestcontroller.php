<?php
/**
 * /api/expwt/v1/<action>: hands the action to expwtApi as the user the REST layer authenticated.
 *
 * The route's variables come from ezcMvcController::__get() (its properties); a declared property would hide them.
 *
 * @property-read string $sidebarAction the route's :sidebarAction (":action" would be the controller's own action name)
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtRestController extends ezpRestMvcController
{
    public function doAction()
    {
        $method = $this->request->originalProtocol === 'http-post' ? 'POST' : 'GET';
        if ( $method === 'POST' )
            $input = json_decode( (string)$this->request->body, true );
        else
            $input = isset( $this->request->get['input'] ) ? json_decode( (string)$this->request->get['input'], true ) : array();
        $settings = expwtSettings::fromIni();
        $answer = is_array( $input )
            ? ( new expwtApi( new expwtGateway( $settings ), $settings ) )->call( (string)$this->sidebarAction, $input, $method )
            : expwtApi::error( 400, 'bad_input', 'The input is not JSON.' );
        $result = new ezpRestMvcResult();
        if ( $answer['status'] === 200 )
            $result->variables = $answer['body'];
        else
            $result->status = new ezpRestStatusResponse( $answer['status'], $answer['body'] );
        return $result;
    }
}

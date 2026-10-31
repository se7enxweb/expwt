<?php
/**
 * {expwt_markup()}: the sidebar's element and deferred script for this page, or an empty string (expwtHook).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtTemplateOperator
{
    public function operatorList()
    {
        return array( 'expwt_markup' );
    }

    public function namedParameterPerOperator()
    {
        return true;
    }

    public function namedParameterList()
    {
        return array( 'expwt_markup' => array( 'node_id' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ) ) );
    }

    public function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        $nodeId = (int)$namedParameters['node_id'] ?: expwtHook::currentNodeId();
        try
        {
            $operatorValue = expwtHook::markup( expwtHook::context( $nodeId ) );
        }
        catch ( Exception $e )
        {
            eZDebug::writeError( $e->getMessage(), __METHOD__ );
            $operatorValue = '';
        }
    }
}

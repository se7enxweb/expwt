<?php
/**
 * {expwt_markup()}: the sidebar's element and deferred script for this page, or an empty string (expwtHook).
 * {expwt_sidebar_on()}: whether the sidebar is on for the siteaccess of this request (expwtSettings, with the defaults
 * of the code for what expwt.ini leaves unset; a template reading expwt.ini [ExpwtSettings] Enabled itself logged an
 * error wherever it is unset, already when the template was compiled).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtTemplateOperator
{
    public function operatorList()
    {
        return array( 'expwt_markup', 'expwt_sidebar_on' );
    }

    public function namedParameterPerOperator()
    {
        return true;
    }

    public function namedParameterList()
    {
        return array( 'expwt_markup' => array( 'node_id' => array( 'type' => 'integer', 'required' => false, 'default' => 0 ) ),
                      'expwt_sidebar_on' => array() );
    }

    public function modify( $tpl, $operatorName, $operatorParameters, $rootNamespace, $currentNamespace, &$operatorValue, $namedParameters )
    {
        if ( $operatorName === 'expwt_sidebar_on' )
        {
            try
            {
                $operatorValue = expwtSettings::onHere( expwtSettings::fromIni() );
            }
            catch ( Exception $e )
            {
                eZDebug::writeError( $e->getMessage(), __METHOD__ );
                $operatorValue = false;
            }
            return;
        }
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

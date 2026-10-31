<?php
/**
 * expwtexample: the server actions the extension registers for the website sidebar (settings/expwt.ini.append.php).
 * expwtApi has checked the registration (the action exists, POST for a writing one, the expwt function and kernel
 * policies named there); each action checks the kernel's policy of what it reads or changes itself.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwtexample
 */

class expwtexampleExpwtActions implements expwtActionHandler
{
    public function run( $action, array $input, expwtGateway $gateway )
    {
        switch ( $action )
        {
            case 'expwtexample.info':
                $node = self::node( $input );
                $object = $node->object();
                return array( 'nodeId' => (int)$node->attribute( 'node_id' ), 'objectId' => (int)$object->attribute( 'id' ),
                              'name' => (string)$object->attribute( 'name' ), 'modified' => (int)$object->attribute( 'modified' ),
                              'children' => (int)$node->attribute( 'children_count' ) );
            case 'expwtexample.clearcache':
                $node = self::node( $input );
                eZContentCacheManager::clearContentCacheIfNeeded( (int)$node->attribute( 'contentobject_id' ) );
                return array( 'cleared' => true, 'objectId' => (int)$node->attribute( 'contentobject_id' ) );
            case 'expwtexample.search':
                $query = isset( $input['query'] ) && is_string( $input['query'] ) ? trim( $input['query'] ) : '';
                if ( strlen( $query ) < 2 )
                    return array( 'items' => array() );
                // the kernel's fetch with the user's read policies applied
                $nodes = eZContentObjectTreeNode::subTreeByNodeID( array( 'AttributeFilter' => array( array( 'name', 'like', '*' . $query . '*' ) ),
                                                                          'Limit' => 10, 'MainNodeOnly' => true ), 2 );
                $items = array();
                foreach ( (array)$nodes as $node )
                    $items[] = array( 'title' => (string)$node->attribute( 'name' ),
                                      'url' => expwtGateway::url( '/' . ltrim( (string)$node->attribute( 'url_alias' ), '/' ) ) );
                return array( 'items' => $items );
        }
        throw new expwtApiError( 404, 'unknown_action', 'There is no such action.' );
    }

    /** @return eZContentObjectTreeNode the node of the input, which the user may read */
    protected static function node( array $input )
    {
        $node = isset( $input['nodeId'] ) && is_numeric( $input['nodeId'] ) ? eZContentObjectTreeNode::fetch( (int)$input['nodeId'] ) : null;
        if ( !$node instanceof eZContentObjectTreeNode || !$node->canRead() )
            throw new expwtApiError( 404, 'not_found', 'This page is not there, or you may not read it.' );
        return $node;
    }
}

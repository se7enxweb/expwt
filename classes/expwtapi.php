<?php
/**
 * The sidebar's actions behind every transport: expwt/api (the browser's session, the REST data layer),
 * /api/expwt/v1/<action> (a token, ezprestapi) and expwtRead/expwtWrite (the GraphQL API). Each transport hands the
 * action and its input here and sends back what call() answers, so the actions, their checks and their answers are
 * the same whichever data layer an installation picks (expwt.ini DataLayer, decision F5).
 *
 * call() checks, in this order: the action exists (404), the method (a writing action only by POST, 405), the expwt
 * policy of the action (403), then hands it to the gateway, whose operations check the kernel's policy of what they
 * change. An expwtApiError of the gateway becomes its status and message; anything else a 500 without details.
 *
 * Tested without a database through a gateway that answers from arrays.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtApi
{
    /** @var expwtGateway */
    protected $gateway;

    /** @var array expwtSettings */
    protected $settings;

    public function __construct( expwtGateway $gateway, array $settings )
    {
        $this->gateway = $gateway;
        $this->settings = $settings;
    }

    /**
     * @param string $action
     * @param array $input
     * @param string $method GET or POST
     * @return array status (HTTP), body
     */
    public function call( $action, array $input, $method = 'POST' )
    {
        if ( !expwtAccess::exists( $action ) )
            return self::error( 404, 'unknown_action', 'There is no such action.' );
        if ( expwtAccess::writes( $action ) && strtoupper( (string)$method ) !== 'POST' )
            return self::error( 405, 'method', 'This action changes something and is only sent by POST.' );
        try
        {
            if ( !expwtAccess::allows( $action, $this->gateway->capabilities() ) )
                return self::error( 403, 'forbidden', 'You may not do this.' );
            return array( 'status' => 200, 'body' => array( 'ok' => true, 'data' => $this->run( $action, $input ) ) );
        }
        catch ( expwtApiError $e )
        {
            return self::error( $e->status, $e->errorCode, $e->getMessage(), $e->details );
        }
        catch ( Exception $e )
        {
            if ( class_exists( 'expGraphQLError' ) && $e instanceof expGraphQLError )
                return self::fromGraphQLError( $e );
            if ( class_exists( 'eZDebug' ) )
                eZDebug::writeError( get_class( $e ) . ': ' . $e->getMessage(), __METHOD__ );
            return self::error( 500, 'internal', 'Something went wrong on the server.' );
        }
    }

    protected function run( $action, array $input )
    {
        $g = $this->gateway;
        switch ( $action )
        {
            case 'boot':
                return $this->boot( $input );
            case 'state.save':
                $state = expwtState::normalize( isset( $input['state'] ) ? $input['state'] : null, $this->settings );
                $g->saveState( $state );
                return array( 'state' => $state );
            case 'me':
                return $g->me();
            case 'page.load':
                return $g->page( self::ids( $input, array( 'nodeId', 'objectId', 'version' ) ) + self::strings( $input, array( 'language' ) ) );
            case 'draft.save':
                if ( !isset( $input['fields'] ) || !is_array( $input['fields'] ) || !$input['fields'] )
                    throw new expwtApiError( 400, 'no_fields', 'Nothing to save.' );
                return $g->saveDraft( self::ids( $input, array( 'objectId', 'version', 'publishedVersion' ) )
                                      + self::strings( $input, array( 'language' ) )
                                      + array( 'fields' => $input['fields'],
                                               'base' => isset( $input['base'] ) && is_array( $input['base'] ) ? $input['base'] : array(),
                                               'choices' => isset( $input['choices'] ) && is_array( $input['choices'] ) ? $input['choices'] : array() ) );
            case 'draft.publish':
                // unpublishAt given empty: the take-down date is cleared (absent: left as it is)
                return $g->publish( self::ids( $input, array( 'objectId', 'version' ) ) + self::strings( $input, array( 'publishAt', 'unpublishAt' ) )
                                    + ( array_key_exists( 'unpublishAt', $input ) && ( $input['unpublishAt'] === null || $input['unpublishAt'] === '' ) ? array( 'unpublishAt' => '' ) : array() ) );
            case 'draft.discard':
                return $g->discard( self::ids( $input, array( 'objectId', 'version' ) ) );
            case 'lock.acquire':
                return $g->lock( self::ids( $input, array( 'objectId' ) ) + array( 'takeOver' => !empty( $input['takeOver'] ) ) );
            case 'lock.release':
                return $g->unlock( self::ids( $input, array( 'objectId' ) ) );
            case 'create.classes':
                return $g->createClasses( self::ids( $input, array( 'nodeId' ) ) );
            case 'create.new':
                return $g->createNew( self::ids( $input, array( 'parentId' ) ) + self::strings( $input, array( 'classIdentifier', 'title', 'language' ) ) );
            case 'tree.children':
                return $g->children( self::ids( $input, array( 'nodeId', 'offset' ) ) );
            case 'tree.move':
                return $g->move( self::ids( $input, array( 'nodeId', 'parentId' ) ) );
            case 'tree.addlocation':
                return $g->addLocation( self::ids( $input, array( 'nodeId', 'parentId' ) ) );
            case 'tree.hide':
                return $g->visibility( self::ids( $input, array( 'nodeId' ) ), true );
            case 'tree.reveal':
                return $g->visibility( self::ids( $input, array( 'nodeId' ) ), false );
            case 'tree.trash':
                return $g->trash( self::ids( $input, array( 'nodeId' ) ) );
            case 'tree.sort':
                $order = isset( $input['order'] ) && is_array( $input['order'] ) ? array_values( array_map( 'intval', $input['order'] ) ) : array();
                return $g->sort( self::ids( $input, array( 'nodeId' ) ) + array( 'order' => $order ) );
        }
        throw new expwtApiError( 404, 'unknown_action', 'There is no such action.' );
    }

    /**
     * What the sidebar needs once per page: who, what they may do, where the sidebar is, the CSRF token for the
     * writes, the transport, and the strings of the panels in the user's language.
     */
    protected function boot( array $input )
    {
        $g = $this->gateway;
        $state = $g->state();
        return array(
            'user' => $g->user(),
            'capabilities' => $g->capabilities(),
            'state' => $state,
            'theme' => expwtState::theme( $state, $g->adminColorMode() ),
            'token' => $g->token(),
            'transport' => $g->transport(),
            'urls' => $g->urls(),
            'strings' => $g->strings(),
            'settings' => array( 'minWidth' => $this->settings['min_width'], 'maxWidth' => $this->settings['max_width'],
                                 'minHeight' => $this->settings['min_height'], 'maxHeight' => $this->settings['max_height'],
                                 'overlayBelow' => $this->settings['overlay_below_width'] ),
        );
    }

    /**
     * @param array $input
     * @param string[] $names
     * @return array the names given, as integers (0 and less are dropped)
     */
    public static function ids( array $input, array $names )
    {
        $out = array();
        foreach ( $names as $name )
            if ( isset( $input[$name] ) && is_numeric( $input[$name] ) && (int)$input[$name] > 0 )
                $out[$name] = (int)$input[$name];
        return $out;
    }

    /** @return array the names given, as trimmed strings of at most 1000 characters */
    public static function strings( array $input, array $names )
    {
        $out = array();
        foreach ( $names as $name )
            if ( isset( $input[$name] ) && is_scalar( $input[$name] ) && trim( (string)$input[$name] ) !== '' )
                $out[$name] = substr( trim( (string)$input[$name] ), 0, 1000 );
        return $out;
    }

    /** @return array */
    public static function error( $status, $code, $message, array $details = array() )
    {
        $body = array( 'ok' => false, 'error' => $code, 'message' => expwtStrings::tr( $message ) );
        if ( $details )
            $body['details'] = $details;
        return array( 'status' => (int)$status, 'body' => $body );
    }

    /** The kernel writer's refusals in the sidebar's terms */
    public static function fromGraphQLError( Exception $e )
    {
        $code = method_exists( $e, 'errorCode' ) ? (string)$e->errorCode() : '';
        $map = array( 'NOT_FOUND' => array( 404, 'not_found' ), 'FORBIDDEN' => array( 403, 'forbidden' ),
                      'INTERNAL' => array( 500, 'internal' ) );
        if ( isset( $map[$code] ) )
            return self::error( $map[$code][0], $map[$code][1], $e->getMessage() );
        return self::error( 400, 'bad_input', $e->getMessage() );
    }
}

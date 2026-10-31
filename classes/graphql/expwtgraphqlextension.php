<?php
/**
 * The sidebar's actions through the GraphQL API (graphql.ini SchemaExtensionList[], the data layer "graphql" of
 * expwt.ini DataLayer, decision F5):
 *
 *   query    expwtRead(action: String!, input: String): ExpwtAnswer     the reading actions (boot, page.load ...)
 *   mutation expwtWrite(action: String!, input: String): ExpwtAnswer    every action, with the mutation rules of the
 *                                                                       API (POST, the session's CSRF token, the
 *                                                                       scope graphql:write of an API key)
 *
 * input and ExpwtAnswer.data are JSON text: the same input and data as expwt/api and /api/expwt/v1, from the same
 * expwtApi. The sidebar calls them as the persisted queries expwt-read and expwt-write
 * (extension/expwt/graphql/persisted).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class expwtGraphQLExtension implements expGraphQLSchemaExtension, expGraphQLMutationExtension
{
    public function isAvailable()
    {
        return class_exists( 'expwtApi' );
    }

    public function name()
    {
        return 'expwt/1';
    }

    public function datatypes()
    {
        return array();
    }

    public function fieldMapping( array $attribute, expGraphQLTypes $types )
    {
        return null;
    }

    public function locationFields( expGraphQLTypes $types )
    {
        return array();
    }

    public function queryFields( expGraphQLTypes $types )
    {
        return array( 'expwtRead' => $this->field( $types, 'A reading action of the website sidebar (expwt): boot, me, page.load, create.classes, tree.children, inbox.load.', false ) );
    }

    public function mutationFields( expGraphQLTypes $types )
    {
        return array( 'expwtWrite' => $this->field( $types, 'An action of the website sidebar (expwt) that changes something: state.save, draft.save, draft.publish ...', true ) );
    }

    protected function field( expGraphQLTypes $types, $description, $write )
    {
        return array(
            'type' => Type::nonNull( self::answerType( $types ) ),
            'description' => $description,
            'args' => array( 'action' => array( 'type' => Type::nonNull( Type::string() ), 'description' => 'The action (expwtAccess::ACTIONS).' ),
                             'input' => array( 'type' => Type::string(), 'description' => 'The input of the action as JSON text.' ) ),
            'resolve' => function ( $root, $args, expGraphQLContext $ctx ) use ( $write ) {
                if ( $write )
                {
                    $refusal = expGraphQLMutations::refusal( $ctx );
                    if ( $refusal !== null )
                        throw new expGraphQLError( $refusal[0], $refusal[1] );
                }
                else if ( expwtApi::writes( (string)$args['action'] ) )
                    return self::answer( expwtApi::error( 400, 'bad_input', 'This action changes something: send it with expwtWrite.' ) );
                $input = isset( $args['input'] ) && $args['input'] !== '' ? json_decode( (string)$args['input'], true ) : array();
                if ( !is_array( $input ) )
                    return self::answer( expwtApi::error( 400, 'bad_input', 'The input is not JSON.' ) );
                $settings = expwtSettings::fromIni();
                $api = new expwtApi( new expwtGateway( $settings ), $settings );
                return self::answer( $api->call( (string)$args['action'], $input, $write ? 'POST' : 'GET' ) );
            },
        );
    }

    /** @return array ok, status, data (JSON), error, message */
    public static function answer( array $answer )
    {
        $body = $answer['body'];
        return array( 'ok' => !empty( $body['ok'] ), 'status' => (int)$answer['status'],
                      'data' => array_key_exists( 'data', $body ) ? json_encode( $body['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null,
                      'error' => isset( $body['error'] ) ? (string)$body['error'] : null,
                      'message' => isset( $body['message'] ) ? (string)$body['message'] : null,
                      'details' => isset( $body['details'] ) ? json_encode( $body['details'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : null );
    }

    /** @return ObjectType ExpwtAnswer, made once per schema */
    protected static function answerType( expGraphQLTypes $types )
    {
        return $types->register( 'ExpwtAnswer', function () {
            return new ObjectType( array(
                'name' => 'ExpwtAnswer',
                'description' => 'The answer of a website sidebar action (expwt).',
                'fields' => array(
                    'ok' => array( 'type' => Type::nonNull( Type::boolean() ) ),
                    'status' => array( 'type' => Type::nonNull( Type::int() ), 'description' => 'The HTTP status expwt/api would answer.' ),
                    'data' => array( 'type' => Type::string(), 'description' => 'The data as JSON text.' ),
                    'error' => array( 'type' => Type::string(), 'description' => 'The code of a refusal (forbidden, not_found, conflict ...).' ),
                    'message' => array( 'type' => Type::string() ),
                    'details' => array( 'type' => Type::string(), 'description' => 'Details of a refusal as JSON text (the fields of a conflict).' ),
                ),
            ) );
        } );
    }
}

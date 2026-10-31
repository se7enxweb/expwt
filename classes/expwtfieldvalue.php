<?php
/**
 * What the sidebar shows of a field (decision F1): rich text as the page renders it, tags and related objects as
 * their names, other fields in the readable string form of their datatype. Never a datatype's storage string that is
 * no text for people (eztags "ids|#keywords|#parents", relations as object ids): those fields show their names.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtFieldValue
{
    /**
     * @param eZContentObjectAttribute $attribute
     * @param string|null $input expwtFields input (null: from the datatype)
     * @return array value (string), names (string[]|null)
     */
    public static function of( eZContentObjectAttribute $attribute, $input = null )
    {
        if ( $input === null )
            $input = expwtFields::describe( (string)$attribute->attribute( 'data_type_string' ) )['input'];
        $names = in_array( $input, expwtFields::NAMES_ONLY, true ) ? self::names( $attribute, $input ) : null;
        return array( 'value' => self::value( $attribute, $input, $names ), 'names' => $names );
    }

    /** @return string */
    public static function value( eZContentObjectAttribute $attribute, $input, $names = null )
    {
        if ( $input === 'richtext' )
        {
            $content = $attribute->content();
            $output = is_object( $content ) && $content->hasAttribute( 'output' ) ? $content->attribute( 'output' ) : null;
            return is_object( $output ) && $output->hasAttribute( 'output_text' ) ? trim( (string)$output->attribute( 'output_text' ) ) : '';
        }
        if ( in_array( $input, expwtFields::NAMES_ONLY, true ) )
            return implode( ', ', $names !== null ? $names : self::names( $attribute, $input ) );
        if ( $input === 'full' || $input === 'image' )
            return '';
        $value = $attribute->toString();
        return is_scalar( $value ) ? (string)$value : '';
    }

    /**
     * The keywords of the tags (eztags), the names of the related objects the user may read.
     *
     * @return string[]
     */
    public static function names( eZContentObjectAttribute $attribute, $input )
    {
        $names = array();
        $content = $attribute->content();
        $has = function ( $o, $name ) { return is_object( $o ) && method_exists( $o, 'hasAttribute' ) && $o->hasAttribute( $name ); };
        if ( $input === 'tags' )
        {
            foreach ( (array)( $has( $content, 'tags' ) ? $content->attribute( 'tags' ) : array() ) as $tag )
            {
                if ( $has( $tag, 'keyword' ) )
                    $names[] = (string)$tag->attribute( 'keyword' );
            }
            if ( !$names && $has( $content, 'keywords' ) )
                $names = array_map( 'strval', (array)$content->attribute( 'keywords' ) );
        }
        else
        {
            $ids = array();
            if ( $content instanceof eZContentObject )
                $ids[] = (int)$content->attribute( 'id' );
            else if ( is_array( $content ) && isset( $content['relation_list'] ) )
                foreach ( (array)$content['relation_list'] as $relation )
                    if ( isset( $relation['contentobject_id'] ) )
                        $ids[] = (int)$relation['contentobject_id'];
            foreach ( $ids as $id )
            {
                $object = eZContentObject::fetch( $id );
                if ( $object instanceof eZContentObject && $object->canRead() )
                    $names[] = (string)$object->attribute( 'name' );
            }
        }
        return array_values( array_filter( $names, 'strlen' ) );
    }
}

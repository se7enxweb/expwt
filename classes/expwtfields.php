<?php
/**
 * How the sidebar edits a field of a datatype (decision F1): the input of the sidebar form, whether the field can be
 * edited on the page itself, and in which form its value travels. Every value travels in the string form of its
 * datatype (eZContentObjectAttribute::toString() and fromString(), the form of the CSV import), except rich text,
 * which travels as HTML and is written through the GraphQL API's HTML conversion (expGraphQLFieldInput::htmlToXml).
 *
 *   input      text, textarea, richtext, number, decimal, checkbox, date, datetime, time, email, url, select,
 *              multiselect, keywords, relations, tags, image, metadata, or full (only the full editor of the
 *              administration, with the installation's configured rich text editor and every datatype)
 *   inline     the page's own text can be edited in place (text and rich text)
 *
 * Pure, tested without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtFields
{
    /** datatype => input */
    const INPUTS = array(
        'ezstring' => 'text',
        'eztext' => 'textarea',
        'ezxmltext' => 'richtext',
        'ezinteger' => 'number',
        'ezfloat' => 'decimal',
        'ezprice' => 'decimal',
        'ezboolean' => 'checkbox',
        'ezdate' => 'date',
        'ezdatetime' => 'datetime',
        'eztime' => 'time',
        'ezemail' => 'email',
        'ezurl' => 'url',
        'ezselection' => 'select',
        'ezkeyword' => 'keywords',
        'ezobjectrelation' => 'relations',
        'ezobjectrelationlist' => 'relations',
        'eztags' => 'tags',
        'ezimage' => 'image',
    );

    /** Inputs whose text is shown on the page and can be edited there */
    const INLINE = array( 'text', 'textarea', 'richtext' );

    /** Datatypes never shown in the sidebar: accounts and secrets */
    const NEVER = array( 'ezuser', 'ezpassword', 'sevenxtwofactor' );

    /**
     * @param string $datatype
     * @param bool $multiple a selection with several choices (ezselection is_multiselect)
     * @return array input, inline, editable
     */
    public static function describe( $datatype, $multiple = false )
    {
        if ( in_array( $datatype, self::NEVER, true ) )
            return array( 'input' => 'hidden', 'inline' => false, 'editable' => false );
        $input = isset( self::INPUTS[$datatype] ) ? self::INPUTS[$datatype] : 'full';
        if ( $input === 'select' && $multiple )
            $input = 'multiselect';
        // a file (an image) needs an upload: the full editor until the media panel (phase 2) takes uploads
        return array( 'input' => $input, 'inline' => in_array( $input, self::INLINE, true ),
                      'editable' => !in_array( $input, array( 'full', 'image' ), true ) );
    }

    /**
     * The value of a date input (Y-m-d, or Y-m-d\TH:i for datetime) as the timestamp the datatype stores, in the
     * installation's time zone; '' stays '' (no date).
     *
     * @param string $input date, datetime or time
     * @param string $value
     * @return string|false the string form, false for a value that is not a date
     */
    public static function dateToString( $input, $value )
    {
        $value = trim( (string)$value );
        if ( $value === '' || ctype_digit( $value ) )
            return $value;
        if ( $input === 'time' )
        {
            if ( !preg_match( '/^(\d{1,2}):(\d{2})$/', $value, $m ) || (int)$m[1] > 23 || (int)$m[2] > 59 )
                return false;
            return (string)( (int)$m[1] * 3600 + (int)$m[2] * 60 );
        }
        $format = $input === 'datetime' ? '!Y-m-d\TH:i' : '!Y-m-d';
        $date = DateTime::createFromFormat( $format, $value );
        return $date instanceof DateTime && $date->format( substr( $format, 1 ) ) === $value ? (string)$date->getTimestamp() : false;
    }

    /**
     * A value from the client in the string form of the datatype.
     *
     * @param array $field a field of the page answer: input
     * @param mixed $value
     * @return string|false false for a value the input cannot take
     */
    public static function toStringForm( array $field, $value )
    {
        if ( is_array( $value ) )
            $value = implode( '|', array_map( 'strval', $value ) );
        if ( !is_scalar( $value ) && $value !== null )
            return false;
        $value = (string)$value;
        switch ( $field['input'] )
        {
            case 'checkbox':
                return in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true ) ? '1' : '0';
            case 'number':
                return $value === '' || preg_match( '/^-?\d+$/', $value ) ? $value : false;
            case 'decimal':
                return $value === '' || is_numeric( $value ) ? $value : false;
            case 'date':
            case 'datetime':
            case 'time':
                return self::dateToString( $field['input'], $value );
            case 'email':
                return $value === '' || filter_var( $value, FILTER_VALIDATE_EMAIL ) !== false ? $value : false;
            case 'text':
                return str_replace( array( "\r", "\n" ), ' ', $value );
        }
        return $value;
    }
}

<?php
/**
 * The focal point and crop of an image field in the website sidebar (E7): whether the kernel has them, which aliases
 * the Images part of a field previews, and the values the browser sends, checked.
 *
 * The focal point and the crop rectangle belong to the kernel's image datatype (Exponential 6.0.15,
 * eZImageAliasHandler::setFocalPoint(), expImageFocalPoint): stored per draft in the image's XML, the crop cut out
 * before an alias's filters, every geometry/crop filter centered on the focal point. On a kernel without them
 * available() is false and the sidebar shows nothing of it.
 *
 * The aliases previewed are those the page's templates use: every alias the image already has (the templates that
 * showed it made them, in the published version or the draft), and every alias with a geometry/crop filter (the
 * ones the focal point moves), in the order of image.ini's alias list, at most MAX_ALIASES.
 *
 * Pure (the kernel's expImageFocalPoint for the filter chains): tested without a database.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtImages
{
    /** The most aliases one image field previews */
    const MAX_ALIASES = 24;

    /** @return bool the kernel stores focal points and crop rectangles of images */
    public static function available()
    {
        return class_exists( 'expImageFocalPoint' ) && class_exists( 'eZImageAliasHandler' )
            && method_exists( 'eZImageAliasHandler', 'setFocalPoint' ) && method_exists( 'eZImageAliasHandler', 'supportsFocalPoint' );
    }

    /**
     * The aliases to preview: those already made for the image, and those with a crop filter, in the order of the
     * alias list, without 'original'.
     *
     * @param array $aliasList eZImageManager::aliasList()
     * @param string[] $made names of the aliases the image has
     * @return string[]
     */
    public static function relevantAliases( array $aliasList, array $made )
    {
        $out = array();
        foreach ( $aliasList as $name => $alias )
        {
            $name = (string)$name;
            if ( $name === 'original' )
                continue;
            if ( in_array( $name, $made, true ) || self::crops( self::chain( $aliasList, $name ) ) )
                $out[] = $name;
            if ( count( $out ) >= self::MAX_ALIASES )
                break;
        }
        return $out;
    }

    /**
     * Every filter from the original to the alias, through its Reference= chain, as the preview needs them.
     *
     * @param array $aliasList
     * @param string $name
     * @return array of array( 'name' => string, 'data' => array )
     */
    public static function chain( array $aliasList, $name )
    {
        if ( !class_exists( 'expImageFocalPoint' ) )
            return array();
        $out = array();
        foreach ( expImageFocalPoint::filterChain( $aliasList, $name ) as $filter )
        {
            $out[] = array( 'name' => (string)$filter['name'],
                            'data' => is_array( $filter['data'] ) ? array_values( array_map( 'strval', $filter['data'] ) ) : array() );
        }
        return $out;
    }

    /** @return bool one of the filters is a geometry/crop */
    public static function crops( array $filters )
    {
        foreach ( $filters as $filter )
            if ( isset( $filter['name'] ) && $filter['name'] === 'geometry/crop' )
                return true;
        return false;
    }

    /**
     * The focal point the browser sent: null for none, false when it is not one.
     *
     * @param mixed $value array( 'x' => fraction, 'y' => fraction ), or null / '' for none
     * @return array|null|false
     */
    public static function point( $value )
    {
        if ( $value === null || $value === '' || $value === array() )
            return null;
        if ( !is_array( $value ) || !isset( $value['x'], $value['y'] ) )
            return false;
        $x = self::fraction( $value['x'] );
        $y = self::fraction( $value['y'] );
        return $x === null || $y === null ? false : array( 'x' => $x, 'y' => $y );
    }

    /**
     * The crop rectangle the browser sent: null for none (or the whole image), false when it is not one.
     *
     * @param mixed $value array( 'x', 'y', 'width', 'height' ) as fractions, or null / '' for none
     * @return array|null|false
     */
    public static function rectangle( $value )
    {
        if ( $value === null || $value === '' || $value === array() )
            return null;
        if ( !is_array( $value ) )
            return false;
        $out = array();
        foreach ( array( 'x', 'y', 'width', 'height' ) as $key )
        {
            $out[$key] = isset( $value[$key] ) ? self::fraction( $value[$key] ) : null;
            if ( $out[$key] === null )
                return false;
        }
        if ( $out['width'] < 0.01 || $out['height'] < 0.01 || $out['x'] + $out['width'] > 1.000001 || $out['y'] + $out['height'] > 1.000001 )
            return false;
        if ( $out['x'] == 0 && $out['y'] == 0 && $out['width'] >= 0.999999 && $out['height'] >= 0.999999 )
            return null;
        return $out;
    }

    /** @return float|null a number from 0 to 1, rounded to six places */
    protected static function fraction( $value )
    {
        if ( is_string( $value ) )
            $value = trim( $value );
        if ( !( is_int( $value ) || is_float( $value ) || ( is_string( $value ) && is_numeric( $value ) ) ) )
            return null;
        $value = (float)$value;
        if ( is_nan( $value ) || $value < 0 || $value > 1 )
            return null;
        return round( $value, 6 );
    }
}

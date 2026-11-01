<?php
/**
 * The review hold in the website sidebar (E14): an object held for review is not edited or published until a reviewer
 * releases it. The hold is the kernel's object state review_hold/held (Exponential 6.0.15, expReviewHold, made by the
 * upgrade step 6.0.15-review-hold); the roles' content/edit and content/publish policies refuse a held object, and
 * placing or releasing a hold is the kernel's state/assign.
 *
 * The sidebar shows the hold in the Page panel (who placed it, when, why), lets a reviewer place and release it with a
 * reason, and records both in the audit (content.expwt.hold.place, content.expwt.hold.release, besides the kernel's
 * content.object.state). Who, when and why are kept in the site data table (ezsite_data, expwt_hold_<object id>) while
 * the hold lasts.
 *
 * Without the state group (an older kernel, or the upgrade step not run yet) there is no hold: the Page panel says so
 * to those who administrate states, and nothing else changes. On a kernel without expReviewHold (but with the group
 * made by hand) the sidebar still refuses to publish a held object for a user whose content/publish does not allow
 * it.
 *
 * view() and the reason rules are pure (tests/phpunit/unit/expwtHoldTest.php).
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtHold
{
    const GROUP = 'review_hold';
    const STATE_FREE = 'not_held';
    const STATE_HELD = 'held';
    const KEY_PREFIX = 'expwt_hold_';
    /** The longest reason kept (characters) */
    const REASON_MAX = 500;

    /**
     * What the Page panel shows. Pure.
     *
     * @param bool $held
     * @param bool $mayPlace the user may assign the held state
     * @param bool $mayRelease the user may assign the not-held state
     * @param bool $mayEdit the user may edit the object as it is now
     * @param array|null $meta who placed it, when and why (from the site data table), null when unknown
     * @return array held, canPlace, canRelease, editable, by, since, reason
     */
    public static function view( $held, $mayPlace, $mayRelease, $mayEdit, $meta )
    {
        $meta = $held && is_array( $meta ) ? $meta : array();
        return array(
            'held' => (bool)$held,
            'canPlace' => !$held && (bool)$mayPlace,
            'canRelease' => (bool)$held && (bool)$mayRelease,
            // a held page that the user still edits (a role whose policy allows the held state, an administrator)
            'editable' => (bool)$mayEdit,
            'by' => isset( $meta['name'] ) ? (string)$meta['name'] : '',
            'since' => isset( $meta['since'] ) ? (int)$meta['since'] : 0,
            'reason' => isset( $meta['reason'] ) ? (string)$meta['reason'] : '',
        );
    }

    /**
     * A reason as it is kept: one paragraph without control characters, trimmed, at most REASON_MAX characters. Pure.
     *
     * @param mixed $text
     * @return string
     */
    public static function reason( $text )
    {
        $text = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', (string)$text );
        $text = trim( preg_replace( '/\s+/u', ' ', (string)$text ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::REASON_MAX, 'UTF-8' ) : substr( $text, 0, self::REASON_MAX );
    }

    /** @return eZContentObjectStateGroup|null the state group, null without it */
    public static function group()
    {
        $group = eZContentObjectStateGroup::fetchByIdentifier( self::GROUP );
        return $group instanceof eZContentObjectStateGroup ? $group : null;
    }

    /**
     * @param string $identifier
     * @return eZContentObjectState|null
     */
    public static function state( $identifier )
    {
        $group = self::group();
        $state = $group ? $group->stateByIdentifier( $identifier ) : null;
        return $state instanceof eZContentObjectState ? $state : null;
    }

    /** @return bool */
    public static function isHeld( eZContentObject $object )
    {
        if ( class_exists( 'expReviewHold' ) )
            return expReviewHold::isHeld( $object );
        $held = self::state( self::STATE_HELD );
        return $held !== null && in_array( (int)$held->attribute( 'id' ), array_map( 'intval', (array)$object->attribute( 'state_id_array' ) ), true );
    }

    /**
     * Whether the current user's publication of this object would be refused: it is held and their content/publish
     * does not allow it (the kernel cancels it too; the sidebar says so before trying).
     *
     * @return bool
     */
    public static function refusesPublish( eZContentObject $object )
    {
        if ( class_exists( 'expReviewHold' ) )
            return expReviewHold::refusesPublish( $object );
        return self::isHeld( $object ) && !$object->checkAccess( 'publish' );
    }

    /**
     * The Page panel's hold: null without the state group.
     *
     * @param eZContentObject $object
     * @param bool $mayEdit
     * @return array|null
     */
    public static function forObject( eZContentObject $object, $mayEdit )
    {
        $free = self::state( self::STATE_FREE );
        $held = self::state( self::STATE_HELD );
        if ( !$free || !$held )
            return null;
        $allowed = array_map( 'intval', (array)$object->attribute( 'allowed_assign_state_id_list' ) );
        return self::view( self::isHeld( $object ), in_array( (int)$held->attribute( 'id' ), $allowed, true ),
                           in_array( (int)$free->attribute( 'id' ), $allowed, true ), $mayEdit, self::meta( (int)$object->attribute( 'id' ) ) );
    }

    /** @return array|null */
    public static function meta( $objectId )
    {
        $row = eZSiteData::fetchByName( self::KEY_PREFIX . (int)$objectId );
        if ( !$row instanceof eZSiteData )
            // a hold placed by the kernel (the auto-hold, Setup > Recent content): its own data, when there is any
            return class_exists( 'expReviewHoldAuto' ) && method_exists( 'expReviewHoldAuto', 'meta' ) ? expReviewHoldAuto::meta( (int)$objectId ) : null;
        $data = json_decode( (string)$row->attribute( 'value' ), true );
        return is_array( $data ) ? $data : null;
    }

    /**
     * Places or releases the hold through the kernel's operation (state/assign decides; the kernel records
     * content.object.state), then keeps or forgets who, when and why and records the sidebar's own event with the reason.
     *
     * @param eZContentObject $object
     * @param bool $hold true to place, false to release
     * @param string $reason
     * @param eZUser $user
     * @return array the Page panel's hold after the change
     * @throws expwtApiError
     */
    public static function change( eZContentObject $object, $hold, $reason, eZUser $user )
    {
        $target = self::state( $hold ? self::STATE_HELD : self::STATE_FREE );
        if ( !$target )
            throw new expwtApiError( 409, 'no_hold', 'Review holds need Exponential 6.0.15 or later, with its upgrade step run.' );
        $reason = self::reason( $reason );
        if ( $hold && $reason === '' )
            throw new expwtApiError( 400, 'reason', 'Say why the page is held.' );
        $id = (int)$object->attribute( 'id' );
        if ( self::isHeld( $object ) === (bool)$hold )
            throw new expwtApiError( 409, 'unchanged', $hold ? 'This page is already held for review.' : 'This page is not held.' );
        $allowed = array_map( 'intval', (array)$object->attribute( 'allowed_assign_state_id_list' ) );
        if ( !in_array( (int)$target->attribute( 'id' ), $allowed, true ) )
            throw new expwtApiError( 403, 'forbidden', $hold ? 'You may not hold this page.' : 'You may not release this page.' );
        $before = self::meta( $id );
        if ( !$hold && class_exists( 'expReviewHoldAuto' ) && method_exists( 'expReviewHoldAuto', 'release' ) )
            // Exponential 6.0.15 with the auto-hold: the kernel releases it and reveals what the auto-hold hid
            expReviewHoldAuto::release( $object, $reason );
        else
            eZOperationHandler::execute( 'content', 'updateobjectstate', array( 'object_id' => $id, 'state_id_list' => array( (int)$target->attribute( 'id' ) ) ) );
        eZContentObject::clearCache( array( $id ) );
        $object = eZContentObject::fetch( $id );
        if ( !$object instanceof eZContentObject || self::isHeld( $object ) !== (bool)$hold )
            throw new expwtApiError( 409, 'not_changed', 'The hold was not changed. Try again.' );
        $userObject = $user->contentObject();
        $name = $userObject instanceof eZContentObject ? (string)$userObject->attribute( 'name' ) : (string)$user->attribute( 'login' );
        $key = self::KEY_PREFIX . $id;
        $row = eZSiteData::fetchByName( $key );
        if ( $hold )
        {
            if ( !$row instanceof eZSiteData )
                $row = eZSiteData::create( $key, '' );
            $row->setAttribute( 'value', json_encode( array( 'user' => (int)$user->attribute( 'contentobject_id' ), 'name' => $name, 'since' => time(), 'reason' => $reason ) ) );
            $row->store();
        }
        else if ( $row instanceof eZSiteData )
            $row->remove();
        if ( class_exists( 'expAuditHook' ) )
            expAuditHook::emit( $hold ? 'content.expwt.hold.place' : 'content.expwt.hold.release', array(
                'object' => array( 'type' => 'object', 'id' => $id, 'name' => (string)$object->attribute( 'name' ) ),
                'before' => array( 'held' => !$hold, 'reason' => is_array( $before ) && isset( $before['reason'] ) ? (string)$before['reason'] : null ),
                'after' => array( 'held' => (bool)$hold, 'reason' => $reason ) ) );
        return self::forObject( $object, (bool)$object->canEdit() );
    }

    /**
     * Whether the current user administrates states (and so can run the upgrade step's work by hand): only they are
     * told that holds are missing.
     *
     * @return bool
     */
    public static function mayAdministrate( eZUser $user )
    {
        $access = $user->hasAccessTo( 'state', 'administrate' );
        return isset( $access['accessWord'] ) && $access['accessWord'] === 'yes';
    }
}

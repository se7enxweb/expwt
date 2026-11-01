<?php
/**
 * Comments held for moderation (expwt.ini [Comments] HideNew): a comment published for the first time by someone who
 * may not approve it (no content/hide of the comment) is hidden right after publishing, inside the kernel's publish
 * operation, so it never shows on the page. The website sidebar's Page panel lists it as waiting for moderation, and
 * Approve reveals it (E4).
 *
 * Called by the content edit handler of expwt (content/expwthandler.php, content.ini [EditSettings]
 * ExtensionDirectories[]=expwt), which the kernel runs in the publish operation's step
 * publish-object-extension-handler for every publication: in a web request, a command, a cronjob and a workflow
 * that publishes later (an approval), after the nodes are placed and before the transaction is committed, the view
 * cache cleared and the object indexed.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtCommentModeration
{
    /**
     * Pure: is [Comments] HideNew switched on? enabled (or true, 1, yes) only; anything else is off
     *
     * @param mixed $value
     * @return bool
     */
    public static function isOn( $value )
    {
        return in_array( strtolower( trim( (string)$value ) ), array( 'enabled', 'true', '1', 'yes' ), true );
    }

    /**
     * Pure: is this publication held for moderation?
     *
     * @param bool        $enabled        [Comments] HideNew
     * @param string      $class          the class identifier of the published object
     * @param string|null $commentClass   the class of the comments here (expservices.ini [Community] CommentClass), null: none
     * @param bool        $first          the object's first publication (no version of it was published before)
     * @param bool        $creatorMayHide the creator of the version may hide (approve) the comment's node
     * @param bool        $hidden         the node is hidden already
     * @return bool
     */
    public static function holds( $enabled, $class, $commentClass, $first, $creatorMayHide, $hidden )
    {
        return (bool)$enabled && $commentClass !== null && (string)$class === (string)$commentClass
               && (bool)$first && !$creatorMayHide && !$hidden;
    }

    /** @return bool expwt.ini [Comments] HideNew of this request's siteaccess */
    public static function enabled()
    {
        $ini = eZINI::instance( 'expwt.ini' );
        return $ini->hasVariable( 'Comments', 'HideNew' ) && self::isOn( $ini->variable( 'Comments', 'HideNew' ) );
    }

    /**
     * The edit handler's publish step: hides each node of a new comment whose creator may not approve it, through the
     * kernel's hide (eZContentOperationCollection::changeHideStatus, the body of the content/hide operation: the
     * hidden subtree, the content/cache event and the search engine's visibility). The publish operation's own
     * clear-object-view-cache step, which runs after this one, then clears the view cache of the comment and of the
     * page it is below, so the page does not keep showing it.
     *
     * @param int $objectId
     * @param int $versionNum
     * @return int[] the ids of the nodes hidden
     */
    public static function published( $objectId, $versionNum )
    {
        if ( !self::enabled() )
            return array();
        $commentClass = class_exists( 'expwtGateway' ) ? expwtGateway::commentClass() : 'comment';
        $object = eZContentObject::fetch( (int)$objectId );
        if ( !$object instanceof eZContentObject || $commentClass === null || (string)$object->attribute( 'class_identifier' ) !== $commentClass )
            return array();
        $version = $object->version( (int)$versionNum );
        $creatorId = $version instanceof eZContentObjectVersion ? (int)$version->attribute( 'creator_id' ) : (int)$object->attribute( 'owner_id' );
        $first = self::firstPublication( (int)$objectId, (int)$versionNum );
        $hiddenIds = array();
        foreach ( (array)$object->assignedNodes() as $node )
        {
            if ( !$node instanceof eZContentObjectTreeNode )
                continue;
            $creatorMayHide = $creatorId > 0 && $node->checkAccess( 'hide', false, false, false, false, $creatorId ) == 1;
            if ( !self::holds( true, (string)$object->attribute( 'class_identifier' ), $commentClass, $first, $creatorMayHide, (bool)$node->attribute( 'is_hidden' ) ) )
                continue;
            eZContentOperationCollection::changeHideStatus( (int)$node->attribute( 'node_id' ) );
            $hiddenIds[] = (int)$node->attribute( 'node_id' );
        }
        if ( $hiddenIds )
            eZDebug::writeNotice( 'Comment ' . (int)$objectId . ' held for moderation: node(s) ' . implode( ', ', $hiddenIds ) . ' hidden', __METHOD__ );
        return $hiddenIds;
    }

    /**
     * @return bool no version of the object but this one was ever published (none is published or archived: the
     *              publish operation archives the previous published version before this step)
     */
    protected static function firstPublication( $objectId, $versionNum )
    {
        $db = eZDB::instance();
        $rows = $db->arrayQuery( 'SELECT COUNT(*) AS n FROM ezcontentobject_version WHERE contentobject_id = ' . (int)$objectId
                                 . ' AND version <> ' . (int)$versionNum
                                 . ' AND status IN (' . (int)eZContentObjectVersion::STATUS_PUBLISHED . ', ' . (int)eZContentObjectVersion::STATUS_ARCHIVED . ')' );
        return isset( $rows[0]['n'] ) && (int)$rows[0]['n'] === 0;
    }
}

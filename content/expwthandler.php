<?php
/**
 * The content edit handler of expwt (content.ini [EditSettings] ExtensionDirectories[]=expwt): the kernel runs its
 * publish() in every publish operation (the step publish-object-extension-handler). It holds new comments for
 * moderation when expwt.ini [Comments] HideNew is enabled (expwtCommentModeration); the input steps of the edit
 * form are the base class's, which do nothing.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtHandler extends eZContentObjectEditHandler
{
    public function publish( $contentObjectID, $contentObjectVersion )
    {
        if ( class_exists( 'expwtCommentModeration' ) )
            expwtCommentModeration::published( (int)$contentObjectID, (int)$contentObjectVersion );
    }
}

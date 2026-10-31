<?php
/**
 * The strings of the sidebar in the user's language (translation context extension/expwt, the catalogues in
 * extension/expwt/translations/<locale>/translation.ts). The panels look a string up by its English source
 * (expwt.js t()); the boot answer carries all() once per page, the closed tab only tab() in its markup.
 *
 * SOURCES is generated from the code (every t('...') of the JavaScript, the refusals of the classes, the views'
 * strings); a test keeps it current.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtStrings
{
    const CONTEXT = 'extension/expwt';

    /** What the closed tab and a visitor's panel show before the boot answer (or without one: not signed in) */
    const TAB = array( 'Website sidebar', 'Edit', 'Menu', 'Arrow keys move it, Enter opens it.', 'Width of the sidebar', 'Panels',
                       'Close the sidebar', 'Welcome', 'Sign in to edit this website.', 'Sign in', 'Register', 'Search',
                       'Light or dark', 'Like the site', 'Like the system', 'Light', 'Dark', 'The sidebar was moved.', 'The sidebar is on the left.',
                       'The sidebar is on the right.', 'The sidebar is at the top.', 'The sidebar is at the bottom.',
                       'Ctrl and an arrow key put it on another edge.', 'Height of the sidebar', 'Loading ...',
                       'The sidebar could not reach the server.' );

    /* sources:start (generated: every string of the panels, the views and the refusals) */
    const SOURCES = array(
        'There is no such action.',
        'This action changes something and is only sent by POST.',
        'You may not do this.',
        'Something went wrong on the server.',
        'Nothing to save.',
        'This page is not there, or you may not read it.',
        'Which page?',
        'This draft is not there any more.',
        'This field cannot be edited here.',
        'This value does not fit the field.',
        'Someone else changed these fields in the meantime.',
        'Fill in the required fields first.',
        'This is not a date and time.',
        'The page would be taken down before it is published.',
        'Give a title.',
        'There is no such class.',
        'Where to?',
        'The pages here are not sorted by priority.',
        'This is not the list of pages here.',
        'This action changes something: send it with expwtWrite.',
        'The input is not JSON.',
        'Something went wrong.',
        'This page is not content of the site. Open a page of the site to edit it.',
        'Loading ...',
        'Draft',
        'Published',
        'Hidden',
        '%name is editing this page since %time.',
        'Take over',
        'You may read this page but not edit it.',
        'Edit on the page',
        'Preview',
        'Full editor',
        'required',
        'Publish later or take down at a date',
        'Publish at',
        'Take down at',
        'In the full editor: %fields',
        'More',
        'Save draft',
        'Browse tags',
        'Publish',
        'Editing',
        'Done',
        'Close',
        'Discard draft',
        'Saving ...',
        'The draft is saved.',
        'Someone else changed these fields in the meantime. Choose for each field which text to keep, then save again.',
        'Keep mine',
        'Take theirs',
        'Save with these choices',
        'Change something first: there is no draft to publish.',
        'Publishing ...',
        'Published.',
        'Sent for approval.',
        'Scheduled for %time.',
        'The publishing was stopped by a workflow.',
        'Throw this draft away?',
        'The draft is gone.',
        'Edit',
        'Click a text on the page to edit it. It is saved with the draft.',
        'None of the fields shows on this page as it is; use the form.',
        'Yes',
        'Text of the link',
        'You may not create anything here.',
        'Where',
        'Below this page',
        'Beside this page',
        'A draft was made. Fill it in and publish it.',
        'Type',
        'Title',
        'Create draft',
        'Reveal',
        'Hide',
        'The page is hidden.',
        'The page is visible again.',
        'Move ...',
        'Add location ...',
        'Move to trash',
        'Move this page to the trash?',
        'Pages below this one',
        'There are no pages below this one.',
        'Move up',
        'Move down',
        '%name moved to place %n.',
        'Save the order',
        'The order is saved.',
        'These pages are sorted automatically, not by hand.',
        'Move "%name" to ...',
        'Add "%name" also below ...',
        'Cancel',
        'In: %name',
        'Up one level',
        'Choose',
        'Open',
        'Move the page below "%name"?',
        'Add the page also below "%name"?',
        'Done.',
        'Last visit: %time',
        'My account',
        'Password',
        'Passkeys',
        'Sessions and devices',
        'Security',
        'Notifications',
        'My drafts',
        'Bookmarks',
        'The sidebar',
        'Anchored at',
        'Left',
        'Right',
        'Position',
        'Top',
        'Bottom',
        'When open',
        'Beside the page',
        'Over the page',
        'Keep these settings only on this device',
        'Sign out',
        'Website sidebar',
        'Menu',
        'Arrow keys move it, Enter opens it.',
        'Ctrl and an arrow key put it on another edge.',
        'Width of the sidebar',
        'Panels',
        'Close the sidebar',
        'Welcome',
        'Page',
        'Create',
        'Structure',
        'Me',
        'Height of the sidebar',
        'The sidebar is on the left.',
        'The sidebar is on the right.',
        'The sidebar is at the top.',
        'The sidebar is at the bottom.',
        'The sidebar was moved.',
        'The sidebar could not reach the server.',
        'Please confirm it is you in the new window.',
        'Allow this site to open a window to confirm it is you.',
        'Sign in to edit this website.',
        'Sign in',
        'Register',
        'Search',
        'Light or dark',
        'Like the site',
        'Like the system',
        'Light',
        'Dark',
        'Thank you. You can go back to the page; this window closes by itself.',
        'Verify it\'s you',
    );
    /* sources:end */

    /** @return array source => translation, every string */
    public static function all()
    {
        return self::translate( self::SOURCES );
    }

    /** @return array source => translation, the closed tab's strings */
    public static function tab()
    {
        return self::translate( self::TAB );
    }

    /** @return string */
    public static function tr( $source )
    {
        return class_exists( 'ezpI18n' ) ? (string)ezpI18n::tr( self::CONTEXT, $source ) : (string)$source;
    }

    protected static function translate( array $sources )
    {
        $out = array();
        foreach ( $sources as $source )
            $out[$source] = self::tr( $source );
        return $out;
    }
}

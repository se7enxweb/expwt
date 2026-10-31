<?php
/**
 * expwt/reauth/<action>: the kernel's "Verify it's you" page for a sidebar action, in the small window the sidebar
 * opens. The kernel asks before this view runs (expwtReauthTriggerHandler names the action's trigger group); once
 * the user has answered, this page tells the sidebar (expwt-reauth.js, postMessage to the window that opened it) and
 * closes, and the sidebar sends the action again.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

$tpl = eZTemplate::factory();
$tpl->setVariable( 'action', (string)$Params['Action'] );
return array( 'content' => $tpl->fetch( 'design:expwt/reauth.tpl' ),
              'path' => array( array( 'text' => ezpI18n::tr( 'extension/expwt', 'Verify it\'s you' ), 'url' => false ) ) );

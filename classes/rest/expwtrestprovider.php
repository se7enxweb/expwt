<?php
/**
 * The REST routes of the sidebar (rest.ini [ApiProvider] ProviderClass[expwt]), for a client with a token (an OAuth
 * token or a personal API key):
 *
 *   GET  /api/expwt/v1/<action>?input={...}     a reading action (boot, me, page.load, create.classes, tree.children, inbox.load)
 *   POST /api/expwt/v1/<action>                  any action, the input as the JSON body
 *
 * The same actions, checks and answers as the browser's expwt/api (expwtApi). "Verify it's you" belongs to the web
 * pages and is never asked here, as for the rest of the REST interface.
 *
 * @copyright Copyright (C) 1998 - 2026 Exponential Foundation and 7x. All rights reserved.
 * @license GNU General Public License v2.0 (or any later version)
 * @package expwt
 */

class expwtRestProvider implements ezpRestProviderInterface
{
    public function getRoutes()
    {
        return array(
            'expwtAction' => new ezpRestVersionedRoute(
                new ezpMvcRailsRoute( '/:sidebarAction', 'expwtRestController', array( 'http-get' => 'action', 'http-post' => 'action' ) ), 1 ),
        );
    }

    public function getViewController()
    {
        return new expwtRestViewController();
    }
}

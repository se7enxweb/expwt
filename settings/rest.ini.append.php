<?php /* #?ini charset="utf-8"?

# /api/expwt/v1/<action>: the sidebar's actions for a client with a token (OAuth or a personal API key)
[ApiProvider]
ProviderClass[expwt]=expwtRestProvider

[expwtRestController_CacheSettings]
ApplicationCache=disabled

# A personal API key needs the scope expwt (given to owners with expwt/use); every action still checks the
# expwt function and the kernel policy of what it changes
[ApiKeySettings]
Scopes[expwt]=expwt/use
ScopeNames[expwt]=Use the website sidebar
RouteScopes[expwtRestController_action]=expwt

*/ ?>

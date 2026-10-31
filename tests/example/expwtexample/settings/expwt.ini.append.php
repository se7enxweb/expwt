<?php /* #?ini charset="utf-8"?

# expwtexample: what an extension adds to the website sidebar (expwt, decision 32). Copy the extension, rename
# "expwtexample" everywhere, activate it (ActiveExtensions[]) and regenerate the autoloads.

[RegistrySettings]
PanelList[]=pagecache
ActionList[]=expwtexample.info
ActionList[]=expwtexample.clearcache
ActionList[]=expwtexample.search
HighlightList[]=pagecache_headings
SearchProviderList[]=expwtexample

# A panel of its own, before Me. Its script registers it: expwtRegister( 'panel', 'pagecache', fn )
[Panel_pagecache]
Label=Page cache
Context=extension/expwtexample
Script=expwtexample-expwt.js
Stylesheet=expwtexample-expwt.css
Before=me
Function=use
Policy[]=setup/managecache

# Reading: by GET, no CSRF token
[Action_expwtexample.info]
Class=expwtexampleExpwtActions
Writes=false
Policy[]=setup/managecache

# Changing: by POST with the CSRF token; "Verify it's you" asks where the administration's setup/cache asks
[Action_expwtexample.clearcache]
Class=expwtexampleExpwtActions
Writes=true
Policy[]=setup/managecache
KernelView=setup/cache

[Action_expwtexample.search]
Class=expwtexampleExpwtActions
Writes=false

# While the panel is shown, the page's main headings are outlined; a click tells the panel which one
[Highlight_pagecache_headings]
Panel=pagecache
Selector=main h1, article h1
Label=A heading of this page

# A source of the sidebar's search (decision 29): input query, answers items (title, url)
[SearchProvider_expwtexample]
Label=Pages by name
Context=extension/expwtexample
Action=expwtexample.search

*/ ?>

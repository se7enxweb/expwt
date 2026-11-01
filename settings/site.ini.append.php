<?php /* #?ini charset="utf-8"?

[TemplateSettings]
ExtensionAutoloadPath[]=expwt

[RegionalSettings]
TranslationExtensions[]=expwt

# The sidebar's hook template at the end of every page body (the kernel's
# design:pagelayout/hooks/body_end.tpl includes each entry)
[PageLayoutHooks]
BodyEnd[]=expwt/hook.tpl

# Pagelayouts without the hook: expwt places the sidebar before </body> (expwt.ini InjectWithoutHook)
[Event]
Listeners[]=response/preoutput@expwtHook::preOutput
Listeners[]=response/output@expwtHook::output

# "Verify it's you": the sidebar's actions ask with the trigger groups of the administration's
# views they stand for
[ReauthSettings]
TriggerHandlers[]=expwtReauthTriggerHandler
# The real send of a newsletter edition to its subscribers from the sidebar always asks (E3)
Groups[]=newsletter

# Sending a newsletter edition (cjw_newsletter) to its list's subscribers from the website sidebar.
# expwtReauthTriggerHandler names this group for the sidebar's real send; no administration view is
# in it. Switched off (here or on setup/reauth), the sidebar does not send at all: the
# administration's send page does.
[ReauthGroup_newsletter]
Enabled=true
Rules[]

# The Performance panel (E12): the kernel collects how each page was rendered (time, SQL, view cache,
# cache-blocks, the template tree) for a user with expwt/performance also without debug output
# (Exponential 6.0.15 on; an older kernel ignores the setting and collects with debug output only)
[DebugSettings]
CollectStatsPolicyList[]=expwt/performance

# Editing on the page:the kernel marks every rendered attribute (field, object, version) for a user
# who may edit with the sidebar; visitors' pages stay as they are
[ContentFieldMarkerSettings]
Handlers[]=expwtFieldMarkerHandler

*/ ?>

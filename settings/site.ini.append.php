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

# "Verify it's you": the sidebar's actions ask with the trigger groups of the administration's
# views they stand for
[ReauthSettings]
TriggerHandlers[]=expwtReauthTriggerHandler

# Editing on the page: the kernel marks every rendered attribute (field, object, version) for a user
# who may edit with the sidebar; visitors' pages stay as they are
[ContentFieldMarkerSettings]
Handlers[]=expwtFieldMarkerHandler

*/ ?>

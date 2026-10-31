<!DOCTYPE html>
{* The page of expwt/field in the sidebar's frame: the field's editor and the scripts and stylesheets its edit template
   asks for (ezscript_require, ezcss_require), nothing of the site around it. The meta tells expwtHook not to place
   a sidebar into it. *}
<html lang="{$site.http_equiv.Content-language|wash}" class="expwt-field-page">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="expwt" content="field" />
<meta name="robots" content="noindex, nofollow" />
<title>{if is_set( $module_result.path[0].text )}{$module_result.path[0].text|wash}{/if}</title>
{ezcss_load( array( 'expwt-field.css' ) )}
{ezscript_load()}
</head>
<body class="expwt-field-body">
{$module_result.content}
</body>
</html>

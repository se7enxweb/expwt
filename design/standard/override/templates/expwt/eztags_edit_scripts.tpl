{* eztags/edit_scripts.tpl in the sidebar's frame (override.ini [expwt_frame_eztags_edit_scripts]): eztags' plain
   JavaScript editor (eztags.js and tagsstructuremenu.js for the picker's tree, as in admin4), no jQuery and none of
   the old look; expwt-field.css draws it in the sidebar's look. The address of ezjscore's server calls as a data
   block, as eztags' admin4 template gives it. *}
{ezscript_require( array( 'eztags.js', 'tagsstructuremenu.js' ) )}
{ezcss_require( array( 'eztags.css' ) )}
{run-once}
<script type="application/json" id="eztags-settings">{json_encode( hash( 'call_url', 'ezjscore/call/'|ezurl( no ) ) )}</script>
{/run-once}

{* eztags/edit_scripts.tpl in the sidebar's frame (override.ini [expwt_frame_eztags_edit_scripts]): eztags' plain
   JavaScript editor (eztags.js and tagsstructuremenu.js for the trees, as in admin4), no jQuery and none of eztags'
   own styles; expwt-field.css draws the field, its tree and its dialog with the sidebar's tokens. The address of
   ezjscore's server calls as a data block, as eztags' admin4 template gives it. *}
{ezscript_require( array( 'eztags.js', 'tagsstructuremenu.js' ) )}
{run-once}
<script type="application/json" id="eztags-settings">{json_encode( hash( 'call_url', 'ezjscore/call/'|ezurl( no ) ) )}</script>
{/run-once}

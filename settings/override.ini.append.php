<?php /* #?ini charset="utf-8"?

# A tags field (eztags) in the sidebar's frame (expwt/field sets the design key expwt_frame): eztags'
# plain JavaScript editor (eztags.js, as admin4 has it) in place of its jQuery one, drawn in the sidebar's
# look by expwt-field.css. Only in the frame: the site's own pages keep eztags as it is.
[expwt_frame_eztags_edit_scripts]
Source=eztags/edit_scripts.tpl
MatchFile=expwt/eztags_edit_scripts.tpl
Subdir=templates
Match[expwt_frame]=1

# ... the field on the sidebar's model: chips, the labeled search, "Browse tags" with the tag tree
[expwt_frame_eztags_edit]
Source=content/datatype/edit/eztags.tpl
MatchFile=expwt/eztags_edit.tpl
Subdir=templates
Match[expwt_frame]=1

# ... and the dialog of "Add new" (the new tag's parent) as a <dialog> on the sidebar's model
[expwt_frame_eztags_modal_dialog]
Source=ezjsctemplate/modal_dialog.tpl
MatchFile=expwt/eztags_modal_dialog.tpl
Subdir=templates
Match[expwt_frame]=1

*/ ?>

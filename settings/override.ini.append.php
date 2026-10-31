<?php /* #?ini charset="utf-8"?

# A tags field (eztags) in the sidebar's frame (expwt/field sets the design key expwt_frame): eztags'
# plain JavaScript editor (eztags.js, as admin4 has it) in place of its jQuery one, drawn in the sidebar's
# look by expwt-field.css. Only in the frame: the site's own pages keep eztags as it is.
[expwt_frame_eztags_edit_scripts]
Source=eztags/edit_scripts.tpl
MatchFile=expwt/eztags_edit_scripts.tpl
Subdir=templates
Match[expwt_frame]=1

# ... and with its tag tree to choose from, for a field of eztags' Default builder
[expwt_frame_eztags_view_default]
Source=content/datatype/edit/view/default.tpl
MatchFile=expwt/eztags_view_default.tpl
Subdir=templates
Match[expwt_frame]=1

*/ ?>

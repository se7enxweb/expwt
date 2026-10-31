<?php /* #?ini charset="utf-8"?

# A page a field's editor in the sidebar's frame goes to (content/browse, when a relation field looks for
# an object): drawn in the frame's own page layout, without the site around it. expwt/field sends the browse
# to layout/set/expwtframe/...; the layout module keeps that prefix on the browse's links and forms.
[expwtframe]
PageLayout=expwt/field_pagelayout.tpl

*/ ?>

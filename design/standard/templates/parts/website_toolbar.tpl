{* expwt compatibility: ezwt's include name for a design not yet moved to expwt. expwt replaces ezwt (the two are
   never installed together), so a pagelayout that still includes design:parts/website_toolbar.tpl gets expwt's
   page toolbar instead of a missing template. New designs include design:parts/expwt_page_toolbar.tpl. *}
{include uri='design:parts/expwt_page_toolbar.tpl'}

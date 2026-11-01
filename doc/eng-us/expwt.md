# expwt: the website sidebar of Exponential

expwt puts a slim tab at the edge of the site's own pages. Opened, it is a panel to edit the page you are looking at,
create pages, move them in the tree and look after your own account, without going to the administration. It
replaces ezwt, the website toolbar. Version 0.1.0 is the first phase: the sidebar itself, the Me panel and the editing
core. Layouts, templates, media, shop and the administration's panels follow.

## Switching it on

expwt ships switched off (until its 1.0 release). In `settings/siteaccess/<siteaccess>/expwt.ini.append.php` (or
`settings/override/` for every siteaccess):

```ini
[ExpwtSettings]
Enabled=enabled
```

The sidebar stays out of the administration (`ShowInAdmin=disabled`, the default): its siteaccesses get no sidebar,
no hook output and no request. `SiteAccessList[]` and `SiteAccessExcludeList[]` choose siteaccesses one by one.

**Only on some siteaccesses, the other way:** activate expwt per siteaccess with `ActiveAccessExtensions[]=expwt` in
`settings/siteaccess/<front siteaccess>/site.ini.append.php` instead of `ActiveExtensions[]=expwt` for the whole
installation. The trade-off: the extension then exists only there - its module (`expwt/api`, `expwt/field`), its
policies in the role editor, its translations and its settings are unknown to every other siteaccess, the
administration included, so roles are edited where it is active or with the policies typed in; the settings above
keep the module everywhere and only leave the sidebar out.

An installation that used ezwt moves over with `./console exp:wt:migrate-from-ezwt --apply` (or the upgrade step
`6.0.15-expwt-enable`): ezwt's hidden classes, its policies as expwt policies, expwt in ezwt's place in the active
extensions. A dry run lists the changes first.

## The sidebar

- **Where:** docked to one of the four edges of the window (right by default). On the **left or right** it is a panel
  just below the site header, staying at the top of the window once the header has scrolled away. On the **top** it is
  a horizontal bar under the header, which stays at the top of the window once the header has scrolled away; on the
  **bottom** it is a horizontal bar above whatever the site keeps at the bottom of the window (a fixed footer, a
  cookie bar). On top and bottom its tab is anchored at the left or the right end of the bar. Closed it is a tab; open
  it is a panel (left, right) or a bar (top, bottom).
- **Moving it:** drag the tab along its edge, or to any other edge (mouse, pen or finger); it docks to the nearest
  edge. With the keyboard, focus the tab:
  - the arrow keys along the edge move it (Shift for bigger steps), the arrow keys across it switch to the opposite
    edge (left and right on a side, up and down on top and bottom);
  - **Ctrl** with an arrow key docks it to that edge; **Page Down** and **Page Up** go round the edges (right,
    bottom, left, top);
  - **Home** and **End** take it to the start or the end of the edge (on top and bottom: anchored at the left or the
    right end);
  - Enter opens it. **Alt+Shift+E** opens it from anywhere and puts the focus in it; Escape closes it.
- **Shortcuts:** all of them are Alt+Shift with a letter or a digit, the one combination the browsers and the systems
  leave free (Ctrl or Cmd with a letter belongs to the browser, Alt with a letter opens its menus, Ctrl+Alt is AltGr):

  | Keys | |
  |---|---|
  | Alt+Shift+E | open the sidebar, or put the focus in it (`ShortcutKey`) |
  | Alt+Shift+D | save the draft of the Page panel |
  | Alt+Shift+K | edit on the page, on or off |
  | Alt+Shift+H | the cheat sheet: every shortcut in effect for you on this page, and the keys of the tab |
  | Alt+Shift+1 to 9 | the panels in their order (1 Page, 2 Create ...) |

  The letters are changed in the Me panel, **Keyboard shortcuts**, kept with the sidebar's other settings (for the
  user on the server, or only on this device). A letter is refused with its reason when it is not one letter A to Z,
  when another shortcut has it, or when the browser or the system uses it with Alt+Shift (A, B, I, L, M, N, S, T:
  Chrome's and Edge's feedback form, toolbar, dialogs and bookmarks bar, ChromeOS's launcher, files, notifications and
  status area); **Back to the defaults** puts them back. Screen readers do not use Alt+Shift with a letter (NVDA, JAWS,
  Narrator and Orca use Insert or Caps Lock, VoiceOver Control+Option), and in their browse mode they pass it on. A key
  the page gives one of its own links (`accesskey`, which Firefox reaches with Alt+Shift) stays the page's: the
  sidebar leaves it alone there and the cheat sheet says so. On a Mac, Option+Shift with a letter types a character:
  in a text field that has the focus the field gets it.
  The Me panel has the same as a setting: **Position** left, right, top or bottom, and for top and bottom
  **Anchored at** left or right.
- **The bar on top and bottom** is laid out across the width, so its main controls never need a scroll bar: one row
  next to the edge holds the panels' tabs, the open panel's main actions (Save draft, Publish, Discard draft; Sign out
  on Me), the status and the close button. Below it (top) or above it (bottom): Page shows its fields in a grid of two
  to six columns, long text two columns wide with its format buttons beside its label; the fields that do not fit the
  bar's height, the date to publish and the fields of the full editor are behind **More** (the bar fits again when its
  height or the window changes). Create is one row (where, type, title, Create draft); Structure shows the page's
  actions in one row and the pages below it as a strip that scrolls sideways; Me shows the account, its links and each
  setting side by side; a visitor's panel is one row. On a phone the bar is a sheet as high as its contents.
- **Size:** the width of the panel on the left and right, the height of the bar on top and bottom (280 px at first): drag the panel's
  inner edge, or focus it and use the arrow keys (Home and End: smallest and largest).
- **Beside or over the page:** on a wide window the open panel pushes the page aside (left, right), down below the
  bar (top: the header stays where it is, what follows it moves down) or gives it room at its end (bottom); on a phone
  (below 768 px) it lies over the page. The Me panel lets each user choose "over the page" on a wide window too.
- **Remembered:** edge, end of the bar, place, width and height, open or closed, beside or over, and light or dark are
  kept for the user on the server (they follow to every device), or only on this device ("Keep these settings only on
  this device"). A visitor who is not signed in keeps them in the browser. A position saved before the top and bottom
  edges existed (left or right) is read as that edge.
- **Light or dark:** the user's choice for the sidebar, else their choice for the administration (light or dark: it
  wins on every design), else the page's: on a site design with its own light/dark switch (the media designs:
  site, smarty, twig) the mode that switch shows, followed at once when it is flipped; elsewhere the system's.
  A visitor who is not signed in, and a user whose administration mode is "system", get the site's mode.
  The sidebar looks like the administration (admin4) on every site design: it lives in a Shadow DOM, so the site's
  styles never reach it and its styles never reach the site.
  The sidebar is drawn in its final colors from the first frame of a page: it appears only once its stylesheet
  applies, and a signed-in user's mode is known before the server's answer arrives (an admin4 page carries the
  preference; elsewhere this device remembers the last answer), so a reload never flashes light and then dark.

## Panels

| Panel | For | What |
|---|---|---|
| Welcome | visitors not signed in (`ShowToAnonymous`) | sign in, register, search, light or dark |
| Page | `expwt/use` (reading), `expwt/edit` (changing) | the page's fields in a form, editing on the page, save the draft, preview, the page on several devices side by side (see "Device preview" below), the full editor, publish now or at a date, take down at a date, discard the draft; under each image field its focal point and crop with a preview of every size (see "Focal point and crop" below) |
| Page: comments | `expwt/edit`; each action the kernel's policy of the comment (`content/hide`, `content/remove`) or of the page (`content/create` of a comment) | the page's comments, moderated in place, below the fields of the Page panel. The comments are content below the page (the class of expservices.ini `[Community] CommentClass`, `comment` by default), so the kernel's content policies are the comments' policies. Newest first, at most 50; hidden comments are the ones waiting for moderation and are listed, marked, only for those who may reveal them (`content/hide` of the comment). **Approve** shows a hidden comment, **Hide** hides a shown one (both `content/hide`, through the kernel's hide operation, as the administration's Hide and Reveal); **Spam ...** asks once more in the panel and moves the comment to the trash (`content/remove`; it can be restored from the trash in the administration). **Reply** writes a comment below the page by the user (subject "Re: " and the comment's subject, the user's name as the author), published through the kernel's publishing, so an approval workflow applies; **Write a comment** does the same for the page itself. "Verify it's you" asks exactly where the administration asks for hiding, removing and publishing. Each moderation and reply is audited (`content.comment.approve`, `.hide`, `.spam`, `.reply`). The number of comments waiting for moderation is a badge on the Page panel's tab (from the boot, no extra request; none at 0). With `[Comments] HideNew=enabled` a new comment waits for moderation: a comment published for the first time by someone who may not approve it (no `content/hide` of the comment: a visitor, a member) is hidden right after publishing, inside the kernel's publish operation, so the page never shows it; moderators' own comments and their replies stay visible |
| Create | `expwt/create` | a new page below or beside this one: the classes you may create there, a title, then fill it in the Page panel and publish |
| Structure | `expwt/structure` | hide and reveal, move and add a location (choose the place in a tree on the site), move to the trash, sort the pages below by dragging or with the up and down buttons |
| SEO | `expwt/edit` | the SEO check of the page as it is rendered: its title and description (length), its headings (one main heading, no level skipped), the alternative texts of its images, its canonical address and robots, and whether each internal link leads somewhere (the server looks it up as the kernel would answer it: a view, a URL alias or redirect, a wildcard, a file of the storage or a design; links of another siteaccess are not checked). Problems first; a finding opens the field it is about in the Page panel (the title or the metadata field, the field an image or a link of the text belongs to) or shows its place on the page. Nothing is saved |
| Sites | `expwt/use` | the page on the other sites of the installation and in its other languages. The sites are those of the kernel's site menu (the admin4 globe: `[SiteAccessSettings] RelatedSiteAccessList[]`, no administration, only where the user may log in); **Open** goes to the page there through the kernel's language switcher (`switchlanguage/to`, which finds the page's address in that site's languages) with the sidebar as it was (open, on the same panel); **Side by side** shows this page and the other one next to each other (one below the other on a phone), each scrolling on its own. The translations: each language of the page with **Edit here** (the Page panel edits that translation; a button there goes back to the site's own language) and **Side by side** (the kernel's preview of it), and the languages the user may translate the page into with **Translate** (the full editor) |
| Forms | `expwt/edit` (the submissions also the kernel's `infocollector/read`) | the page's forms: the page itself when its class collects information (the kernel's information collection: a contact form, a lead form ...) and the forms it relates to or embeds, each with its number of submissions. With `infocollector/read` (the administration's Collected information, the policy that guards personal data there) also its latest ten submissions (when, who or Visitor, the first two answers): one opens with every answer, as the kernel's `infocollector/view` shows it (and audited the same way: which submission was opened, never its values); **Export CSV** is the kernel's `infocollector/export` of the form, **In the administration** its list there. A form that collects sensitive data (collect.ini `CollectSensitiveData`) says so. Without the policy: the numbers only, and the sidebar refuses a submission. Nothing is changed or removed here |
| Newsletter | `expwt/edit` and cjw_newsletter's `newsletter/editorial` (making an edition) or `newsletter/send` (sending); only where cjw_newsletter is active | the page's newsletter. On an edition: the edition. On any other page: the editions that carry the page as an article, and **Make an edition from this page**: a newsletter list (those where the user may create an edition, with their number of subscribers), a title (the page's by default) and an optional introduction make a new edition below the list, published, with the page as its article as the administration's article picker takes it (its title, its intro and "Read more", counted by the article statistics). Each edition shows its list, state, subscribers, approval and last send. With `newsletter/send`: **Send a test** to the addresses given (the list's test addresses by default, marked as a test, at most a test group's size) and **Send to the subscribers ...**: the panel names the edition, the list and the number of subscribers and asks once more, then "Verify it's you" asks in the small window, every time a confirmation is not fresh (its own trigger group `newsletter`, shipped on; switched off, the sidebar does not send at all and points to the administration). The send is the administration's: the current version, now, checked by the newsletter's approval, skin, tracking and throttling as the send page checks it; the newsletter's cronjob mails it. An edition being sent, in the archive, without approval where its list needs one, or with a list without subscribers says why it cannot be sent. Where the installation writes newsletter mail to files (cjw_newsletter.ini `TransportMethod*=file`), the mail goes there |
| URLs | `expwt/edit` (an alias also `content/edit` of the page, a wildcard also `content/urltranslator`) | the page's addresses: its address in each language, its old addresses (they redirect to the current one), the aliases added to it (each marked when it redirects) and the URL wildcards that lead to it, all as this siteaccess answers them (without its path prefix). **Addresses visitors did not find**: the addresses of the kernel's not-found log (the end of `404.log`, without the visitors' addresses and the queries) whose last part is the last part of one of the page's addresses and that still answer "not found", most often first, each with **Redirect to this page**, which adds that address as an alias that redirects. **Add an address**, as the administration's URL aliases of a page add one (`content/urlalias`): an address from the top of the site, a language, and whether it redirects to the page's address or shows the page under it (the kernel cleans the address as it does there and says so; a taken address is refused). **Add a wildcard**, as the administration's URL wildcards add one (`content/urlwildcards`): an address pattern (a `*` matches the rest), the page's address as its destination, redirecting or not. Nothing is removed here. Adding is audited as `content.urlalias.change`, as in the administration; "Verify it's you" asks as it asks for those views |
| Calendar | `expwt/edit` (moving a draft's or a page's date also `content/edit` of it; moving a layout variant `expwt/layouts` and explayouts' `explayouts_ui_api/edit`) | everything scheduled for this page, this page and below, or the whole site, a month at a time (six weeks from a Monday): the drafts the "Wait until date" event holds (publish), the pages the hide cronjob takes down (take down) and the layout variants (an explayouts rule with a time condition: its start and its end). Only what you may read; a mark per item on its day, and the list of the weeks below. Drag an item's handle in the list or its mark in the grid to another day (the same time of day), or **Move** it to a date and time; every move is confirmed first and must lie in the future. See "The calendar" below |
| Insights | `expwt/insights` | read-only, from the kernel's own statistics, no third-party tracking: the page's views (the view counter the cronjob `updateviewcount` fills from the access log; where it is off the panel says so), the searches on this site for words of the page's title (the kernel's search log, with how often and how many results), and that the kernel records no referrers |
| Inbox | `expwt/use` (approving also the kernel's `collaboration` policy) | the approvals of the publishing workflow that wait for you (the kernel's approve event): each page with its author and last comment, **Open the draft**, and for its approvers a comment with **Approve** or **Deny**, decided through the kernel's own `collaboration/action` view, exactly as in the administration (the workflow publishes an approved page on its next run; a denied one is a draft of its author again). Beside them the other notifications, from the kernel's own collaboration and workflow (no extra mail): **Comments and mentions** (items with a comment of someone else you have not read, "Mentions you" where the last one names you as @login), **Your pages** (your pages an approver approved or denied since you last looked, within 30 days) and **Scheduled tasks that failed** (your workflow processes that stopped with an error within 30 days, with the full editor of the page). Comments and results are shown to every user who takes part in the item (an author without the `collaboration` policy, as in the shipped Editor role, too). **Mark as read** does what opening the item in the administration does; **Open in the administration** (for users with the `collaboration` policy) opens it there. Their number together (each item once) is a badge on the closed tab and on the panel's tab ("3 new in your inbox"), counted with a few queries and kept for a minute; opening the panel, marking as read or deciding counts again at once |
| Migrations | `expwt/use` and the kernel's `migration/read` (Exponential 6.0.15 and later); without that policy, or on a kernel without migrations, there is no Migrations tab | read-only: the state of the installation's migrations as Setup > Migrations in the administration shows it, on every page. A run that is active now (the installation lock: what it does, who started it and since when), the **failed** migrations (each with its source and description), the **pending** ones (id, source, description; the first 50, the rest counted), the migrations **recorded, but whose file is gone**, and the **last run**: its state, when it started and finished, who ran it, its caption, tag and push state (a failed run with its message). Each item has **Open in the administration** (the status, or the log of its run), where running, resuming and rolling back stay: the sidebar has no button that changes anything here. The number of pending, failed and recorded-without-file migrations is a badge on the panel's tab, counted once a minute at most |
| Me | `expwt/use` | your account at a glance and the kernel's own pages for it (account, password, passkeys, sessions and devices, security, notifications, drafts, bookmarks), the sidebar's preferences, sign out |

### Editing

Every change is made on a draft: the first save makes one, later saves write into the same draft, and nothing is
public before Publish. Text, long text, rich text (bold, italic, lists, links), numbers, check boxes, dates and times,
e-mail addresses and selections are edited in the panel; links, keywords, relations and tags in their text form.
Images, files, SEO metadata and every other datatype open in the full editor of the administration, with the
installation's configured rich text editor.

**Edit on the page** makes the page's own text editable where it shows a field's value: click the title on the page and
type; the panel's form follows, and the change is saved with the draft. Hovering a field in the panel outlines it on
the page.

**Publish** runs the kernel's publishing, workflows included: with an approval workflow the answer is "Sent for
approval".

**Publish at and take down at** use the kernel's own scheduling, the same as the administration's: the sidebar has no
schedule and no cronjob of its own.

- *Publish at* writes the date into the draft's date field that a "Wait until date" event lists (a workflow on
  `content/publish` before) and publishes the draft: the event holds it until then, the workflow cronjob
  (`workflow.php`) publishes it, and the answer is "Scheduled for ...". Every other event of that workflow applies, so
  with an approval the draft also waits for it.
- *Take down at* writes the date into the field that `content.ini [HideSettings] HideDateAttributeList[<class>]`
  names, for a page below a node of `RootNodeList[]`; the hide cronjob (`hide.php`) hides the page then. Emptying the
  input clears the date.
- These date fields are edited here only, not again in the list of fields. A date that is still to come is shown.
- A class without such a field shows "Not possible here: this kind of page has no date field for it." in place of the
  input, and the server refuses a date for it. To give a class the field, see "Scheduling" in the kernel's guide
  `doc/eng-us/features/6.0/website-sidebar.md`: a date field (Date and time, or Date for a day), the wait-until-date
  event listing it on the publish trigger, and the `[HideSettings]` lines.

**The calendar** shows and moves only what the kernel and explayouts schedule; it keeps nothing of its own.

- *Publish*: a draft that waits in the "Wait until date" event of the `content/publish` workflow (a pending version).
  Moving it writes the new date into the draft's date field and tells its waiting workflow process when to wake up, so
  the workflow cronjob publishes it then; every other event of the workflow (an approval) still applies.
- *Take down*: a published page whose `[HideSettings]` date field lies in the weeks shown. Moving it publishes a new
  version of the page with the new date, as the administration does; with an approval workflow the answer is that the
  new date waits for its approval.
- *Layout variant*: an explayouts rule with a time condition that applies to the page (a node or subtree target, or an
  address target that matches the page; for "this page and below" also the pages below). Its start moves the whole
  range (the end keeps its distance), its end moves alone. The resolver's cache and the template blocks expire as after
  any rule change. A rule that is switched off is marked.
- Each move is audited as `content.expwt.schedule.change` (the sidebar's own branch of the audit taxonomy, on by
  default), with the date before and after.
- What the installation has not set up is said under "Not set up on this site": no "Wait until date" event on the
  publish trigger, or no `[HideSettings]` class and top node.

**Focal point and crop** of an image field (in the Page panel, under the field's editor, closed until opened). It needs
an Exponential kernel that stores them (6.0.15: the image datatype's focal point and crop rectangle); with an older
kernel the part is not shown and the sidebar refuses its actions.

- Click or drag on the image to set the focal point, the part of the picture that must stay in view; the arrow keys
  move it (Shift: in larger steps). **Crop** adds a box: drag it, or drag its corners; on the focused box the arrow
  keys move it and Shift with an arrow key changes its size. **Reset** removes both.
- **How each size will look** previews every image alias the page's templates use (each alias the image already has,
  in the published page or the draft, and every alias with a `geometry/crop` filter), live while you move the point
  or the box, computed as the kernel computes the alias: the crop box is cut out first, and every crop filter of the
  alias is centered on the focal point. An alias made by a filter the preview cannot follow shows its file as it is.
- **Save draft** writes the focal point and the crop into your draft (the first save makes the draft, as the field's
  editor does). The kernel stores the original again for the draft and makes its sizes again around the point; the
  previews then show those files. The published page changes only when the draft is published, through the usual
  publish (and its approval, where there is one).
- Needs `expwt/edit` and the kernel's `content/edit` of the page in its language; "Verify it's you" asks as the
  administration's edit asks. Each change is audited as `content.expwt.image.focal`, with the values before and after.
- Actions `images.load` and `images.save`. An SVG image has no focal point (its sizes are the original file).

**Device preview** (the Page panel's **Devices**, next to Preview): the page in a frame per device, side by side over
the whole window, to see how it lays out before it is published.

- The frames of `expwt.ini [DevicePreviewSettings] DeviceList[]`: iPhone 13 (390 x 844), Pixel 7 (412 x 915), a
  tablet (820 x 1180), **1920 wide at 200% zoom** (a 1920 pixel window zoomed to 200%, which lays the page out at 960
  pixels: the case of a reader who enlarges the page) and a desktop (1440 x 900). **A width of your own** adds a frame
  of any width from 240 to 2560 pixels (up to four), **Remove the frame** takes it away.
- **Frames shown:** a row of toggles, one per device and one per width of your own; a pressed toggle (with a check
  mark) shows its frame. A toggle hides or shows its frame at once and the frames left grow to fill the room (a frame
  is loaded the first time it is shown). **Show all** brings every frame back. Every frame is shown when the preview
  first opens, or those of `ShownDeviceList[]` where the installation names some; after that, this browser remembers
  your choice (in its local storage, nothing on the server). The row wraps on a narrow window and scrolls inside the
  bar on a low one; the toggles are buttons, reached with Tab and pressed with Space or Enter. A page loading in a
  frame (its cookie notice, say) does not take the keyboard focus from the dialog.
- Each frame is the device's window, scaled down to fit: one row where every frame stays readable, else each frame on
  its own, as large as the window allows, the frames wrapping below each other. A frame scrolls on its own, and its
  links work in it.
- **Version:** your own newest draft of the page in its language (the kernel's draft preview, `content/versionview`,
  which checks again that you may read it), or the published page. Without a draft of yours the frames show the
  published page and say so.
- **Light or dark per frame:** Like the site, Light or Dark; the choice is set in that frame only (the media designs'
  color mode), nothing of the site's own choice changes. Like the site loads the frame again as the site has it.
- The pages in the frames show no sidebar and no debug bar of their own (`?in=sidebar`). The choices are remembered on
  this device. Read-only: nothing is saved on the server.
- Needs `expwt/edit` (the button is there for those who may edit the page). Action `preview.devices` (GET): it answers
  the frames and the two addresses, and 403 without `expwt/edit`.

**Upgrading from an earlier expwt:** it kept its own schedule (the site data rows `expwt_publish_*` and
`expwt_unpublish_*`, the cronjob part `expwt_scheduled`). Run `./console exp:wt:migrate-from-ezwt` to see what moves
and `--apply` to move it (the upgrade step `6.0.15-expwt-enable` does the same); an entry whose class lacks the date
field stays, is listed, and moves on a later run once the class has it.

**Two editors on one page:** the first who changes something holds a soft lock; the others see "Anna is editing this
page since 10:42" and may take it over. If both save anyway, nothing is lost: fields only one of them changed are
merged, and for a field both changed differently the second editor chooses per field which text to keep.

**Nothing typed is lost (autosave):** what is typed in the Page panel is saved to the user's draft every few seconds,
the same draft Save draft writes, and only when something changed: opening a page or a field's editor makes no draft.
The panel's own inputs are saved once typing pauses; a field's editor (rich text, tags, an image ...) once the focus
has left it, because saving it reloads its frame. "Saved automatically at 10:42" under the page's name says when.
Until the server has it, the browser also keeps a copy on this device (its local storage, for two weeks, per page and
language). If the window closed, the connection dropped or the browser crashed before that, the closed tab shows a
**!** on that page next time ("Unsaved changes on this page"), and the Page panel says which fields and from when, with
**Restore** (puts the text back and saves it to the draft) and **Discard**. A field someone else changed on the server
in the meantime goes through the field by field merge (above). Publishing or discarding the draft removes the copy.

## Fields and their editors

The Page panel edits every field with the editor the administration has for its datatype; it never shows a field in
the text form of its storage.

| Datatype | Editor in the sidebar |
|---|---|
| ezstring, eztext, ezinteger, ezfloat, ezprice, ezemail | an input of the form (text also on the page itself) |
| ezboolean, ezselection, ezdate, ezdatetime, eztime | a checkbox, a choice, a date or time input |
| ezurl | two inputs: the address and the text of the link |
| ezkeyword | an input with the keywords, separated by commas |
| ezxmltext | the installation's rich text editor (ezoe with TinyMCE 8, or exp_oe_tiptap), with its toolbar, custom tags, embeds and link and object browsing |
| eztags | the eztags picker (autocomplete, tree, chips; its subtree and number limits) |
| ezobjectrelation, ezobjectrelationlist | the datatype's own edit (browsing for objects) |
| ezimage, ezbinaryfile, ezmedia | the upload and the fields of the datatype (alternative text ...) |
| xrowmetadata, ezgmaplocation, ezmatrix, any other | the datatype's own edit template |

Every field of the second half is open in its editor when the Page panel opens, ready to type (F32): no Edit button,
each editor loaded as it comes into view. The editor is the view `expwt/field/<object>/<version>/<language>/<field>`
in a frame as high as its content: the kernel's `{attribute_edit_gui}` for that field, saved with the same steps as
content/edit (validation, the datatype's input, its custom actions, the object's name), so what is stored is what the
administration stores for the same input. It shows the user's newest draft in the language, or the published page
while there is none; the panel's one **Save draft** and **Publish** save each editor in turn (the first save makes the
draft, an editor nobody changed sends nothing), then the form's fields. Nothing in the panel scrolls sideways: the
content of an editor wraps to its width (images scale down, tables and code scroll in their own box, long words break).

The fields of a category other than the default one (content.ini `[ClassAttributeSettings] CategoryList`, the class
attribute's category) are in a group of their own at the end of the panel, closed until it is opened, as content/edit
has them: **Meta** holds the page's metadata (xrowmetadata: title, description, keywords, the Open Graph image, the
license with its picker, the sitemap options) and whatever else the class puts into that category. A field clicked on
the page while editing on the page opens its group. On top and bottom the groups are behind **More**. A field whose
class attribute has no name shows the name of its datatype. Text fields have no length limit of the sidebar's own:
what the class allows is saved.

The editor's frame looks like the sidebar (its admin4 tokens and buttons, in the sidebar's light or dark mode); the
sidebar's own row has Save draft and Publish for it, and the rich text editor's options that are no main action
(switching the engine, switching the editor off) are behind **More**.

**Edit on the page** (pressed: the accent fill and a check mark, like every selected control of the sidebar) outlines
every field the page shows: text fields are edited in place; rich text, images and the other fields open their editor
with a click or Enter, the field's row scrolled into view. A field is found by the kernel's field markers (site.ini
`[ContentFieldMarkerSettings]`, expwt's handler: only for a user who may edit with the sidebar), an image also by its
file's path, a text by its value where there are no markers. An image file dropped on the page's image replaces it in
the draft, through the image field's own upload; its alternative text is in its editor.

### Exponential Intelligence on the fields

Where expai (Exponential Intelligence) is on for the user, every field it can write has a row of actions under it in
the Page panel, marked **AI**: the field actions of the administration's edit form (expai.ini `[EditViewSettings]
FieldActions[]`: **Write** with an instruction, **Rewrite** with an optional one, **Shorten**, **Translate** into a
chosen language, **Suggest**, for a keyword field its keywords), **Summarize** (a short summary of the page for a text
or rich text field: expai's Write with that instruction) and, on an image, its **Alt text** (expai's alt text, where a
model that can look at images is available). Translate starts on the field's own language: the usual case is a new
translation whose fields still hold the text of the main language.

- **Who:** `expwt/edit` and expai's own policy `expai/write`, on a page the user may edit in that language; expai active
  in the siteaccess the sidebar runs on (`[ExtensionSettings] ActiveAccessExtensions[]=expai` of the site siteaccess,
  or `ActiveExtensions[]`), switched on for it (`[AISettings] Enabled`, not in `DisabledSiteAccesses[]`), configured (a
  model and its key) and its edit form actions on (`[EditViewSettings] Enabled`). Where any of that is missing the
  sidebar shows no AI action and makes no request for it. expai's limits, data policy, model choice and fallback, and
  its usage log apply exactly as in the administration.
- **Into the draft only:** the answer is a suggestion shown under the field (rich text as its text); the field keeps
  its value until **Use it**. A field of the form takes it into its input, marked as changed and saved with the draft
  (autosave included); a field with its own editor (rich text, the image's alternative text) gets it written into
  the user's draft and the editor loads again. Nothing is published: Publish is the user's own step, with the
  workflows and approvals of the installation. Asking needs a draft (expai marks it as assisted): the user's newest in
  the language, or a new one.
- Actions: `ai.field` (ask) and `ai.apply` (write the suggestion into a field of its own editor), both POST with the
  form token, both `expwt/edit`.

## Policies

| Policy | Allows |
|---|---|
| `expwt/use` | the sidebar itself, the Me panel, reading the Page panel |
| `expwt/edit` | editing on a draft, preview, the device preview, publishing, soft locks, the SEO check |
| `expwt/create` | the Create panel |
| `expwt/structure` | the Structure panel |
| `expwt/layouts`, `expwt/templates`, `expwt/shop`, `expwt/admin` | the panels of later phases (defined now) |
| `expwt/insights` | the Insights panel: the page's views and the site's searches for words of its title, from the kernel's own statistics |

The expwt policies decide what the sidebar offers; every action also checks the kernel's policy of what it changes
(`content/edit` of the object, `content/create` below the parent, `content/hide`, `content/remove` ...) through the
kernel's own operations. "Verify it's you" asks for an action exactly when the administration asks for the view it
stands for (publishing and removing are in the trigger group `content`); the sidebar opens the question in a small
window and goes on once it is answered. The real send of a newsletter (E3) always asks, in its own group `newsletter`
of `[ReauthSettings] Groups[]` (the expwt settings add it, switched on). The small window of the question shows no
sidebar.

## Data layer

`expwt.ini [ExpwtSettings] DataLayer` picks how the sidebar talks to the server:

| DataLayer | The browser | A client with a token |
|---|---|---|
| `rest` (default) | `expwt/api` (GET for reading, POST with the CSRF token for changes) | `/api/expwt/v1/<action>` (OAuth or a personal API key with the scope `expwt`) |
| `graphql` | `graphql/query` with the persisted queries `expwt-read` and `expwt-write` | `/api/graphql`, the fields `expwtRead` and `expwtWrite` |

Both answer the same actions with the same checks (`expwtApi`). `graphql` falls back to `rest` where the GraphQL API
or its mutations are switched off, or the user lacks `graphql/query`.

## Performance and caching

The kernel's pagelayout hook (`design:pagelayout/hooks/body_end.tpl`) prints the sidebar's element and one deferred
script; a pagelayout without the hook gets them from a response filter (`InjectWithoutHook`). What the markup depends
on (signed in or not, `expwt/use`) is what the cache blocks and the full-page cache key on, so pages stay cacheable.
Visitors who are not signed in get nothing at all while `ShowToAnonymous=disabled`. A signed-in user's page makes one
request after it has loaded (the user's state, rights, strings and CSRF token); the panels' script loads when the
sidebar is first opened. The closed tab (script and stylesheet) stays below 50 KB gzip; a test keeps it there.

## Settings (expwt.ini)

| Setting | Default | |
|---|---|---|
| `[ExpwtSettings] Enabled` | disabled | the sidebar on this siteaccess |
| `ShowToAnonymous` | disabled | visitors not signed in: disabled (nothing at all), enabled (the closed tab for every visitor), policy (only when the Anonymous role grants expwt/use; the setup never grants it); any other value: disabled |
| `ShowInAdmin` | disabled | the sidebar in the administration's siteaccesses (those of `[AdminSiteAccessMenu] AdminSiteAccessList[]`, else an administration design); disabled: nothing at all there |
| `SiteAccessList[]`, `SiteAccessExcludeList[]` | (empty) | only these siteaccesses (empty: all), never these |
| `DataLayer` | rest | rest or graphql |
| `InjectWithoutHook` | enabled | place the sidebar in pagelayouts without the hook |
| `DefaultEdge`, `DefaultAlign` | (empty), end | a first visit's edge (left, right, top, bottom; empty: `DefaultSide`) and, on top and bottom, the end of the bar the tab is anchored at (start: left, end: right) |
| `DefaultSide` | right | the edge when `DefaultEdge` is empty (left or right; the setting from before the top and bottom edges) |
| `DefaultWidth`, `MinWidth`, `MaxWidth` | 380, 300, 720 | a first visit's width of the panel on the left and right, the limits |
| `DefaultHeight`, `MinHeight`, `MaxHeight` | 280, 160, 640 | a first visit's height of the bar on top and bottom, the limits |
| `OverlayBelowWidth` | 768 | narrower windows get the panel over the page |
| `ShortcutKey` | E | the letter of Alt+Shift+<key> that opens the sidebar for a user who has not chosen another (Me panel) |
| `HeaderSelector` | header, [role=banner], #header, .header | the site header the tab and the top bar sit below |
| `FooterSelector` | footer, [role=contentinfo], cookie bars | what the site may fix to the bottom of the window; the bottom bar sits above whichever of them is fixed or sticky there |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | classes Create never offers |
| `[LockSettings] LockSeconds` | 300 | how long a soft lock lasts without a heartbeat |
| `[DevicePreviewSettings] DeviceList[]` | iPhone 13, Pixel 7, tablet, 1920 at 200% zoom, desktop | the frames of the device preview, each `id;name;width;height` (CSS pixels, 240 to 2560 wide, 240 to 2400 high); the name is translated where the sidebar's catalogue has it |
| `ShownDeviceList[]` | (empty) | the frames shown when the preview first opens in a browser (ids of `DeviceList[]`); empty: all of them. After that the browser remembers the toggles |
| `[Comments] HideNew` | disabled | enabled: a comment (expservices.ini `[Community] CommentClass`, else `comment`) published for the first time is hidden right after publishing when its creator may not approve it (no `content/hide` of the comment); the Page panel lists it as waiting for moderation and **Approve** reveals it. Done by expwt's content edit handler (content.ini `[EditSettings] ExtensionDirectories[]=expwt`), which the kernel calls in every publish operation (the web, commands, cronjobs, workflows), through the kernel's hide; no workflow to set up |

## Adding to the sidebar from another extension

Another extension adds panels, server actions, page highlights and search providers to the sidebar in its own
`settings/expwt.ini.append.php` (decision 32), with no change to expwt. A worked example, an extension that adds a
"Page cache" panel, is in `tests/example/expwtexample` (its registration is checked by the tests); copy it, rename
`expwtexample` everywhere, activate it and regenerate the autoloads.

```ini
[RegistrySettings]
PanelList[]=pagecache
ActionList[]=expwtexample.info
ActionList[]=expwtexample.clearcache
HighlightList[]=pagecache_headings
SearchProviderList[]=expwtexample

[Panel_pagecache]
Label=Page cache
Context=extension/expwtexample
Script=expwtexample-expwt.js
Stylesheet=expwtexample-expwt.css
Before=me
Function=use
Policy[]=setup/managecache

[Action_expwtexample.clearcache]
Class=expwtexampleExpwtActions
Writes=true
Policy[]=setup/managecache
KernelView=setup/cache

[Highlight_pagecache_headings]
Panel=pagecache
Selector=main h1, article h1
Label=A heading of this page

[SearchProvider_expwtexample]
Label=Pages by name
Action=expwtexample.search
```

| Setting | |
|---|---|
| `Label`, `Context` | the English source of the tab's or entry's name, and the translation context it is translated in |
| `Script`, `Stylesheet` | file names in the extension's design (`javascript/`, `stylesheets/`; the design needs `DesignExtensions[]`) |
| `Before` | the panel of the sidebar whose tab this panel's tab stands before (default `me`) |
| `Function` | the expwt function needed (`use`, `edit`, `create`, `structure`, `layouts`, `templates`, `shop`, `admin`; default `use`) |
| `Policy[]` | kernel policies needed, all of them (`module/function`) |
| `Class`, `Writes` | the action's class (`expwtActionHandler`); `true` for an action that changes something |
| `KernelView` | the administration view (`module/view`) an action stands for: "Verify it's you" asks where it asks |
| `Panel`, `Selector` | the panel a highlight belongs to, and the page's elements it outlines (a CSS selector) |
| `Action` | the action a search provider answers through |

- **Who sees what.** An entry needs its expwt function and every kernel policy of its `Policy[]`; a user without them
  gets neither the tab nor the action (403), and a highlight or search provider goes with its panel or action. The
  sidebar grants nothing: the action's class checks the kernel's policy of what it reads or changes, as the sidebar's
  own actions do. An entry with a wrong name, without what it needs, or taking a name of the sidebar is left out.
- **Panels.** The sidebar loads the panel's script (through the ezjscore packer) the first time the panel is shown;
  the script registers it with `expwtRegister('panel', '<id>', function (ctx, root) { ... })`. `ctx` is what the
  sidebar's own panels get (`h`, `t`, `call`, `say`, `node`, `registry()` ...) and `root` is the panel's body inside the
  sidebar's Shadow DOM, where the sidebar's classes (`wt-btn`, `wt-field`, `wt-section`, `wt-muted` ...) and tokens
  (`--a4-*`, with their dark values) apply; a stylesheet uses only those tokens.
- **Actions** are named `<extension>.<name>` and answered by `run($action, $input, $gateway)` of their class
  (`expwtActionHandler`) through the installation's data layer (`expwt/api`, `/api/expwt/v1`, GraphQL): checked in the
  order of the sidebar's own (registered 404, POST for a writing one 405, policies 403), and an `expwtApiError` of the
  class reaches the panel with its status and message. `ctx.call('<extension>.<name>', input)` sends a writing action
  by POST with the CSRF token and a reading one by GET.
- **Highlights** are outlined while their panel is shown and the sidebar is open, and can be reached with the
  keyboard; a click or Enter on one sends the event `expwt-pick` to the panel's body (`detail.highlight`,
  `detail.element`).
- **Search providers** are listed in the boot answer for the sidebar's search (decision 29).

## For developers

- Tests (no database): `php vendor/bin/phpunit extension/expwt/tests/phpunit/unit` from the installation's root.
- Strings: every `t('...')` of the JavaScript and every refusal of the classes is in `expwtStrings::SOURCES`
  (context `extension/expwt`) and in every catalogue of `translations/`.
- The admin4 tokens of `expwt.css` are a generated copy of `design/admin4/stylesheets/components.css`; a test compares
  them.

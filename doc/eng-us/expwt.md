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
| Page | `expwt/use` (reading), `expwt/edit` (changing) | the page's fields in a form, editing on the page, save the draft, preview, the full editor, publish now or at a date, take down at a date, discard the draft |
| Create | `expwt/create` | a new page below or beside this one: the classes you may create there, a title, then fill it in the Page panel and publish |
| Structure | `expwt/structure` | hide and reveal, move and add a location (choose the place in a tree on the site), move to the trash, sort the pages below by dragging or with the up and down buttons |
| SEO | `expwt/edit` | the SEO check of the page as it is rendered: its title and description (length), its headings (one main heading, no level skipped), the alternative texts of its images, its canonical address and robots, and whether each internal link leads somewhere (the server looks it up as the kernel would answer it: a view, a URL alias or redirect, a wildcard, a file of the storage or a design; links of another siteaccess are not checked). Problems first; a finding opens the field it is about in the Page panel (the title or the metadata field, the field an image or a link of the text belongs to) or shows its place on the page. Nothing is saved |
| Sites | `expwt/use` | the page on the other sites of the installation and in its other languages. The sites are those of the kernel's site menu (the admin4 globe: `[SiteAccessSettings] RelatedSiteAccessList[]`, no administration, only where the user may log in); **Open** goes to the page there through the kernel's language switcher (`switchlanguage/to`, which finds the page's address in that site's languages) with the sidebar as it was (open, on the same panel); **Side by side** shows this page and the other one next to each other (one below the other on a phone), each scrolling on its own. The translations: each language of the page with **Edit here** (the Page panel edits that translation; a button there goes back to the site's own language) and **Side by side** (the kernel's preview of it), and the languages the user may translate the page into with **Translate** (the full editor) |
| Insights | `expwt/insights` | read-only, from the kernel's own statistics, no third-party tracking: the page's views (the view counter the cronjob `updateviewcount` fills from the access log; where it is off the panel says so), the searches on this site for words of the page's title (the kernel's search log, with how often and how many results), and that the kernel records no referrers |
| Inbox | `expwt/use` and the kernel's `collaboration` policy | the approvals of the publishing workflow that wait for you (the kernel's approve event): each page with its author and last comment, **Open the draft**, and for its approvers a comment with **Approve** or **Deny**, decided through the kernel's own `collaboration/action` view, exactly as in the administration (the workflow publishes an approved page on its next run; a denied one is a draft of its author again). Their number is a badge on the closed tab and on the panel's tab |
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

**Upgrading from an earlier expwt:** it kept its own schedule (the site data rows `expwt_publish_*` and
`expwt_unpublish_*`, the cronjob part `expwt_scheduled`). Run `./console exp:wt:migrate-from-ezwt` to see what moves
and `--apply` to move it (the upgrade step `6.0.15-expwt-enable` does the same); an entry whose class lacks the date
field stays, is listed, and moves on a later run once the class has it.

**Two editors on one page:** the first who changes something holds a soft lock; the others see "Anna is editing this
page since 10:42" and may take it over. If both save anyway, nothing is lost: fields only one of them changed are
merged, and for a field both changed differently the second editor chooses per field which text to keep.

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

## Policies

| Policy | Allows |
|---|---|
| `expwt/use` | the sidebar itself, the Me panel, reading the Page panel |
| `expwt/edit` | editing on a draft, preview, publishing, soft locks, the SEO check |
| `expwt/create` | the Create panel |
| `expwt/structure` | the Structure panel |
| `expwt/layouts`, `expwt/templates`, `expwt/shop`, `expwt/admin` | the panels of later phases (defined now) |
| `expwt/insights` | the Insights panel: the page's views and the site's searches for words of its title, from the kernel's own statistics |

The expwt policies decide what the sidebar offers; every action also checks the kernel's policy of what it changes
(`content/edit` of the object, `content/create` below the parent, `content/hide`, `content/remove` ...) through the
kernel's own operations. "Verify it's you" asks for an action exactly when the administration asks for the view it
stands for (publishing and removing are in the trigger group `content`); the sidebar opens the question in a small
window and goes on once it is answered.

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
| `ShortcutKey` | E | Alt+Shift+<key> |
| `HeaderSelector` | header, [role=banner], #header, .header | the site header the tab and the top bar sit below |
| `FooterSelector` | footer, [role=contentinfo], cookie bars | what the site may fix to the bottom of the window; the bottom bar sits above whichever of them is fixed or sticky there |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | classes Create never offers |
| `[LockSettings] LockSeconds` | 300 | how long a soft lock lasts without a heartbeat |

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

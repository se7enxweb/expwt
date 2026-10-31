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
approval". Publish later and take down at a date are carried out by the cronjob part `expwt_scheduled` (frequent),
as the user who scheduled them.

**Two editors on one page:** the first who changes something holds a soft lock; the others see "Anna is editing this
page since 10:42" and may take it over. If both save anyway, nothing is lost: fields only one of them changed are
merged, and for a field both changed differently the second editor chooses per field which text to keep.

## Policies

| Policy | Allows |
|---|---|
| `expwt/use` | the sidebar itself, the Me panel, reading the Page panel |
| `expwt/edit` | editing on a draft, preview, publishing, soft locks |
| `expwt/create` | the Create panel |
| `expwt/structure` | the Structure panel |
| `expwt/layouts`, `expwt/templates`, `expwt/shop`, `expwt/admin` | the panels of later phases (defined now) |

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
| `ShowToAnonymous` | enabled | the closed tab for visitors not signed in |
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

## For developers

- Tests (no database): `php vendor/bin/phpunit extension/expwt/tests/phpunit/unit` from the installation's root.
- Strings: every `t('...')` of the JavaScript and every refusal of the classes is in `expwtStrings::SOURCES`
  (context `extension/expwt`) and in every catalogue of `translations/`.
- The admin4 tokens of `expwt.css` are a generated copy of `design/admin4/stylesheets/components.css`; a test compares
  them.

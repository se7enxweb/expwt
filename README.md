# expwt: the website sidebar of Exponential

Edit, create and arrange content on the site's own pages, in a sidebar docked to the edge of every design. expwt
replaces ezwt, the website toolbar.

- Install: `composer require se7enxweb/expwt`, then `./console exp:wt:migrate-from-ezwt --apply` on an installation that
  used ezwt, and switch it on per siteaccess (`expwt.ini [ExpwtSettings] Enabled=enabled`; the migration does so where
  ezwt was active).
- The package replaces `se7enxweb/ezwt` (Composer `replace`): the two are never installed together, and a
  `require` of ezwt is met by expwt. Composer removes `extension/ezwt` when expwt comes in, so run the migration
  first or right after, before `ActiveExtensions[]=ezwt` is taken out of the settings.
- Guide: [doc/eng-us/expwt.md](doc/eng-us/expwt.md) (Deutsch: [doc/ger-de/expwt.md](doc/ger-de/expwt.md)).
- Requires Exponential 6.0.15 or later (the pagelayout hook, GraphQL mutation extensions, the move from ezwt) and
  ezjscore; PHP 7.4 or later.

Version 0.1.0 (first phase): the sidebar on every design (tab, push or overlay, drag, keyboard, width, per user and
per device), the Me panel, editing on a draft in the panel and on the page, preview, publish now or later, create,
tree operations, soft locks with a field by field merge, the expwt policies, REST and GraphQL data layers.

License: GNU General Public License v2.0 (or any later version).

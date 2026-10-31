# expwt: die Website-Seitenleiste von Exponential

expwt setzt einen schmalen Reiter an den Rand der Seiten der Website. Geöffnet ist er ein Bereich, in dem Sie die
Seite bearbeiten, die Sie gerade sehen, Seiten anlegen, sie im Baum verschieben und Ihr eigenes Konto verwalten, ohne
in die Administration zu wechseln. Sie ersetzt ezwt, die Website-Toolbar. Version 0.1.0 ist die erste Phase: die
Seitenleiste selbst, der Bereich „Ich" und der Kern des Bearbeitens. Layouts, Templates, Medien, Shop und die Bereiche
der Administration folgen.

## Einschalten

expwt wird ausgeschaltet ausgeliefert (bis Version 1.0). In `settings/siteaccess/<Siteaccess>/expwt.ini.append.php`
(oder `settings/override/` für alle Siteaccesses):

```ini
[ExpwtSettings]
Enabled=enabled
```

Eine Installation, die ezwt nutzte, wechselt mit `./console exp:wt:migrate-from-ezwt --apply` (oder dem
Upgrade-Schritt `6.0.15-expwt-enable`): die ausgeblendeten Klassen von ezwt, seine Policies als expwt-Policies, expwt an
der Stelle von ezwt in den aktiven Erweiterungen. Ein Probelauf listet die Änderungen vorher auf.

## Die Seitenleiste

- **Wo:** an einem der vier Ränder des Fensters (standardmäßig rechts). **Links oder rechts** ist sie ein Bereich
  direkt unter dem Kopf der Website, und oben im Fenster, sobald der Kopf weggescrollt ist. **Oben** ist sie eine
  waagerechte Leiste unter dem Kopf, die oben im Fenster bleibt, sobald der Kopf weggescrollt ist; **unten** eine
  waagerechte Leiste über dem, was die Website unten im Fenster hält (eine feste Fußzeile, ein Cookie-Hinweis). Oben
  und unten sitzt ihr Reiter am linken oder am rechten Ende der Leiste. Geschlossen ist sie ein Reiter, geöffnet ein
  Bereich (links, rechts) oder eine Leiste (oben, unten).
- **Verschieben:** den Reiter an seinem Rand entlang ziehen oder an einen anderen Rand (Maus, Stift oder Finger); sie
  legt sich an den nächsten Rand. Mit der Tastatur den Reiter fokussieren:
  - die Pfeiltasten entlang des Randes verschieben ihn (mit Umschalt in größeren Schritten), die Pfeiltasten quer dazu
    wechseln an den gegenüberliegenden Rand (links und rechts an der Seite, hoch und runter oben und unten);
  - **Strg** mit einer Pfeiltaste legt ihn an diesen Rand; **Bild ab** und **Bild auf** gehen reihum (rechts, unten,
    links, oben);
  - **Pos1** und **Ende** bringen ihn an den Anfang oder das Ende des Randes (oben und unten: am linken oder rechten
    Ende verankert);
  - Enter öffnet ihn. **Alt+Umschalt+E** öffnet sie von überall und setzt den Fokus hinein; Escape schließt sie.
  Im Bereich „Ich" gibt es dasselbe als Einstellung: **Position** links / rechts / oben / unten, und für oben und unten
  **Ausgerichtet nach** links oder rechts.
- **Die Leiste oben und unten** ist in die Breite angelegt, damit ihre wichtigsten Bedienelemente nie eine Bildlaufleiste
  brauchen: eine Zeile am Rand enthält die Reiter der Bereiche, die Hauptaktionen des offenen Bereichs (Entwurf
  speichern, Veröffentlichen, Entwurf verwerfen; Abmelden bei „Ich"), die Statusmeldung und die Schließen-Schaltfläche.
  Darunter (oben) oder darüber (unten): „Seite" zeigt die Felder in einem Raster aus zwei bis sechs Spalten, lange Texte
  zwei Spalten breit mit den Formatknöpfen neben der Beschriftung; was nicht in die Höhe der Leiste passt, das
  Veröffentlichungsdatum und die Felder des vollen Editors liegen unter **Mehr** (die Leiste passt sich neu an, wenn sich
  ihre Höhe oder das Fenster ändert). „Anlegen" ist eine Zeile (wo, Typ, Titel, Entwurf anlegen); „Struktur" zeigt die
  Aktionen der Seite in einer Zeile und die Seiten darunter als seitlich scrollenden Streifen; „Ich" zeigt Konto, Links
  und jede Einstellung nebeneinander; der Bereich für Besucher ist eine Zeile. Auf einem Telefon ist die Leiste ein
  Blatt, so hoch wie sein Inhalt.
- **Größe:** die Breite des Bereichs links und rechts, die Höhe der Leiste oben und unten (anfangs 280 px): den inneren Rand ziehen,
  oder ihn fokussieren und die Pfeiltasten nutzen (Pos1 und Ende: am kleinsten und am größten).
- **Neben oder über der Seite:** In einem breiten Fenster schiebt der geöffnete Bereich die Seite beiseite (links,
  rechts), unter die Leiste nach unten (oben: der Kopf bleibt, wo er ist, was ihm folgt, rückt nach unten) oder gibt
  ihr an ihrem Ende Platz (unten); auf einem Telefon (unter 768 px) liegt er über der Seite. Im Bereich „Ich" kann
  jeder „über der Seite" auch für breite Fenster wählen.
- **Gemerkt:** Rand, Ende der Leiste, Platz, Breite und Höhe, offen oder geschlossen, neben oder über und hell oder
  dunkel werden für den Benutzer auf dem Server gehalten (sie folgen auf jedes Gerät) oder nur auf diesem Gerät
  („Diese Einstellungen nur auf diesem Gerät behalten"). Ein nicht angemeldeter Besucher behält sie im Browser. Eine
  Position, die gespeichert wurde, bevor es oben und unten gab (links oder rechts), gilt als dieser Rand.
- **Hell oder dunkel:** die Wahl des Benutzers für die Seitenleiste, sonst seine Wahl für die Administration (hell
  oder dunkel: sie gilt auf jedem Design), sonst die der Seite: auf einem Website-Design mit eigenem Hell/Dunkel-
  Schalter (die Media-Designs: site, smarty, twig) der Modus dieses Schalters, dem sie beim Umschalten sofort folgt;
  sonst der des Systems. Nicht angemeldete Besucher und Benutzer mit dem Administrationsmodus "System" bekommen den
  Modus der Website. Die Seitenleiste sieht auf jedem Design aus wie die Administration (admin4): Sie lebt in einem
  Shadow DOM, die Stile der Website erreichen sie nicht und ihre Stile nicht die Website.
  Die Seitenleiste erscheint vom ersten Bild einer Seite an in ihren endgültigen Farben: erst wenn ihr Stylesheet
  greift, und der Modus eines angemeldeten Benutzers steht fest, bevor die Antwort des Servers da ist (eine
  admin4-Seite trägt die Einstellung, sonst merkt sich das Gerät die letzte Antwort); ein Neuladen blitzt nie hell
  und dann dunkel auf.

## Bereiche

| Bereich | Für | Was |
|---|---|---|
| Willkommen | nicht angemeldete Besucher (`ShowToAnonymous`) | anmelden, registrieren, suchen, hell oder dunkel |
| Seite | `expwt/use` (lesen), `expwt/edit` (ändern) | die Felder der Seite in einem Formular, auf der Seite bearbeiten, Entwurf speichern, Vorschau, der volle Editor, jetzt oder zu einem Zeitpunkt veröffentlichen, zu einem Zeitpunkt zurückziehen, Entwurf verwerfen |
| Anlegen | `expwt/create` | eine neue Seite unter oder neben dieser: die Klassen, die Sie dort anlegen dürfen, ein Titel, dann im Bereich Seite ausfüllen und veröffentlichen |
| Struktur | `expwt/structure` | ausblenden und einblenden, verschieben und einen weiteren Ort hinzufügen (der Platz wird in einem Baum auf der Website gewählt), in den Papierkorb, die Seiten darunter per Ziehen oder mit den Pfeilschaltflächen sortieren |
| Ich | `expwt/use` | Ihr Konto auf einen Blick und die Seiten des Kernels dafür (Konto, Passwort, Passkeys, Sitzungen und Geräte, Sicherheit, Benachrichtigungen, Entwürfe, Lesezeichen), die Einstellungen der Seitenleiste, abmelden |

### Bearbeiten

Jede Änderung geschieht in einem Entwurf: Das erste Speichern legt ihn an, spätere Speichervorgänge schreiben in
denselben Entwurf, und nichts ist öffentlich vor „Veröffentlichen". Text, langer Text, formatierter Text (fett, kursiv,
Listen, Links), Zahlen, Kontrollkästchen, Datum und Uhrzeit, E-Mail-Adressen und Auswahlen werden im Bereich bearbeitet;
Links, Schlagwörter, Beziehungen und Tags in ihrer Textform. Bilder, Dateien, SEO-Metadaten und alle anderen Datentypen
öffnen den vollen Editor der Administration, mit dem eingestellten Editor für formatierten Text.

**Auf der Seite bearbeiten** macht den Text der Seite selbst bearbeitbar, wo er den Wert eines Feldes zeigt: den Titel
auf der Seite anklicken und tippen; das Formular des Bereichs folgt, und die Änderung wird mit dem Entwurf gespeichert.
Fährt die Maus über ein Feld im Bereich, wird es auf der Seite umrandet.

**Veröffentlichen** nutzt das Veröffentlichen des Kernels, Workflows eingeschlossen: Mit einem Freigabe-Workflow lautet
die Antwort „Zur Freigabe gesendet". Später veröffentlichen und zu einem Zeitpunkt zurückziehen erledigt der
Cronjob-Teil `expwt_scheduled` (frequent), als der Benutzer, der es geplant hat.

**Zwei Redakteure, eine Seite:** Wer zuerst etwas ändert, hält eine weiche Sperre; die anderen sehen „Anna bearbeitet
diese Seite seit 10:42" und können sie übernehmen. Speichern beide trotzdem, geht nichts verloren: Felder, die nur einer
geändert hat, werden zusammengeführt, und bei einem Feld, das beide verschieden geändert haben, wählt der zweite je Feld,
welcher Text bleibt.

## Policies

| Policy | Erlaubt |
|---|---|
| `expwt/use` | die Seitenleiste selbst, den Bereich Ich, das Lesen im Bereich Seite |
| `expwt/edit` | Bearbeiten im Entwurf, Vorschau, Veröffentlichen, weiche Sperren |
| `expwt/create` | den Bereich Anlegen |
| `expwt/structure` | den Bereich Struktur |
| `expwt/layouts`, `expwt/templates`, `expwt/shop`, `expwt/admin` | die Bereiche späterer Phasen (schon jetzt definiert) |

Die Policies von expwt entscheiden, was die Seitenleiste anbietet; jede Aktion prüft zusätzlich die Policy des Kernels
für das, was sie ändert (`content/edit` des Objekts, `content/create` unter dem Elternknoten, `content/hide`,
`content/remove` ...), über die eigenen Operationen des Kernels. „Bestätigen Sie Ihre Identität" fragt bei einer Aktion
genau dann, wenn die Administration bei der Ansicht fragt, für die sie steht (Veröffentlichen und Entfernen gehören zur
Auslösegruppe `content`); die Seitenleiste öffnet die Frage in einem kleinen Fenster und macht weiter, sobald sie
beantwortet ist.

## Datenschicht

`expwt.ini [ExpwtSettings] DataLayer` wählt, wie die Seitenleiste mit dem Server spricht:

| DataLayer | Der Browser | Ein Client mit Token |
|---|---|---|
| `rest` (Standard) | `expwt/api` (GET zum Lesen, POST mit dem CSRF-Token für Änderungen) | `/api/expwt/v1/<Aktion>` (OAuth oder ein persönlicher API-Schlüssel mit dem Scope `expwt`) |
| `graphql` | `graphql/query` mit den gespeicherten Abfragen `expwt-read` und `expwt-write` | `/api/graphql`, die Felder `expwtRead` und `expwtWrite` |

Beide beantworten dieselben Aktionen mit denselben Prüfungen (`expwtApi`). `graphql` fällt auf `rest` zurück, wo die
GraphQL-API oder ihre Mutationen ausgeschaltet sind oder dem Benutzer `graphql/query` fehlt.

## Leistung und Caching

Der Pagelayout-Hook des Kernels (`design:pagelayout/hooks/body_end.tpl`) gibt das Element der Seitenleiste und ein
verzögertes Skript aus; ein Pagelayout ohne Hook bekommt sie von einem Antwortfilter (`InjectWithoutHook`). Wovon die
Ausgabe abhängt (angemeldet oder nicht, `expwt/use`), ist das, worauf die Cache-Blöcke und der Seiten-Cache schlüsseln;
die Seiten bleiben also cachebar. Nicht angemeldete Besucher bekommen gar nichts, solange `ShowToAnonymous=disabled`
ist. Die Seite eines angemeldeten Benutzers macht eine Anfrage, nachdem sie geladen ist (Zustand, Rechte, Texte und
CSRF-Token des Benutzers); das Skript der Bereiche lädt beim ersten Öffnen. Der geschlossene Reiter (Skript und
Stylesheet) bleibt unter 50 KB gzip; ein Test hält ihn dort.

## Einstellungen (expwt.ini)

| Einstellung | Standard | |
|---|---|---|
| `[ExpwtSettings] Enabled` | disabled | die Seitenleiste in diesem Siteaccess |
| `ShowToAnonymous` | enabled | der geschlossene Reiter für nicht angemeldete Besucher |
| `DataLayer` | rest | rest oder graphql |
| `InjectWithoutHook` | enabled | die Seitenleiste in Pagelayouts ohne Hook einsetzen |
| `DefaultEdge`, `DefaultAlign` | (leer), end | Rand beim ersten Besuch (left, right, top, bottom; leer: `DefaultSide`) und oben und unten das Ende der Leiste, an dem der Reiter sitzt (start: links, end: rechts) |
| `DefaultSide` | right | der Rand, wenn `DefaultEdge` leer ist (left oder right; die Einstellung aus der Zeit vor oben und unten) |
| `DefaultWidth`, `MinWidth`, `MaxWidth` | 380, 300, 720 | Breite des Bereichs links und rechts beim ersten Besuch, die Grenzen |
| `DefaultHeight`, `MinHeight`, `MaxHeight` | 280, 160, 640 | Höhe der Leiste oben und unten beim ersten Besuch, die Grenzen |
| `OverlayBelowWidth` | 768 | schmalere Fenster bekommen den Bereich über der Seite |
| `ShortcutKey` | E | Alt+Umschalt+<Taste> |
| `HeaderSelector` | header, [role=banner], #header, .header | der Kopf der Website, unter dem der Reiter und die obere Leiste sitzen |
| `FooterSelector` | footer, [role=contentinfo], Cookie-Hinweise | was die Website unten im Fenster festhalten kann; die untere Leiste sitzt über dem, was davon dort gerade fest steht |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | Klassen, die Anlegen nie anbietet |
| `[LockSettings] LockSeconds` | 300 | wie lange eine weiche Sperre ohne Lebenszeichen hält |

## Für Entwickler

- Tests (ohne Datenbank): `php vendor/bin/phpunit extension/expwt/tests/phpunit/unit` im Wurzelverzeichnis der
  Installation.
- Texte: Jedes `t('...')` des JavaScripts und jede Ablehnung der Klassen steht in `expwtStrings::SOURCES` (Kontext
  `extension/expwt`) und in jedem Katalog unter `translations/`.
- Die admin4-Tokens in `expwt.css` sind eine erzeugte Kopie von `design/admin4/stylesheets/components.css`; ein Test
  vergleicht sie.

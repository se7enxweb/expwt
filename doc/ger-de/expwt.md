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

- **Wo:** am rechten Rand (oder am linken), direkt unter dem Kopf der Website, und oben im Fenster, sobald der Kopf
  weggescrollt ist. Geschlossen ist sie ein Reiter, geöffnet ein Bereich.
- **Verschieben:** den Reiter am Rand entlang ziehen oder an den anderen Rand (Maus, Stift oder Finger). Mit der
  Tastatur: den Reiter fokussieren, Pfeil hoch und runter verschieben ihn (mit Umschalt in größeren Schritten), links
  und rechts wechseln die Seite, Enter öffnet ihn. **Alt+Umschalt+E** öffnet sie von überall und setzt den Fokus
  hinein; Escape schließt sie.
- **Breite:** den inneren Rand des Bereichs ziehen, oder ihn fokussieren und die Pfeiltasten nutzen (Pos1 und Ende:
  am schmalsten und am breitesten).
- **Neben oder über der Seite:** In einem breiten Fenster schiebt der geöffnete Bereich die Seite beiseite, auf einem
  Telefon (unter 768 px) liegt er über der Seite. Im Bereich „Ich" kann jeder „über der Seite" auch für breite Fenster
  wählen.
- **Gemerkt:** Seite, Platz, Breite, offen oder geschlossen, neben oder über und hell oder dunkel werden für den
  Benutzer auf dem Server gehalten (sie folgen auf jedes Gerät) oder nur auf diesem Gerät („Diese Einstellungen nur auf
  diesem Gerät behalten"). Ein nicht angemeldeter Besucher behält sie im Browser.
- **Hell oder dunkel:** die Wahl des Benutzers für die Seitenleiste, sonst seine Wahl für die Administration, sonst
  die des Systems. Die Seitenleiste sieht auf jedem Design aus wie die Administration (admin4): Sie lebt in einem
  Shadow DOM, die Stile der Website erreichen sie nicht und ihre Stile nicht die Website.

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
| `DefaultSide`, `DefaultWidth`, `MinWidth`, `MaxWidth` | right, 380, 300, 720 | Seite und Breite beim ersten Besuch, die Grenzen |
| `OverlayBelowWidth` | 768 | schmalere Fenster bekommen den Bereich über der Seite |
| `ShortcutKey` | E | Alt+Umschalt+<Taste> |
| `HeaderSelector` | header, [role=banner], #header, .header | der Kopf der Website, unter dem der Reiter sitzt |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | Klassen, die Anlegen nie anbietet |
| `[LockSettings] LockSeconds` | 300 | wie lange eine weiche Sperre ohne Lebenszeichen hält |

## Für Entwickler

- Tests (ohne Datenbank): `php vendor/bin/phpunit extension/expwt/tests/phpunit/unit` im Wurzelverzeichnis der
  Installation.
- Texte: Jedes `t('...')` des JavaScripts und jede Ablehnung der Klassen steht in `expwtStrings::SOURCES` (Kontext
  `extension/expwt`) und in jedem Katalog unter `translations/`.
- Die admin4-Tokens in `expwt.css` sind eine erzeugte Kopie von `design/admin4/stylesheets/components.css`; ein Test
  vergleicht sie.

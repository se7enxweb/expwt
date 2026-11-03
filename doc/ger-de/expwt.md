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

Die Seitenleiste bleibt aus der Administration heraus (`ShowInAdmin=disabled`, der Standard): deren Siteaccesses
bekommen keine Seitenleiste, keine Ausgabe des Hooks und keine Anfrage. `SiteAccessList[]` und
`SiteAccessExcludeList[]` wählen Siteaccesses einzeln.

**Nur in manchen Siteaccesses, auf dem anderen Weg:** expwt pro Siteaccess mit `ActiveAccessExtensions[]=expwt` in
`settings/siteaccess/<Website-Siteaccess>/site.ini.append.php` aktivieren statt mit `ActiveExtensions[]=expwt` für die
ganze Installation. Der Preis: Die Erweiterung gibt es dann nur dort - ihr Modul (`expwt/api`, `expwt/field`), ihre
Policies im Rolleneditor, ihre Übersetzungen und Einstellungen kennt kein anderer Siteaccess, auch nicht die
Administration; Rollen werden dann dort bearbeitet, wo sie aktiv ist, oder die Policies werden von Hand eingetragen.
Die Einstellungen oben behalten das Modul überall und lassen nur die Seitenleiste weg.

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
- **Tastenkürzel:** alle sind Alt+Umschalt mit einem Buchstaben oder einer Ziffer, die eine Kombination, die Browser und
  Systeme frei lassen (Strg oder Cmd mit einem Buchstaben gehört dem Browser, Alt mit einem Buchstaben öffnet seine
  Menüs, Strg+Alt ist AltGr):

  | Tasten | |
  |---|---|
  | Alt+Umschalt+E | die Seitenleiste öffnen oder den Fokus hineinsetzen (`ShortcutKey`) |
  | Alt+Umschalt+D | den Entwurf des Bereichs Seite speichern |
  | Alt+Umschalt+K | auf der Seite bearbeiten, ein oder aus |
  | Alt+Umschalt+H | die Übersicht: jedes Tastenkürzel, das auf dieser Seite für Sie gilt, und die Tasten des Reiters |
  | Alt+Umschalt+1 bis 9 | die Bereiche in ihrer Reihenfolge (1 Seite, 2 Erstellen ...) |

  Die Buchstaben werden im Bereich Ich unter **Tastenkürzel** geändert und mit den anderen Einstellungen der
  Seitenleiste gespeichert (für den Benutzer auf dem Server oder nur auf diesem Gerät). Ein Buchstabe wird mit Grund
  abgelehnt, wenn er nicht ein Buchstabe von A bis Z ist, wenn ihn ein anderes Kürzel hat oder wenn Browser oder System
  ihn mit Alt+Umschalt belegen (A, B, I, L, M, N, S, T: Feedback-Formular, Symbolleiste, Dialoge und Lesezeichenleiste
  von Chrome und Edge, Launcher, Dateien, Benachrichtigungen und Statusbereich von ChromeOS); **Zurück zu den
  Standardwerten** stellt sie wieder her. Screenreader belegen Alt+Umschalt mit einem Buchstaben nicht (NVDA, JAWS,
  Sprachausgabe und Orca nutzen Einfg oder Feststelltaste, VoiceOver Control+Option) und reichen es im Lesemodus
  weiter. Eine Taste, die die Seite einem ihrer eigenen Links gibt (`accesskey`, die Firefox mit Alt+Umschalt
  erreicht), bleibt der Seite: Die Seitenleiste lässt sie dort in Ruhe, und die Übersicht sagt es. Auf einem Mac tippt
  Option+Umschalt mit einem Buchstaben ein Zeichen: In einem Textfeld mit dem Fokus bekommt es das Feld.
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
- **Das zuletzt gewählte Panel** (Ich, Performance, Einblicke, das Panel einer Erweiterung ...) ist nach dem Neuladen
  wieder offen, auch wenn direkt nach dem Klick neu geladen wird: der Browser behält es sofort und schickt es dem
  Server, wenn die Seite verlassen wird, und eine Änderung, die der Server noch nicht bestätigt hat, gilt auf der
  nächsten Seite vor der Kopie des Servers. Ausnahmen: mit ungespeicherten Änderungen der Seite und beim Bearbeiten auf
  der Seite öffnet sich das Panel Seite; ein Panel, das der Benutzer nicht mehr hat, öffnet das erste.
- **Beschriftung der Reiter:** wie die Reiter der Bereiche und der geschlossene Reiter beschriftet sind: **Symbole**
  (nur das Symbol; der Name des Bereichs erscheint beim Zeigen mit der Maus und beim Fokus mit der Tastatur in einer
  Blase unter dem Reiter, Escape blendet sie aus, und Screenreader lesen ihn weiter vor), **Symbole und Text** oder
  **Text** (nur der Name). Jeder Benutzer wählt im Bereich Ich, ein Besucher im Bereich Willkommen; gespeichert wie die
  übrigen Einstellungen der Seitenleiste. Ohne eigene Wahl gilt `TabLabels` der Installation (ausgeliefert: icons),
  auch wenn es später geändert wird. Die Icons sind die Bootstrap Icons des Kernels (`share/icons/bootstrap-icons`,
  derselbe Satz wie im Design admin4), in der Textfarbe der Seitenleiste, hell wie dunkel, und in der Textfarbe, die
  die erzwungenen Farben des Systems (hoher Kontrast) wählen; der gewählte Reiter ist dann Highlight. Ein Bereich
  einer Erweiterung ohne eigenes Symbol bekommt das Puzzle-Symbol; auf einem Kernel ohne den Satz zeigt jeder Reiter
  seinen Namen. Plaketten bleiben in jeder Wahl an ihrem Reiter. Die Vorgabe der Installation stellt ein
  Administrator je Siteaccess unter Setup > Bereiche der Website-Seitenleiste ein (Beschriftung der Reiter). Bis zu dieser Version zeigten die Reiter nur ihre Namen; `TabLabels=text`
  lässt es dabei.
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
| Willkommen | nicht angemeldete Besucher (`ShowToAnonymous`) | anmelden, registrieren, suchen, hell oder dunkel, Lesen und Darstellung für die ganze Website (siehe „Barrierefreiheit für Besucher“ unten) |
| Seite | `expwt/use` (lesen), `expwt/edit` (ändern) | die Felder der Seite in einem Formular, auf der Seite bearbeiten, Entwurf speichern, Vorschau, die Seite auf mehreren Geräten nebeneinander (siehe „Gerätevorschau“ unten), der volle Editor, jetzt oder zu einem Zeitpunkt veröffentlichen, zu einem Zeitpunkt zurückziehen, Entwurf verwerfen; unter jedem Bildfeld sein Fokuspunkt und Zuschnitt mit einer Vorschau jeder Größe (siehe „Fokuspunkt und Zuschnitt“ unten) |
| Seite: Kommentare | `expwt/edit`; jede Aktion die Richtlinie des Kernels für den Kommentar (`content/hide`, `content/remove`) oder für die Seite (`content/create` eines Kommentars) | die Kommentare der Seite, an Ort und Stelle moderiert, unter den Feldern des Bereichs Seite. Die Kommentare sind Inhalte unter der Seite (die Klasse aus expservices.ini `[Community] CommentClass`, vorgegeben `comment`), daher sind die Inhaltsrichtlinien des Kernels die Richtlinien der Kommentare. Neueste zuerst, höchstens 50; verborgene Kommentare sind die, die auf Moderation warten, und erscheinen, markiert, nur für die, die sie einblenden dürfen (`content/hide` des Kommentars). **Freigeben** zeigt einen verborgenen Kommentar, **Verbergen** verbirgt einen sichtbaren (beides `content/hide`, über die Verbergen-Operation des Kernels wie Verbergen und Einblenden der Administration); **Spam ...** fragt im Bereich noch einmal und verschiebt den Kommentar in den Papierkorb (`content/remove`; in der Administration aus dem Papierkorb wiederherstellbar). **Antworten** schreibt einen Kommentar des Benutzers unter die Seite (Betreff „Re: " und der Betreff des Kommentars, der Name des Benutzers als Autor), veröffentlicht über die Veröffentlichung des Kernels, ein Freigabe-Workflow greift also; **Kommentar schreiben** tut dasselbe für die Seite selbst. „Bestätigen Sie, dass Sie es sind" fragt genau dort, wo die Administration beim Verbergen, Entfernen und Veröffentlichen fragt. Jede Moderation und Antwort wird protokolliert (`content.comment.approve`, `.hide`, `.spam`, `.reply`). Die Zahl der Kommentare, die auf Moderation warten, steht als Zähler auf dem Reiter des Bereichs Seite (aus dem Start der Seitenleiste, keine zusätzliche Anfrage; keiner bei 0). Mit `[Comments] HideNew=enabled` wartet ein neuer Kommentar auf Moderation: Ein Kommentar, den jemand zum ersten Mal veröffentlicht, der ihn nicht freigeben darf (kein `content/hide` des Kommentars: ein Besucher, ein Mitglied), wird gleich nach dem Veröffentlichen verborgen, innerhalb der Veröffentlichung des Kernels, die Seite zeigt ihn also nie; eigene Kommentare und Antworten der Moderatoren bleiben sichtbar |
| Anlegen | `expwt/create` | eine neue Seite unter oder neben dieser: die Klassen, die Sie dort anlegen dürfen, ein Titel, dann im Bereich Seite ausfüllen und veröffentlichen |
| Struktur | `expwt/structure` | ausblenden und einblenden, verschieben und einen weiteren Ort hinzufügen (der Platz wird in einem Baum auf der Website gewählt), in den Papierkorb, die Seiten darunter per Ziehen oder mit den Pfeilschaltflächen sortieren |
| SEO | `expwt/edit` | die SEO-Prüfung der Seite, wie sie dargestellt wird: Titel und Beschreibung (Länge), Überschriften (eine Hauptüberschrift, keine Ebene übersprungen), die Alternativtexte der Bilder, die kanonische Adresse und robots, und ob jeder interne Link irgendwohin führt (der Server schlägt ihn nach, wie der Kernel ihn beantworten würde: eine Ansicht, ein URL-Alias oder eine Weiterleitung, ein Platzhalter, eine Datei des Speichers oder eines Designs; Links eines anderen Siteaccess werden nicht geprüft). Probleme zuerst; ein Befund öffnet das betroffene Feld im Bereich Seite (das Titel- oder Metadatenfeld, das Feld, zu dem ein Bild oder ein Link des Textes gehört) oder zeigt seine Stelle auf der Seite. Es wird nichts gespeichert. Darunter prüft **Barrierefreiheit der Seite prüfen (WCAG 2.2 AA)** die Seite auf Anforderung mit axe-core (siehe „Barrierefreiheitsprüfung") |
| Websites | `expwt/use` | die Seite auf den anderen Websites der Installation und in ihren anderen Sprachen. Die Websites sind die des Website-Menüs des Kernels (der Globus in admin4: `[SiteAccessSettings] RelatedSiteAccessList[]`, keine Administration, nur wo sich der Benutzer anmelden darf); **Öffnen** führt über den Sprachumschalter des Kernels (`switchlanguage/to`, der die Adresse der Seite in den Sprachen dieser Website findet) zur Seite dort, mit der Seitenleiste wie zuvor (offen, im selben Bereich); **Nebeneinander** zeigt diese und die andere Seite nebeneinander (auf dem Telefon untereinander), jede für sich scrollbar. Die Übersetzungen: jede Sprache der Seite mit **Hier bearbeiten** (der Bereich Seite bearbeitet diese Übersetzung; eine Schaltfläche dort führt zur Sprache der Website zurück) und **Nebeneinander** (die Vorschau des Kernels), und die Sprachen, in die der Benutzer die Seite übersetzen darf, mit **Übersetzen** (der volle Editor) |
| Formulare | `expwt/edit` (die Einsendungen zusätzlich `infocollector/read` des Kernels) | die Formulare der Seite: die Seite selbst, wenn ihre Klasse Informationen sammelt (die Informationssammlung des Kernels: ein Kontaktformular, ein Lead-Formular ...), und die Formulare, auf die sie verweist oder die sie einbettet, jeweils mit der Anzahl ihrer Einsendungen. Mit `infocollector/read` (Gesammelte Informationen der Administration, die Policy, die dort die personenbezogenen Daten schützt) auch die letzten zehn Einsendungen (wann, wer oder Besucher, die ersten zwei Antworten): eine öffnet sich mit allen Antworten, wie `infocollector/view` des Kernels sie zeigt (und genauso protokolliert: welche Einsendung geöffnet wurde, nie ihre Werte); **CSV exportieren** ist der Export `infocollector/export` des Kernels für das Formular, **In der Administration** seine Liste dort. Ein Formular, das sensible Daten sammelt (collect.ini `CollectSensitiveData`), sagt das. Ohne die Policy: nur die Anzahlen, und die Seitenleiste verweigert eine Einsendung. Hier wird nichts geändert oder entfernt |
| Newsletter | `expwt/edit` und `newsletter/editorial` von cjw_newsletter (eine Ausgabe machen) oder `newsletter/send` (versenden); nur wo cjw_newsletter aktiv ist | der Newsletter der Seite. Auf einer Ausgabe: die Ausgabe. Auf jeder anderen Seite: die Ausgaben, die die Seite als Artikel enthalten, und **Eine Ausgabe aus dieser Seite machen**: eine Newsletter-Liste (die, unter denen der Benutzer eine Ausgabe anlegen darf, mit der Zahl ihrer Abonnenten), ein Titel (vorgegeben der der Seite) und eine optionale Einleitung ergeben eine neue, veröffentlichte Ausgabe unter der Liste, mit der Seite als Artikel, so wie die Artikelauswahl der Administration ihn übernimmt (Titel, Einleitung und „Weiterlesen", von der Artikelstatistik gezählt). Jede Ausgabe zeigt Liste, Status, Abonnenten, Freigabe und letzten Versand. Mit `newsletter/send`: **Test senden** an die angegebenen Adressen (vorgegeben die Testadressen der Liste, als Test markiert, höchstens so viele wie eine Testgruppe) und **An die Abonnenten senden ...**: der Bereich nennt Ausgabe, Liste und Zahl der Abonnenten und fragt noch einmal, dann fragt „Bestätigen Sie, dass Sie es sind" im kleinen Fenster, jedes Mal, wenn keine frische Bestätigung vorliegt (eigene Auslösegruppe `newsletter`, ausgeliefert eingeschaltet; ausgeschaltet versendet die Seitenleiste gar nicht und verweist auf die Administration). Der Versand ist der der Administration: die aktuelle Version, sofort, geprüft von Freigabe, Skin, Tracking und Drosselung des Newsletters wie auf der Versandseite; der Cronjob des Newsletters verschickt ihn. Eine Ausgabe, die gerade versendet wird, im Archiv liegt, ohne nötige Freigabe ist oder deren Liste keine Abonnenten hat, sagt, warum sie nicht versendet werden kann. Wo die Installation Newsletter-Mails in Dateien schreibt (cjw_newsletter.ini `TransportMethod*=file`), landen sie dort |
| URLs | `expwt/edit` (ein Alias zusätzlich `content/edit` der Seite, ein Platzhalter zusätzlich `content/urltranslator`) | die Adressen der Seite: ihre Adresse in jeder Sprache, ihre alten Adressen (sie leiten auf die aktuelle weiter), die ihr hinzugefügten Aliase (jeweils markiert, wenn er weiterleitet) und die URL-Platzhalter, die zu ihr führen, alle so, wie dieser Siteaccess sie beantwortet (ohne seinen Pfadpräfix). **Adressen, die Besucher nicht fanden**: die Adressen des Nicht-gefunden-Protokolls des Kernels (das Ende von `404.log`, ohne die Adressen der Besucher und ohne die Abfragen), deren letzter Teil der letzte Teil einer Adresse der Seite ist und die noch immer „nicht gefunden" antworten, die häufigsten zuerst, jede mit **Hierher weiterleiten**, das diese Adresse als weiterleitenden Alias hinzufügt. **Eine Adresse hinzufügen**, wie die URL-Aliase einer Seite in der Administration einen hinzufügen (`content/urlalias`): eine Adresse ab der Wurzel der Website, eine Sprache und ob sie auf die Adresse der Seite weiterleitet oder die Seite unter ihr zeigt (der Kernel bereinigt die Adresse wie dort und sagt das; eine vergebene Adresse wird abgelehnt). **Einen Platzhalter hinzufügen**, wie die URL-Platzhalter der Administration einen hinzufügen (`content/urlwildcards`): ein Adressmuster (ein `*` passt auf den Rest), die Adresse der Seite als Ziel, weiterleitend oder nicht. Hier wird nichts entfernt. Das Hinzufügen wird als `content.urlalias.change` protokolliert, wie in der Administration; „Bestätigen Sie, dass Sie es sind" fragt, wie es bei diesen Ansichten fragt |
| Kalender | `expwt/edit` (den Zeitpunkt eines Entwurfs oder einer Seite verschieben zusätzlich `content/edit` davon; eine Layoutvariante verschieben `expwt/layouts` und `explayouts_ui_api/edit` von explayouts) | alles Geplante für diese Seite, diese Seite und darunter oder die ganze Website, einen Monat auf einmal (sechs Wochen ab einem Montag): die Entwürfe, die das Ereignis „Wait until date“ zurückhält (Veröffentlichen), die Seiten, die der Hide-Cronjob herunternimmt (Herunternehmen), und die Layoutvarianten (eine explayouts-Regel mit einer Zeitbedingung: ihr Beginn und ihr Ende). Nur was Sie lesen dürfen; eine Markierung je Eintrag an seinem Tag und darunter die Liste der Wochen. Ziehen Sie den Griff eines Eintrags in der Liste oder seine Markierung im Raster auf einen anderen Tag (dieselbe Uhrzeit), oder **Verschieben** Sie ihn auf Datum und Uhrzeit; jede Verschiebung wird zuerst bestätigt und muss in der Zukunft liegen. Siehe „Der Kalender“ unten |
| Export | `expwt/use` (die Felder als Datei); `expwt/edit` mit `content/versionread` und `content/edit` des Kernels (ein Freigabelink) | die Seite als Datei: drucken oder als PDF speichern mit einem Drucklayout, ihre Felder als Markdown oder Word (die veröffentlichte Version oder Ihr Entwurf) und ein signierter, ablaufender Freigabelink zu Ihrem Entwurf für Prüfer ohne Konto. Siehe „Export: Drucken, PDF, Markdown, Word und Freigabelinks“ |
| Einblicke | `expwt/insights` | nur lesend, aus den eigenen Statistiken des Kernels, ohne Tracking Dritter: die Aufrufe der Seite (der Aufrufzähler, den der Cronjob `updateviewcount` aus dem Zugriffsprotokoll füllt; ist er aus, sagt der Bereich das), die Suchen auf der Website nach Wörtern des Seitentitels (das Suchprotokoll des Kernels, mit Anzahl und Treffern) und dass der Kernel keine Herkunftsseiten erfasst |
| Posteingang | `expwt/use` (für das Freigeben auch die Richtlinie `collaboration` des Kernels) | die Freigaben des Veröffentlichungs-Workflows, die auf Sie warten (das Freigabe-Ereignis des Kernels): jede Seite mit Autor und letztem Kommentar, **Entwurf öffnen**, und für ihre Freigebenden ein Kommentar mit **Freigeben** oder **Ablehnen**, entschieden über die eigene Ansicht `collaboration/action` des Kernels, genau wie in der Administration (der Workflow veröffentlicht eine freigegebene Seite bei seinem nächsten Lauf; eine abgelehnte ist wieder ein Entwurf ihres Autors). Ihre Anzahl steht als Zähler auf dem geschlossenen Reiter und auf dem Reiter des Bereichs Daneben die anderen Benachrichtigungen, aus der Zusammenarbeit und den Workflows des Kernels (keine zusätzliche Mail): **Kommentare und Erwähnungen** (Einträge mit einem ungelesenen Kommentar eines anderen, „Erwähnt Sie", wo der letzte Sie als @Login nennt), **Ihre Seiten** (Ihre Seiten, die seit Ihrem letzten Blick freigegeben oder abgelehnt wurden, innerhalb von 30 Tagen) und **Geplante Aufgaben, die fehlgeschlagen sind** (Ihre Workflow-Prozesse, die innerhalb von 30 Tagen mit einem Fehler stehen blieben, mit dem vollständigen Editor der Seite). Kommentare und Ergebnisse sieht jeder, der am Eintrag beteiligt ist (auch ein Autor ohne die Richtlinie `collaboration`, wie in der ausgelieferten Rolle Editor). **Als gelesen markieren** tut, was das Öffnen des Eintrags in der Administration tut; **In der Administration öffnen** (mit der Richtlinie `collaboration`) öffnet ihn dort. Ihre Anzahl zusammen (jeder Eintrag einmal) steht als Zähler auf dem geschlossenen Reiter und dem Reiter des Bereichs, mit wenigen Abfragen gezählt und eine Minute behalten; Öffnen des Bereichs, Markieren als gelesen oder eine Entscheidung zählen sofort neu |
| Migrationen | `expwt/use` und `migration/read` des Kernels (ab Exponential 6.0.15); ohne diese Richtlinie oder auf einem Kernel ohne Migrationen gibt es keinen Reiter Migrationen | nur lesend: der Stand der Migrationen der Installation, wie ihn Einrichtung > Migrationen in der Administration zeigt, auf jeder Seite. Ein gerade laufender Lauf (die Sperre der Installation: was er tut, wer ihn gestartet hat und seit wann), die **fehlgeschlagenen** Migrationen (jede mit Quelle und Beschreibung), die **ausstehenden** (ID, Quelle, Beschreibung; die ersten 50, der Rest gezählt), die Migrationen, die **verzeichnet sind, deren Datei aber fehlt**, und der **letzte Lauf**: sein Status, wann er begann und endete, wer ihn ausgeführt hat, sein Sicherungspunkt, sein Tag und sein Push-Status (ein fehlgeschlagener Lauf mit seiner Meldung). Jeder Eintrag hat **In der Administration öffnen** (der Stand oder das Protokoll seines Laufs); Ausführen, Fortsetzen und Zurücknehmen bleiben dort: die Seitenleiste führt hier nichts aus. Die Zahl der ausstehenden, fehlgeschlagenen und ohne Datei verzeichneten Migrationen steht als Zähler auf dem Reiter des Bereichs, höchstens einmal pro Minute gezählt. **Aufzeichnung** (mit `migration/edit` des Kernels und einem Kernel, der Änderungen aufzeichnet, ab Exponential 6.0.15): **Meine Änderungen aufzeichnen** startet eine Aufzeichnung der eigenen Änderungen für eine Migration, wie Einrichtung > Migrationen > Aufzeichnung, und **Aufzeichnung beenden** beendet sie; solange sie läuft, zeigt der Bereich **Aufzeichnung läuft…** mit einem roten Punkt, und auch der Reiter des Bereichs trägt einen roten Punkt. Was der Benutzer auf der Website, in der Seitenleiste oder in der Administration ändert, wird bis zum Beenden als Schritte einer Migration festgehalten; das Konto und die Felder von Benutzern werden nie aufgezeichnet. Eine Aufzeichnung der ganzen Website wird hier nur angezeigt (Öffnen und Schließen bleiben in der Administration). Ohne die Richtlinie, bei abgeschalteter Aufzeichnung (`migration.ini [RecordingSettings] Recording=disabled`) oder auf einem Kernel ohne sie hat der Bereich keinen Aufzeichnungsteil. **Deploys** (mit `migration/deploy` des Kernels und einem Kernel, der Migrationen auf Gegenstellen überträgt, ab Exponential 6.0.15): die letzten fünf Deploys, nur lesend, in den Worten von Einrichtung > Migrationen > Deploys: jeder mit seinem Status, ob die Gegenstellen aus dem Gleichstand sind, jede Gegenstelle mit ihrem Status (nach ihrer ID, nie mit Adresse), wer ihn gestartet hat und wann, und **In der Administration öffnen** zu seiner Seite dort |
| Statisches Veröffentlichen | `expwt/use` und `staticpublish/read` des Kernels (ab Exponential 6.0.15); **Veröffentlichen** zusätzlich `staticpublish/publish` des Ziels; ohne `staticpublish/read` oder auf einem Kernel ohne statisches Veröffentlichen gibt es keinen Reiter | diese Seite im statischen Veröffentlichen des Kernels (Setup > Statisches Veröffentlichen): je Ziel die letzte Veröffentlichung, ob diese Seite dort veraltet ist, und ihre öffentliche Adresse dort; **Veröffentlichen** veröffentlicht nur diese Seite. Siehe „Bereich Statisches Veröffentlichen" |
| Performance | `expwt/performance` (Administratoren und Entwickler) | nur lesend: wie diese Seite gerendert wurde, aus den Daten der Debug-Leiste des Kernels zum Rendern der Seite selbst (nicht zu den Anfragen der Seitenleiste): die Renderzeit, die SQL-Anweisungen und ihre Zeit, der Spitzenspeicher, der Content-View-Cache (aus dem Cache, erzeugt, aus), die Cache-Blöcke (wie viele aus dem Cache kamen, die langsamsten) und die Zeit jedes Layout-Blocks, ein Block in einem Container eingerückt. Eine Seite aus einem Cache wurde nicht für diesen Aufruf gerendert: der Bereich sagt es und nennt die Zeit des Renderns, von dem seine Zahlen stammen. Siehe „Bereich Performance" |
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
die Antwort „Zur Freigabe gesendet".

**Veröffentlichen am und Zurückziehen am** nutzen die Zeitsteuerung des Kernels, dieselbe wie die Administration: Die
Seitenleiste hat keinen eigenen Zeitplan und keinen eigenen Cronjob.

- *Veröffentlichen am* schreibt den Zeitpunkt in das Datumsfeld des Entwurfs, das ein Ereignis „Wait until date“
  aufführt (ein Workflow an `content/publish` before), und veröffentlicht den Entwurf: Das Ereignis hält ihn bis dahin
  zurück, der Workflow-Cronjob (`workflow.php`) veröffentlicht ihn dann, und die Antwort lautet „Geplant für …“. Jedes
  andere Ereignis dieses Workflows gilt, mit einer Freigabe wartet der Entwurf also auch auf sie.
- *Zurückziehen am* schreibt den Zeitpunkt in das Feld, das `content.ini [HideSettings]
  HideDateAttributeList[<Klasse>]` nennt, für eine Seite unterhalb eines Knotens aus `RootNodeList[]`; der Hide-Cronjob
  (`hide.php`) verbirgt die Seite dann. Ein geleertes Eingabefeld löscht den Zeitpunkt.
- Diese Datumsfelder werden nur hier bearbeitet, nicht noch einmal in der Liste der Felder. Ein Zeitpunkt, der noch
  bevorsteht, wird angezeigt.
- Eine Klasse ohne ein solches Feld zeigt statt der Eingabe „Hier nicht möglich: Diese Art Seite hat kein Datumsfeld
  dafür.“, und der Server lehnt einen Zeitpunkt dafür ab. Wie eine Klasse das Feld bekommt, steht unter
  „Zeitsteuerung“ in der Anleitung des Kernels `doc/ger-de/funktionen/6.0/website-seitenleiste.md`: ein Datumsfeld
  (Datum und Uhrzeit, oder Datum für einen Tag), das Wait-until-date-Ereignis, das es am Veröffentlichen-Trigger
  aufführt, und die Zeilen in `[HideSettings]`.

**Der Kalender** zeigt und verschiebt nur, was der Kernel und explayouts planen; er führt nichts Eigenes.

- *Veröffentlichen*: ein Entwurf, der im Ereignis „Wait until date“ des Workflows an `content/publish` wartet (eine
  ausstehende Version). Verschieben schreibt den neuen Zeitpunkt in das Datumsfeld des Entwurfs und teilt seinem
  wartenden Workflow-Prozess mit, wann er aufwacht, sodass der Workflow-Cronjob ihn dann veröffentlicht; jedes andere
  Ereignis des Workflows (eine Freigabe) gilt weiter.
- *Herunternehmen*: eine veröffentlichte Seite, deren Datumsfeld aus `[HideSettings]` in den gezeigten Wochen liegt.
  Verschieben veröffentlicht eine neue Version der Seite mit dem neuen Zeitpunkt, wie die Administration es tut; mit
  einem Freigabe-Workflow lautet die Antwort, dass der neue Zeitpunkt auf seine Freigabe wartet.
- *Layoutvariante*: eine explayouts-Regel mit einer Zeitbedingung, die für die Seite gilt (ein Knoten- oder
  Teilbaum-Ziel oder ein Adressziel, das auf die Seite passt; bei „Diese Seite und darunter“ auch die Seiten darunter).
  Ihr Beginn verschiebt den ganzen Zeitraum (das Ende behält seinen Abstand), ihr Ende verschiebt sich allein. Der
  Cache des Resolvers und die Template-Blöcke verfallen wie nach jeder Regeländerung. Eine ausgeschaltete Regel ist
  markiert.
- Jede Verschiebung wird als `content.expwt.schedule.change` protokolliert (der eigene Zweig der Seitenleiste in der
  Audit-Taxonomie, standardmäßig an), mit dem Zeitpunkt davor und danach.
- Was die Installation nicht eingerichtet hat, steht unter „Auf dieser Website nicht eingerichtet“: kein Ereignis
  „Wait until date“ am Veröffentlichen-Trigger, oder keine Klasse und kein oberer Knoten in `[HideSettings]`.

**Fokuspunkt und Zuschnitt** eines Bildfelds (im Seiten-Panel, unter dem Editor des Felds, geschlossen, bis man es
öffnet). Er braucht einen Exponential-Kernel, der sie speichert (6.0.15: Fokuspunkt und Zuschnittsrechteck des
Bild-Datentyps); mit einem älteren Kernel wird der Teil nicht gezeigt, und die Seitenleiste lehnt seine Aktionen ab.

- Klicken oder ziehen Sie auf dem Bild, um den Fokuspunkt zu setzen, den Teil des Bildes, der sichtbar bleiben muss;
  die Pfeiltasten verschieben ihn (mit Umschalt in größeren Schritten). **Zuschneiden** fügt einen Rahmen hinzu:
  ziehen Sie ihn oder seine Ecken; auf dem fokussierten Rahmen verschieben ihn die Pfeiltasten, und Umschalt mit einer
  Pfeiltaste ändert seine Größe. **Zurücksetzen** entfernt beides.
- **So sieht jede Größe aus** zeigt jede Bildvariante, die die Templates der Seite verwenden (jede Variante, die das
  Bild schon hat, in der veröffentlichten Seite oder im Entwurf, und jede Variante mit einem Filter `geometry/crop`),
  live, während Sie den Punkt oder den Rahmen bewegen, berechnet wie der Kernel die Variante berechnet: Der Rahmen wird
  zuerst ausgeschnitten, und jeder Zuschnittfilter der Variante wird auf den Fokuspunkt zentriert. Eine Variante aus
  einem Filter, dem die Vorschau nicht folgen kann, zeigt ihre Datei, wie sie ist.
- **Entwurf speichern** schreibt Fokuspunkt und Zuschnitt in Ihren Entwurf (das erste Speichern legt den Entwurf an,
  wie der Editor des Felds es tut). Der Kernel speichert das Original für den Entwurf neu und erzeugt seine Größen um
  den Punkt neu; die Vorschauen zeigen dann diese Dateien. Die veröffentlichte Seite ändert sich erst, wenn der
  Entwurf veröffentlicht wird, auf dem üblichen Weg (und mit seiner Freigabe, wo es eine gibt).
- Braucht `expwt/edit` und das `content/edit` des Kernels für die Seite in ihrer Sprache; „Bestätigen Sie, dass Sie es
  sind" fragt, wie das Bearbeiten in der Administration fragt. Jede Änderung wird als `content.expwt.image.focal`
  protokolliert, mit den Werten davor und danach.
- Aktionen `images.load` und `images.save`. Ein SVG-Bild hat keinen Fokuspunkt (seine Größen sind die Originaldatei).

**Gerätevorschau** (**Geräte** im Bereich Seite, neben Vorschau): die Seite in einem Rahmen je Gerät, nebeneinander
über dem ganzen Fenster, um zu sehen, wie sie sich aufbaut, bevor sie veröffentlicht wird.

- Die Rahmen aus `expwt.ini [DevicePreviewSettings] DeviceList[]`: iPhone 13 (390 x 844), Pixel 7 (412 x 915), ein
  Tablet (820 x 1180), **1920 breit bei 200 % Zoom** (ein 1920 Pixel breites Fenster mit 200 % Zoom, das die Seite
  wie bei 960 Pixeln aufbaut: der Fall einer Leserin, die die Seite vergrößert) und ein Desktop (1440 x 900).
  **Eine eigene Breite** fügt einen Rahmen beliebiger Breite von 240 bis 2560 Pixeln hinzu (bis zu vier), **Den Rahmen
  entfernen** nimmt ihn wieder weg.
- **Gezeigte Rahmen:** eine Reihe von Umschaltern, einer je Gerät und je eigener Breite; ein gedrückter Umschalter (mit
  Häkchen) zeigt seinen Rahmen. Ein Umschalter blendet seinen Rahmen sofort aus oder ein, und die übrigen Rahmen werden
  größer, bis sie den Platz füllen (ein Rahmen lädt, wenn er zum ersten Mal gezeigt wird). **Alle zeigen** holt jeden
  Rahmen zurück. Beim ersten Öffnen der Vorschau sind alle Rahmen zu sehen, oder die aus `ShownDeviceList[]`, wo die
  Installation welche nennt; danach merkt sich dieser Browser Ihre Wahl (in seinem lokalen Speicher, nichts auf dem
  Server). Die Reihe bricht in einem schmalen Fenster um und scrollt in einem niedrigen in der Leiste; die Umschalter
  sind Schaltflächen, mit Tab erreichbar und mit Leertaste oder Enter gedrückt. Eine Seite, die in einem Rahmen lädt
  (etwa ihr Cookie-Hinweis), nimmt dem Dialog den Tastaturfokus nicht weg.
- Jeder Rahmen ist das Fenster des Geräts, verkleinert, bis er passt: eine Reihe, wo jeder Rahmen lesbar bleibt, sonst
  jeder Rahmen für sich, so groß, wie das Fenster erlaubt, die Rahmen untereinander umbrochen. Ein Rahmen scrollt für
  sich, und seine Links funktionieren darin.
- **Version:** Ihr eigener neuester Entwurf der Seite in ihrer Sprache (die Entwurfsvorschau des Kernels,
  `content/versionview`, die noch einmal prüft, dass Sie ihn lesen dürfen) oder die veröffentlichte Seite. Ohne einen
  Entwurf von Ihnen zeigen die Rahmen die veröffentlichte Seite und sagen das.
- **Hell oder dunkel je Rahmen:** Wie die Website, Hell oder Dunkel; die Wahl gilt nur in diesem Rahmen (der Farbmodus
  der Media-Designs), an der eigenen Wahl der Website ändert sich nichts. Wie die Website lädt den Rahmen neu, wie die
  Website ihn hat.
- Die Seiten in den Rahmen zeigen keine eigene Seitenleiste und keine Debug-Leiste (`?in=sidebar`). Die Auswahl merkt
  sich dieses Gerät. Nur lesend: auf dem Server wird nichts gespeichert.
- Braucht `expwt/edit` (die Schaltfläche gibt es für alle, die die Seite bearbeiten dürfen). Aktion `preview.devices`
  (GET): sie antwortet mit den Rahmen und den beiden Adressen, ohne `expwt/edit` mit 403.

**Upgrade von einer früheren expwt-Version:** Sie führte einen eigenen Zeitplan (die Site-Data-Zeilen `expwt_publish_*`
und `expwt_unpublish_*`, den Cronjob-Teil `expwt_scheduled`). `./console exp:wt:migrate-from-ezwt` zeigt, was überführt
wird, `--apply` überführt es (der Upgrade-Schritt `6.0.15-expwt-enable` tut dasselbe); ein Eintrag, dessen Klasse das
Datumsfeld fehlt, bleibt, wird aufgeführt und bei einem späteren Lauf überführt, sobald die Klasse es hat.

**Zwei Redakteure, eine Seite:** Wer zuerst etwas ändert, hält eine weiche Sperre; die anderen sehen „Anna bearbeitet
diese Seite seit 10:42" und können sie übernehmen. Speichern beide trotzdem, geht nichts verloren: Felder, die nur einer
geändert hat, werden zusammengeführt, und bei einem Feld, das beide verschieden geändert haben, wählt der zweite je Feld,
welcher Text bleibt.

**Nichts Getipptes geht verloren (automatisches Speichern):** Was im Bereich Seite getippt wird, wird alle paar
Sekunden in den Entwurf des Benutzers gespeichert, denselben, den „Entwurf speichern" schreibt, und nur, wenn sich etwas
geändert hat: Eine Seite oder den Editor eines Felds zu öffnen, legt keinen Entwurf an. Die eigenen Eingaben des Bereichs
werden gespeichert, sobald das Tippen pausiert; der Editor eines Felds (Rich Text, Tags, ein Bild ...), sobald der Fokus
ihn verlassen hat, weil das Speichern seinen Rahmen neu lädt. „Automatisch gespeichert um 10:42" unter dem Namen der
Seite sagt, wann. Bis der Server es hat, hält der Browser außerdem eine Kopie auf diesem Gerät (in seinem lokalen
Speicher, zwei Wochen lang, je Seite und Sprache). Wurde das Fenster geschlossen, brach die Verbindung ab oder stürzte
der Browser vorher ab, zeigt der geschlossene Reiter auf dieser Seite beim nächsten Mal ein **!** („Nicht gespeicherte
Änderungen auf dieser Seite"), und der Bereich Seite nennt die Felder und die Zeit, mit **Wiederherstellen** (setzt den
Text wieder ein und speichert ihn in den Entwurf) und **Verwerfen**. Ein Feld, das inzwischen jemand anderes auf dem
Server geändert hat, geht durch die Zusammenführung Feld für Feld (oben). Veröffentlichen oder Verwerfen des Entwurfs
entfernt die Kopie.

### Barrierefreiheit für Besucher

Wo nicht angemeldete Besucher die Seitenleiste sehen (`ShowToAnonymous=enabled`, oder `policy` mit `expwt/use` für die
Rolle Anonymous), hat ihr Bereich „Willkommen“ **Lesen und Darstellung**: Einstellungen für die ganze Website, nicht nur
für die Seitenleiste.

- **Textgröße:** Normal, Groß (125 %), Größer (150 %), Am größten (200 %). Sie skaliert die eigene Grundgröße der Website,
  ein Design in `rem` (media, smarty_media, twig_media und die meisten Themes) wächst also überall und bricht um, statt zu
  zoomen.
- **Hoher Kontrast:** Text in einer Farbe auf einem Hintergrund (schwarz auf weiß, weiß auf schwarz, wo die Website oder
  das System dunkel ist), Links in einer eigenen Farbe, jeder Rahmen in der Textfarbe, ein kräftiger Fokusrahmen;
  Hintergrundbilder und Schatten entfallen. Bilder, Videos und die Seitenleiste bleiben, wie sie sind.
- **Bewegung reduzieren:** Animationen und Übergänge enden sofort, weiches Scrollen ist aus (zusätzlich zu dem, was die
  Website für „Bewegung reduzieren“ des Systems tut).
- **Gut lesbare Schrift:** eine schlichte serifenlose Schrift mit etwas mehr Buchstaben- und Wortabstand und höherem
  Zeilenabstand; Code und Symbolzeichen behalten ihre.
- **Links unterstreichen:** jeder Link unterstrichen.

Die Auswahl wird **auf diesem Gerät** gespeichert (im lokalen Speicher des Browsers, `expwt.visitor`), nie auf dem
Server, und gilt auf jeder Seite der Website, sobald sie lädt. **Zurück zum Aussehen der Website** vergisst sie. Sie
wirkt über Attribute auf `<html>` (`data-expwt-text`, `data-expwt-a11y`) und ein Stylesheet aus Tokens (`--expwt-a11y-*`,
mit hellen und dunklen Werten), das nur diese Attribute einschalten: Für einen Besucher ohne Auswahl ändert sich nichts,
kein Inline-Style wird geschrieben. Damit die Auswahl gegen Farben, Schriften und Animationen eines Themes gewinnt, sind
die Regeln absichtlich `!important`. Ein angemeldeter Benutzer auf demselben Gerät behält die Auswahl. Mit
`ShowToAnonymous=disabled` (der Voreinstellung) bekommen Besucher keine Seitenleiste, also auch nichts hiervon, und
kein Byte mehr.

### Prüfsperre

Eine Seite lässt sich **zur Prüfung sperren**, solange jemand sie prüft (Rechtsabteilung, Compliance, ein Kunde, eine
Sperrfrist): Bis ein Prüfer sie freigibt, bearbeitet und veröffentlicht sie niemand. Besucher sehen weiter die
veröffentlichte Seite.

- Die Sperre ist der Objektzustand `review_hold/held` des Kernels (Exponential 6.0.15, angelegt vom Upgrade-Schritt
  `6.0.15-review-hold`, Anleitung zum Teil des Kernels: `doc/eng-us/features/6.0/review-hold.md`). Der Schritt ergänzt
  jede Policy `content/edit` und `content/publish` um die Einschränkung `StateGroup_review_hold = not_held`: So
  verweigern die eigenen Policies der Rollen das Bearbeiten einer gesperrten Seite, und der Kernel bricht das
  Veröffentlichen einer gesperrten Seite ab, wenn `content/publish` des Benutzers den gesperrten Zustand nicht erlaubt
  (auch ein geplantes oder freigegebenes Veröffentlichen: die Version bleibt ein Entwurf).
- Der Bereich „Seite“ zeigt die Sperre oben: **Zur Prüfung gesperrt**, von wem und seit wann, den Grund und was sie für
  Sie bedeutet. Wer die Seite nicht bearbeiten darf, sieht kein Formular; eine Rolle, deren Policy den gesperrten
  Zustand erlaubt (und ein Administrator), bearbeitet weiter und erfährt, dass das Veröffentlichen wartet.
- **Zur Prüfung sperren** und **Freigeben** bekommt, wem `state/assign` des Kernels die Gruppe erlaubt (die Rolle
  **Review hold**, die der Schritt anlegt und niemandem zuweist; Administratoren). Beim Sperren fragt die Seitenleiste
  nach dem Grund, beim Freigeben nach einer freiwilligen Notiz. Beides wird mit dem Grund im Audit festgehalten
  (`content.expwt.hold.place`, `content.expwt.hold.release`), neben `content.object.state` des Kernels; ein
  abgebrochenes Veröffentlichen ist `content.object.hold.refused`. Die Aktionen der Seitenleiste brauchen nur
  `expwt/use`: Es entscheidet die Policy des Kernels.
- Eine Sperre aus der Administration (die Zustände des Objekts) wird ebenso gezeigt, nur ohne wer und warum.
- Ohne die Zustandsgruppe (ein älterer Kernel, oder der Schritt lief noch nicht) gibt es keine Sperre: Der Bereich
  „Seite“ nennt denen, die Zustände verwalten, den Schritt, und sonst ändert sich nichts.

### Barrierefreiheitsprüfung

Der Bereich SEO endet mit **Barrierefreiheit der Seite prüfen (WCAG 2.2 AA)**: Auf Anforderung prüft axe-core die
Seite, wie der Browser sie darstellt, gegen die Regeln von WCAG 2.0, 2.1 und 2.2 in den Stufen A und AA. Die
Seitenleiste selbst bleibt außen vor, nichts wird gespeichert und nichts an einen Server geschickt.

- axe-core 4.11.3 wird unverändert mitgeliefert (`design/standard/javascript/expwt-axe.min.js`, Mozilla Public License
  2.0) und erst geladen, wenn die Prüfung läuft, für Benutzer mit `expwt/edit`: kein Byte für alle anderen oder bevor
  die Schaltfläche gedrückt wird.
- Ein Befund je Element, die schwersten zuerst: Kritisch, Schwerwiegend, Mittel, Gering (die Auswirkung laut axe-core). Jeder
  sagt, wo er ist: das **Feld** der Seite (die Feldmarkierungen des Kernels), der **Block** des Layouts (sein
  Ansichtstyp) und das **Template** (wenn der Siteaccess die Template-Debug-Kommentare des Kernels zeigt). Ein Klick
  öffnet das Feld im Bereich Seite oder zeigt das Element auf der Seite. **Mehr zu dieser Regel** öffnet die Seite
  von axe-core zur Regel.
- Die Regeltexte sind die von axe-core, auf Englisch; alles darum herum ist übersetzt.
- Eine automatische Prüfung findet nur einen Teil der Probleme (Kontrast, Namen von Bedienelementen und Bildern,
  Struktur, ARIA); die Seite auch nur mit der Tastatur und mit einem Screenreader ausprobieren.

### Bereiche je Rolle (Setup > Bereiche der Website-Seitenleiste)

Für den gewählten Siteaccess zeigt die Seite auch **Beschriftung der Reiter**: Symbole, Symbole und Text oder Text,
die Beschriftung der Reiter für Benutzer, die im Bereich Ich nicht selbst gewählt haben
(`[ExpwtSettings] TabLabels`); **Speichern** schreibt sie ebenso in die `expwt.ini.append.php` des Siteaccess,
zuvor eine Sicherung.

Administratoren legen je Siteaccess und Rolle fest, welche Bereiche der Seitenleiste die Benutzer einer Rolle sehen und
in welcher Reihenfolge: **Setup > Bereiche der Website-Seitenleiste** (`expwt/panels`). Siteaccess (die Siteaccesses der Website, jeweils mit
„Seitenleiste an" oder „Seitenleiste aus" markiert; Administrationen stehen nicht in der Liste) und Rolle wählen, dann:

- **Angezeigt** ankreuzen für die Bereiche, die die Benutzer der Rolle sehen, und einen Bereich mit den Pfeilen nach
  oben oder unten verschieben; **Speichern** schreibt es. Seite und Ich werden immer angezeigt (in Seite landet das
  Bearbeiten auf der Seite, Ich enthält die Einstellungen und die Abmeldung). Bereiche, die andere Erweiterungen
  registrieren (siehe „Erweiterungen fügen der Seitenleiste etwas hinzu"), stehen mit in der Liste.
- **Rollen mit eigenen Bereichen** listet die Rollen, die in diesem Siteaccess eingerichtet sind. Ein Benutzer mit mehreren davon bekommt
  die Reihenfolge der ersten (eine Rolle nach oben oder unten verschieben ändert, welche das ist); ein Bereich bleibt
  sichtbar, wenn eine der Rollen des Benutzers in der Liste ihn zeigt. Diese Einstellungen nehmen also nie einen
  Bereich weg, den eine andere Rolle desselben Benutzers zeigt. Ein Benutzer, von dessen Rollen keine in der Liste
  steht, sieht die eigene Reihenfolge der Seitenleiste. **Bereiche dieser Rolle entfernen** stellt die Rolle darauf zurück.
- Ausblenden betrifft nur das Aussehen der Seitenleiste: Was ein Benutzer tun darf, bestimmen weiter die Policies
  seiner Rollen. Die Aktionen eines ausgeblendeten Bereichs antworten denen, die sie dürfen, weiter, und ein Bereich,
  den die Policies einer Rolle nicht geben, erscheint nicht, was auch immer die Liste sagt.
- Die Einstellungen werden über den Einstellungseditor des Kernels in
  `settings/siteaccess/<siteaccess>/expwt.ini.append.php` geschrieben, so wie `exp:ini` und Setup > Einstellungen
  schreiben: Nur die Zeilen dieser Einstellungen ändern sich, die Datei wird vorher nach `var/backup/ini/<Zeit>/`
  gesichert, die Änderung wird protokolliert (`system.setting.write`) und der INI-Cache geleert. **Verlauf**
  listet die früheren Fassungen der Datei (die letzten 20 Sicherungen) mit den Rollen, für die sie Bereiche hatten;
  **Wiederherstellen** stellt deren Rolleneinstellungen wieder her (der Rest der Datei bleibt, wie er ist, und die
  Wiederherstellung legt selbst eine Sicherung an, lässt sich also genauso rückgängig machen).
- Bestimmt eine andere Einstellungsdatei diese Einstellungen für den Siteaccess (die
  `settings/expwt.ini.append.php` einer Erweiterung oder `settings/override/expwt.ini.append.php`, die nach
  `settings/siteaccess/` gelesen werden), sagt die Seite es: Was dort gespeichert wird, wirkt erst ohne sie.
- Ob die Seitenleiste in einem Siteaccess an ist (die Auswahlliste), verlangt die Einstellungen jedes Siteaccess zu
  lesen; die Antwort bleibt deshalb im Cache-Verzeichnis (`expwt/setup-siteaccesses.json`), bis eine `site.ini` oder
  `expwt.ini` der Installation hinzukommt, wegfällt oder sich ändert. Bei jedem Aufruf gelesen werden nur die
  Einstellungen des gewählten Siteaccess.
- Braucht `expwt/admin` und das Einstellungsmodul des Kernels: `settings/view`, um die Seite zu sehen, `settings/edit`,
  um etwas zu ändern. Das Setup-Menü zeigt den Link Benutzern mit `expwt/admin` und `settings/view`; alle anderen
  werden abgewiesen.

Die Einstellungen, um sie von Hand oder mit `exp:ini` zu schreiben:

```ini
[RolePanelSettings]
RoleList[]
RoleList[]=3                # Rollen-IDs; die erste bestimmt die Reihenfolge
RoleList[]=4

[RolePanels_3]
PanelOrder[]
PanelOrder[]=calendar       # diese zuerst, in dieser Reihenfolge; der Rest in der eigenen Reihenfolge der Seitenleiste
PanelOrder[]=page
HiddenPanelList[]
HiddenPanelList[]=seo
```

Die Startantwort enthält die Regel der Rollen des Benutzers (`panels`: `order`, `hidden`; null ohne eine); die
Seitenleiste wendet sie auf die Reiter an, die sie baut (die Tastenkürzel Alt+Umschalt+1 bis 9 folgen der neuen
Reihenfolge).

### Branding je Siteaccess

Ein Siteaccess kann der Seitenleiste einen eigenen Namen, ein Logo und eine Akzentfarbe geben (White Label), in
`settings/siteaccess/<Siteaccess>/expwt.ini.append.php`:

```ini
[BrandingSettings]
Name=Acme Editor
Logo=/extension/acme/design/acme/images/sidebar-logo.svg
AccentColor=#1d3557
# AccentColorDark=#8da4c4   (leer: AccentColor, aufgehellt, bis sie auf der dunklen Leiste besteht)
```

- **Name** steht anstelle von „Website-Seitenleiste" in der Überschrift, im Tooltip des Reiters und in dem, was
  Screenreader ansagen (höchstens 60 Zeichen, nicht übersetzt).
- **Logo** steht neben dem Namen am linken und rechten Rand (höchstens 32 Pixel hoch; die einzeilige Leiste oben und
  unten lässt es weg). Ein Pfad ab der Wurzel der Website oder eine `https`-Adresse einer svg-, png-, webp-, jpg-, gif-
  oder avif-Datei; alles andere wird nicht verwendet.
- **AccentColor** färbt den gewählten Reiter, die Hauptschaltflächen, gedrückte Schalter, Links und den Fokusrahmen. Sie
  wird vor der Verwendung nach WCAG 2.2 AA geprüft: Weißer oder fast schwarzer Text muss auf ihr 4,5:1 erreichen, und
  sie selbst 3:1 gegen die Leiste und ihre Karten. Links und Fokusrahmen bekommen einen Ton desselben Farbtons, der
  4,5:1 erreicht. Eine Farbe, die durchfällt (oder keine `#rrggbb`-/`#rgb`-Farbe ist, gewertet als 0:1), wird nicht
  verwendet - die Seitenleiste behält ihren eigenen Akzent -, und Setup > Bereiche der Website-Seitenleiste sagt es für
  diesen Siteaccess, mit dem Kontrast, den sie hat, und dem, der nötig ist. Ebenso für ein Logo, das nicht verwendet
  wird.
- **AccentColorDark** wird genauso gegen die dunkle Leiste geprüft; leer, hellt die Seitenleiste AccentColor schrittweise
  auf, bis sie dort besteht.
- Die Werte erreichen die Seitenleiste als Stylesheet ihres Shadow DOM (kein style-Attribut), und nur auf Seiten, die
  die Seitenleiste zeigen: Für einen Siteaccess ohne Branding kommt nichts hinzu.
- Die Hilfe (die Tastenkürzel, Alt+Umschalt+H) sagt immer „Website-Seitenleiste von Exponential", wie auch immer das
  Branding ist.
- Die Feldeditoren im Rahmen der Seitenleiste behalten den Akzent der Administration.

### Export: Drucken, PDF, Markdown, Word und Freigabelinks

Der Bereich „Export“ gibt die Seite in anderen Formen aus:

- **Drucken oder als PDF speichern:** der Druckdialog des Browsers mit einem Drucklayout: die Seitenleiste und der
  Platz, den sie einnimmt, werden nie gedruckt. **Nur der Inhalt** lässt Kopfbereich, Menüs, Seitenspalte und Fußbereich
  der Website weg (die innerhalb des Hauptinhalts bleiben); **Linkadressen hinter den Links** schreibt die Adresse jedes
  Links in Klammern dahinter. Für ein PDF wählen Sie im Druckdialog „Als PDF speichern“: jeder Browser hat es, und das PDF
  ist die Seite, wie der Leser sie sieht, im Design der Website.
- **Felder als Datei:** die Felder der Seite als **Markdown** (`.md`) oder **Word** (`.docx`), von der veröffentlichten
  Version oder, wenn Sie einen haben, von Ihrem Entwurf: der Name als Titel, darunter Klasse, Sprache und Version, dann
  jedes Feld mit seinem Namen als Überschrift. Rich Text behält Überschriften, Listen, Zitate, Code, Fett, Kursiv und
  Links; ein Bild ist sein Alternativtext; leere Felder fallen weg. Die Word-Datei schreibt expwt selbst (keine
  Bibliothek). Eine Datei zu erstellen fragt zuerst „Bestätigen Sie Ihre Identität“, wie die Exporte der Administration
  (die Gruppe `export` in `site.ini [ReauthSettings]`).
- **Entwurf zur Prüfung teilen** (ab Exponential 6.0.15, mit dem Geheimnisspeicher): ein Link, mit dem jemand
  ohne Konto Ihren Entwurf im Design der Website sieht, bis der Link abläuft (eine Stunde, ein Tag, drei Tage oder eine
  Woche, nie länger als `content.ini [PreviewLinkSettings] MaxLifetime` des Kernels). Er braucht `expwt/edit` und
  `content/versionread` und `content/edit` des Kernels und fragt zuerst „Bestätigen Sie Ihre Identität“. Der Link ist mit
  einem Schlüssel aus dem Geheimnisspeicher signiert, zeigt genau diese eine Version in dieser einen Sprache und sonst
  nichts, wird nie zwischengespeichert oder indexiert und funktioniert nicht mehr, wenn er abläuft, zurückgezogen wird
  oder der Entwurf veröffentlicht oder verworfen ist. Der Bereich listet die Links der Seite mit ihrem Zustand, wer sie
  erstellt hat, einer Notiz, an wen sie gingen, und wie oft sie geöffnet wurden; **Zurückziehen** beendet einen sofort.
  Erstellen, Zurückziehen und Öffnen eines Links stehen im Audit (`content.previewlink.*`). Auf einem älteren Kernel sagt
  der Bereich, dass Freigabelinks Exponential 6.0.15 brauchen. Anleitung zum Teil des Kernels:
  `doc/eng-us/features/6.0/preview-links.md`.

### Bereich Statisches Veröffentlichen

Mit dem statischen Veröffentlichen des Kernels (Exponential 6.0.15, Setup > Statisches Veröffentlichen: eine Website als
statische Dateien auf GitHub Pages oder einem anderen Git-Host) zeigt der Bereich **Statisches Veröffentlichen** diese
Seite auf jedem Ziel, das der Benutzer lesen darf (`staticpublish/read` mit seiner Einschränkung Ziel):

- **Letzte Veröffentlichung:** wann, und ob sie gepusht, nicht gepusht, fehlgeschlagen ist oder nichts geändert hat;
  **nie** vor der ersten.
- **Veraltete Seiten:** **Ja**, wenn Inhalt dieser Seite veröffentlicht wurde, nachdem die Seite für dieses Ziel gebaut
  wurde (die statische Kopie ist veraltet), **Nein**, wenn sie aktuell ist, **Nicht im Build.**, wenn die letzte
  Veröffentlichung keine Seite davon enthält.
- **Öffentliche Adresse:** die Adresse dieser Seite auf dem Ziel (seine `PublicURL` oder eigene Domain), in einem
  neuen Fenster.
- **Veröffentlichen** (mit `staticpublish/publish` des Ziels): startet den Veröffentlichungsauftrag des Kernels nur für
  diese Seite, einen inkrementellen Build ihres Knotens in den letzten Build, dann Commit, Tag und Push
  (`exp:static:publish <Ziel> --nodes=<Knoten>`). Es fragt „Bestätigen Sie, dass Sie es sind" wie Veröffentlichen in
  Setup > Statisches Veröffentlichen (`[ReauthGroup_system]`). Der Auftrag läuft im Hintergrund als der Benutzer; der
  Bereich verlinkt seine Seite in der Administration. Solange ein Auftrag des Ziels läuft, sagt der Bereich das und
  bietet kein Veröffentlichen an. Ist das statische Veröffentlichen ausgeschaltet, sagt der Bereich das.
- **Verlauf** öffnet den Verlauf des Ziels in der Administration, wo das Zurückrollen bleibt.
- **Noch kein Ziel:** „Noch kein Ziel."; Benutzer mit `staticpublish/setup` bekommen **Ziel anlegen** und **Anleitung
  lesen**.

Jeder Text des Bereichs ist der des Kernels (Setup > Statisches Veröffentlichen), die Seitenleiste bringt also nichts zu
übersetzen mit. Auf einem Kernel ohne statisches Veröffentlichen gibt es den Bereich nicht; auf einem Kernel, dessen
Veröffentlichungsauftrag noch keine einzelnen Knoten bauen kann, ist er nur lesend. Anleitung zum Teil des Kernels:
Hilfe > Statisches Veröffentlichen, „Der Bereich in der Website-Seitenleiste".

### Bereich Performance

Für Administratoren und Entwickler (`expwt/performance`) zeigt der Bereich „Performance", wie die Seite im Fenster
gerendert wurde, aus den Zahlen der Debug-Leiste des Kernels:

- **Was er zeigt:** die Renderzeit, die SQL-Anweisungen (wie viele und ihre Zeit), den Spitzenspeicher, den
  Content-View-Cache (aus dem Cache, erzeugt, aus), die Cache-Blöcke (wie viele aus dem Cache kamen, die langsamsten
  mit Template und Zeile) und die **Zeit je Layout-Block** in der Reihenfolge des Renderns, jeder nach seinem
  Block-Template benannt, mit einer Anzeige gegenüber dem langsamsten; ein Block in einem Container ist eingerückt.
- **Woher die Zahlen kommen:** vom Rendern der Seite selbst, an seinem Ende erfasst und in der Seite am Element der
  Seitenleiste mitgegeben, nur für Benutzer mit `expwt/performance`: kein Speicher, keine zusätzliche Anfrage und kein
  Byte für andere. Die eine Anfrage des Bereichs (`performance.load`) ergänzt die Zeit des Servers und wird ohne die
  Richtlinie abgelehnt.
- **Ohne Debug-Ausgabe** (ab Exponential 6.0.15): der Kernel erfasst die Zahlen ohne jede Debug-Ausgabe für die
  Benutzer von `site.ini [DebugSettings] CollectStatsPolicyList[]`, und expwt trägt dort `expwt/performance` ein. Auf
  einem älteren Kernel oder mit geleerter Einstellung erfasst der Kernel sie nur, solange die Debug-Ausgabe für den
  Benutzer an ist (`DebugByIP`, `DebugByUser`); sonst zeigt der Bereich Renderzeit und Speicher und sagt, warum. Die
  Zeit je Layout-Block braucht den Template-Baum des Kernels (`debugbar.ini [DebugBarSettings] RenderTree`, an, solange
  nicht ausgeschaltet).
- **Seiten aus einem Cache:** eine Seite aus dem rollenbezogenen Seiten-Cache, einem Proxy oder dem Verlauf des Browsers
  wurde nicht für diesen Aufruf gerendert. Der Bereich vergleicht die Zeit des Renderns mit dem Laden der Seite (nach der
  Uhr des Servers, was immer die des Geräts sagt) und sagt dann „Diese Seite kam aus einem Cache" mit der Zeit des
  Renderns, von dem seine Zahlen stammen. Velocity und Apache mit PHP-FPM liefern dieselben Zahlen.

## Felder und ihre Editoren

Der Bereich „Seite" bearbeitet jedes Feld mit dem Editor, den die Administration für seinen Datentyp hat; er zeigt nie
ein Feld in der Textform seiner Speicherung.

| Datentyp | Editor in der Seitenleiste |
|---|---|
| ezstring, eztext, ezinteger, ezfloat, ezprice, ezemail | ein Eingabefeld des Formulars (Text auch auf der Seite selbst) |
| ezboolean, ezselection, ezdate, ezdatetime, eztime | ein Kontrollkästchen, eine Auswahl, eine Datums- oder Zeiteingabe |
| ezurl | zwei Eingaben: die Adresse und der Text des Links |
| ezkeyword | eine Eingabe mit den Schlagwörtern, durch Kommas getrennt |
| ezxmltext | der Rich-Text-Editor der Installation (ezoe mit TinyMCE 8 oder exp_oe_tiptap) mit seiner Werkzeugleiste, eigenen Tags, Einbettungen und dem Durchsuchen nach Links und Objekten |
| eztags | die eztags-Auswahl (Vorschläge, Baum, Chips; ihre Grenzen für Teilbaum und Anzahl) |
| ezobjectrelation, ezobjectrelationlist | die Bearbeitung des Datentyps (Objekte durchsuchen) |
| ezimage, ezbinaryfile, ezmedia | das Hochladen und die Felder des Datentyps (Alternativtext ...) |
| xrowmetadata, ezgmaplocation, ezmatrix, alle anderen | die eigene Bearbeitungsvorlage des Datentyps |

Jedes Feld der zweiten Hälfte ist offen in seinem Editor, sobald der Bereich „Seite" aufgeht, bereit zum Tippen (F32):
keine Schaltfläche „Bearbeiten", jeder Editor wird geladen, wenn er sichtbar wird. Der Editor ist die Ansicht
`expwt/field/<Objekt>/<Version>/<Sprache>/<Feld>` in einem Rahmen, so hoch wie sein Inhalt: das `{attribute_edit_gui}`
des Kernels für dieses Feld, gespeichert mit denselben Schritten wie content/edit (Prüfung, die Eingabe des
Datentyps, seine eigenen Aktionen, der Name des Objekts). Gespeichert wird also, was die Administration für dieselbe
Eingabe speichert. Er zeigt den neuesten Entwurf des Benutzers in der Sprache, oder die veröffentlichte Seite, solange
es keinen gibt; das eine **Entwurf speichern** und **Veröffentlichen** des Bereichs speichert jeden Editor der Reihe
nach (das erste Speichern legt den Entwurf an, ein unveränderter Editor sendet nichts), dann die Felder des
Formulars. Nichts im Bereich scrollt seitwärts: der Inhalt eines Editors bricht in seiner Breite um (Bilder werden
kleiner, Tabellen und Code scrollen in ihrem eigenen Kasten, lange Wörter werden getrennt).

Die Felder einer anderen Kategorie als der Standardkategorie (content.ini `[ClassAttributeSettings] CategoryList`, die
Kategorie des Klassenattributs) stehen in einer eigenen Gruppe am Ende des Bereichs, geschlossen, bis man sie öffnet,
wie content/edit sie zeigt: **Meta** enthält die Metadaten der Seite (xrowmetadata: Titel, Beschreibung,
Schlüsselwörter, das Open-Graph-Bild, die Lizenz mit ihrer Auswahl, die Sitemap-Optionen) und was die Klasse sonst in
diese Kategorie legt. Ein Feld, das beim Bearbeiten auf der Seite angeklickt wird, öffnet seine Gruppe. Oben und unten
liegen die Gruppen hinter **Mehr**. Ein Feld, dessen Klassenattribut keinen Namen hat, zeigt den Namen seines
Datentyps. Textfelder haben keine eigene Längengrenze der Seitenleiste: gespeichert wird, was die Klasse erlaubt.

Der Rahmen des Editors sieht aus wie die Seitenleiste (ihre admin4-Tokens und Schaltflächen, in ihrem hellen oder
dunklen Modus); die eigene Zeile der Seitenleiste hat Entwurf speichern und Veröffentlichen dafür, und die Optionen des
Rich-Text-Editors, die keine Hauptaktion sind (Editor wechseln, Editor ausschalten), liegen unter **Mehr**.

**Auf der Seite bearbeiten** (gedrückt: die Akzentfüllung und ein Haken, wie jedes ausgewählte Bedienelement der
Seitenleiste) umrandet jedes Feld, das die Seite zeigt: Textfelder werden an Ort und Stelle bearbeitet; Rich Text,
Bilder und die anderen Felder öffnen mit einem Klick oder Enter ihren Editor, die Zeile des Feldes wird sichtbar
gemacht. Ein Feld wird über die Feldmarkierungen des Kernels gefunden (site.ini `[ContentFieldMarkerSettings]`, der
Handler von expwt: nur für Benutzer, die mit der Seitenleiste bearbeiten dürfen), ein Bild auch über den Pfad seiner
Datei, ein Text ohne Markierungen über seinen Wert. Eine Bilddatei, die auf das Bild der Seite gezogen wird, ersetzt es
im Entwurf, über das Hochladen des Bildfeldes selbst; sein Alternativtext steht in seinem Editor.

### Exponential Intelligence an den Feldern

Wo expai (Exponential Intelligence) für den Benutzer eingeschaltet ist, hat jedes Feld, das es schreiben kann, im
Bereich Seite eine Reihe von Aktionen darunter, markiert mit **KI**: die Feldaktionen des Bearbeitungsformulars der
Administration (expai.ini `[EditViewSettings] FieldActions[]`: **Schreiben** mit einer Anweisung, **Umschreiben** mit
einer optionalen, **Kürzen**, **Übersetzen** in eine gewählte Sprache, **Vorschlagen**, bei einem Schlagwortfeld seine
Schlagwörter), **Zusammenfassen** (eine kurze Zusammenfassung der Seite für ein Text- oder Rich-Text-Feld: Schreiben von
expai mit dieser Anweisung) und bei einem Bild sein **Alternativtext** (der Alternativtext von expai, wo ein Modell
verfügbar ist, das Bilder ansehen kann). Übersetzen beginnt mit der Sprache des Feldes selbst: der übliche Fall ist eine
neue Übersetzung, deren Felder noch den Text der Hauptsprache enthalten.

- **Wer:** `expwt/edit` und die eigene Policy von expai `expai/write`, auf einer Seite, die der Benutzer in dieser
  Sprache bearbeiten darf; expai aktiv im Siteaccess, in dem die Seitenleiste läuft (`[ExtensionSettings]
  ActiveAccessExtensions[]=expai` des Website-Siteaccess oder `ActiveExtensions[]`), dort eingeschaltet (`[AISettings]
  Enabled`, nicht in `DisabledSiteAccesses[]`), eingerichtet (ein Modell und sein Schlüssel) und mit eingeschalteten
  Aktionen im Bearbeitungsformular (`[EditViewSettings] Enabled`). Fehlt etwas davon, zeigt die Seitenleiste keine
  KI-Aktion und stellt keine Anfrage dafür. Die Limits, die Datenrichtlinie, die Modellwahl mit Ausweichmodell und das
  Nutzungsprotokoll von expai gelten genau wie in der Administration.
- **Nur in den Entwurf:** die Antwort ist ein Vorschlag unter dem Feld (Rich Text als sein Text); das Feld behält seinen
  Wert bis **Übernehmen**. Ein Feld des Formulars übernimmt ihn in seine Eingabe, als geändert markiert und mit dem
  Entwurf gespeichert (auch automatisch); ein Feld mit eigenem Editor (Rich Text, der Alternativtext des Bildes) bekommt
  ihn in den Entwurf des Benutzers geschrieben, und der Editor lädt neu. Es wird nichts veröffentlicht: Veröffentlichen
  ist der eigene Schritt des Benutzers, mit den Workflows und Freigaben der Installation. Eine Anfrage braucht einen
  Entwurf (expai markiert ihn als unterstützt): den neuesten des Benutzers in der Sprache, oder einen neuen.
- Aktionen: `ai.field` (fragen) und `ai.apply` (den Vorschlag in ein Feld mit eigenem Editor schreiben), beide per POST
  mit dem Formular-Token, beide `expwt/edit`.

## Policies

| Policy | Erlaubt |
|---|---|
| `expwt/use` | die Seitenleiste selbst, den Bereich Ich, das Lesen im Bereich Seite, das Setzen und Aufheben einer Prüfsperre (wo `state/assign` des Kernels es erlaubt) |
| `expwt/edit` | Bearbeiten im Entwurf, Vorschau, die Gerätevorschau, Veröffentlichen, weiche Sperren, die SEO-Prüfung und die Barrierefreiheitsprüfung |
| `expwt/create` | den Bereich Anlegen |
| `expwt/structure` | den Bereich Struktur |
| `expwt/admin` | Setup > Bereiche der Website-Seitenleiste (mit `settings/view` des Kernels, und `settings/edit`, um sie zu ändern); die Verwaltungsbereiche späterer Phasen |
| `expwt/layouts`, `expwt/templates`, `expwt/shop` | die Bereiche späterer Phasen (schon jetzt definiert) |
| `expwt/insights` | der Bereich „Einblicke": die Aufrufe der Seite und die Suchen der Website nach Wörtern ihres Titels, aus den eigenen Statistiken des Kernels |
| `expwt/performance` | der Bereich „Performance": wie die Seite gerendert wurde (Zeit, SQL, Caches, Layout-Blöcke); für Administratoren und Entwickler |

Die Policies von expwt entscheiden, was die Seitenleiste anbietet; jede Aktion prüft zusätzlich die Policy des Kernels
für das, was sie ändert (`content/edit` des Objekts, `content/create` unter dem Elternknoten, `content/hide`,
`content/remove` ...), über die eigenen Operationen des Kernels. „Bestätigen Sie Ihre Identität" fragt bei einer Aktion
genau dann, wenn die Administration bei der Ansicht fragt, für die sie steht (Veröffentlichen und Entfernen gehören zur
Auslösegruppe `content`); die Seitenleiste öffnet die Frage in einem kleinen Fenster und macht weiter, sobald sie
beantwortet ist. Der echte Versand eines Newsletters (E3) fragt immer, in seiner eigenen Gruppe `newsletter` von
`[ReauthSettings] Groups[]` (die Einstellungen von expwt fügen sie eingeschaltet hinzu). Das kleine Fenster der Frage
zeigt keine Seitenleiste.

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

**Wohin sie gehören.** Die eigene `settings/expwt.ini` von expwt setzt nichts: Jede Einstellung steht dort
auskommentiert mit ihrem Standard, und die Standards stehen im Code. Der Grund ist die Reihenfolge, in der der Kernel
die Einstellungsdateien liest (die spätere gewinnt): `settings/`, die `ActiveAccessExtensions[]` des Siteaccess,
`extension/*/settings/siteaccess/<Siteaccess>/`, `settings/siteaccess/<Siteaccess>/`, die `ActiveExtensions[]` (die
zuerst genannte wird zuletzt gelesen), `settings/override/`. Ein Wert in den Einstellungen einer Erweiterung aus
`ActiveExtensions[]` gewinnt also gegen jeden Siteaccess. Deshalb:

- pro Siteaccess: `settings/siteaccess/<Siteaccess>/expwt.ini.append.php`;
- für alle Siteaccesses: `settings/override/expwt.ini.append.php` oder die `settings/expwt.ini.append.php` einer
  eigenen Einstellungs-Erweiterung in `ActiveExtensions[]` (die dann für das, was sie setzt, ebenfalls gegen jeden
  Siteaccess gewinnt);
- nie eine Zeile in `extension/expwt/settings/expwt.ini` einkommentieren, sondern sie in eine dieser Dateien kopieren.

`./console exp:ini where expwt.ini/ExpwtSettings/Enabled <Siteaccess>` listet jede Datei, die einen Wert setzt, in
dieser Reihenfolge, und den Wert, der gilt.

| Einstellung | Standard | |
|---|---|---|
| `[ExpwtSettings] Enabled` | disabled | die Seitenleiste in diesem Siteaccess |
| `ShowToAnonymous` | disabled | nicht angemeldete Besucher: disabled (gar nichts), enabled (der geschlossene Reiter für jeden Besucher), policy (nur wenn die Rolle Anonymous expwt/use gewährt; die Einrichtung gewährt sie nie); jeder andere Wert: disabled |
| `ShowInAdmin` | disabled | die Seitenleiste in den Siteaccesses der Administration (die von `[AdminSiteAccessMenu] AdminSiteAccessList[]`, sonst ein Administrationsdesign); disabled: dort gar nichts |
| `SiteAccessList[]`, `SiteAccessExcludeList[]` | (leer) | nur diese Siteaccesses (leer: alle), nie diese |
| `DataLayer` | rest | rest oder graphql |
| `InjectWithoutHook` | enabled | die Seitenleiste in Pagelayouts ohne Hook einsetzen |
| `DefaultEdge`, `DefaultAlign` | (leer), end | Rand beim ersten Besuch (left, right, top, bottom; leer: `DefaultSide`) und oben und unten das Ende der Leiste, an dem der Reiter sitzt (start: links, end: rechts) |
| `DefaultSide` | right | der Rand, wenn `DefaultEdge` leer ist (left oder right; die Einstellung aus der Zeit vor oben und unten) |
| `DefaultWidth`, `MinWidth`, `MaxWidth` | 380, 300, 720 | Breite des Bereichs links und rechts beim ersten Besuch, die Grenzen |
| `DefaultHeight`, `MinHeight`, `MaxHeight` | 280, 160, 640 | Höhe der Leiste oben und unten beim ersten Besuch, die Grenzen |
| `OverlayBelowWidth` | 768 | schmalere Fenster bekommen den Bereich über der Seite |
| `ShortcutKey` | E | der Buchstabe von Alt+Umschalt+<Taste>, der die Seitenleiste öffnet, für Benutzer ohne eigene Wahl (Bereich Ich) |
| `TabLabels` | icons | wie die Reiter für Benutzer ohne eigene Wahl (Bereich Ich) beschriftet sind: `icons`, `both` (Icons und Text) oder `text`, gleich in welcher Schreibung; jeder andere Wert gilt als `icons`. Setup > Bereiche der Website-Seitenleiste schreibt ihn für einen Siteaccess |
| `HeaderSelector` | header, [role=banner], #header, .header | der Kopf der Website, unter dem der Reiter und die obere Leiste sitzen |
| `FooterSelector` | footer, [role=contentinfo], Cookie-Hinweise | was die Website unten im Fenster festhalten kann; die untere Leiste sitzt über dem, was davon dort gerade fest steht |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | Klassen, die Anlegen nie anbietet |
| `[LockSettings] LockSeconds` | 300 | wie lange eine weiche Sperre ohne Lebenszeichen hält |
| `[DevicePreviewSettings] DeviceList[]` | iPhone 13, Pixel 7, Tablet, 1920 bei 200 % Zoom, Desktop | die Rahmen der Gerätevorschau, je `id;Name;Breite;Höhe` (CSS-Pixel, 240 bis 2560 breit, 240 bis 2400 hoch); der Name wird übersetzt, wo der Katalog der Seitenleiste ihn hat |
| `ShownDeviceList[]` | (leer) | die Rahmen, die beim ersten Öffnen der Vorschau in einem Browser gezeigt werden (IDs aus `DeviceList[]`); leer: alle. Danach merkt sich der Browser die Umschalter |
| `[RolePanelSettings] RoleList[]`, `[RolePanels_<Rollen-ID>] PanelOrder[]`, `HiddenPanelList[]` | (nicht gesetzt) | die Bereiche je Rolle, eingestellt unter Setup > Bereiche der Website-Seitenleiste je Siteaccess (siehe „Bereiche je Rolle"); nie in den Einstellungen einer Erweiterung setzen, die dann für jeden Siteaccess entscheiden würden |
| `[BrandingSettings] Name`, `Logo` | (leer) | der Name der Seitenleiste anstelle von „Website-Seitenleiste" und ein Logo daneben, je Siteaccess (siehe „Branding je Siteaccess") |
| `AccentColor`, `AccentColorDark` | (leer) | der Akzent im hellen und dunklen Modus, `#rrggbb`, geprüft nach WCAG 2.2 AA (4,5:1 für den Text darauf, 3:1 gegen die Leiste); eine Farbe, die durchfällt, wird nicht verwendet, und Setup sagt warum; eine leere dunkle wird aus der hellen abgeleitet |
| `[Comments] HideNew` | disabled | enabled: Ein Kommentar (expservices.ini `[Community] CommentClass`, sonst `comment`), der zum ersten Mal veröffentlicht wird, wird gleich danach verborgen, wenn sein Ersteller ihn nicht freigeben darf (kein `content/hide` des Kommentars); der Bereich Seite listet ihn als wartend auf Moderation, **Freigeben** zeigt ihn. Das erledigt der Inhalts-Bearbeitungs-Handler von expwt (content.ini `[EditSettings] ExtensionDirectories[]=expwt`), den der Kernel in jeder Veröffentlichung aufruft (Web, Befehle, Cronjobs, Workflows), über das Verbergen des Kernels; kein Workflow einzurichten |

## Designs: die Bearbeitungsaktionen und die Seiten-Toolbar

Ein Site-Design bindet die Templates von expwt dort ein, wo ein ezwt-Design die von ezwt einband. Sie sind
designneutral (einfache Schaltflächen, die Klassen `expwt-actions*`, gezeichnet von `expwt-actions.css` aus ihren
Tokens `--expwt-act-*`, die ein Design auf `.expwt-actions` setzen kann, um sie an sein Aussehen anzupassen; dunkel,
wenn die Seite `data-theme`, `data-a4-theme` oder `data-bs-theme` auf `dark` gesetzt hat), senden die Aktionen des
Kernels und enthalten kein Inline-CSS und kein Inline-Skript:

| Include | Anstelle von | Was es zeichnet |
| --- | --- | --- |
| `design:parts/expwt_edit_actions.tpl` | `parts/website_toolbar_edit.tpl` | im Formular `content/edit` des Designs: **Zur Veröffentlichung schicken**, **Entwurf speichern**, **Entwurf speichern und beenden**, **Entwurf verwerfen** (fragt vorher, `expwt-actions.js`), **Versionen verwalten**, **Vorschau**, **Übersetzen von** (ein veröffentlichtes Objekt in mehr als einer Sprache). `{include uri='design:parts/expwt_edit_actions.tpl' expwt_versions=false()}` lässt die zweite Zeile weg |
| `design:parts/expwt_versionview_actions.tpl` | `parts/website_toolbar_versionview.tpl` | auf `content/versionview`: **Bearbeiten**, **Veröffentlichen**, **Versionen verwalten** (ein eigenes Formular) |
| `design:parts/expwt_page_toolbar.tpl` | `parts/website_toolbar.tpl` | nichts, wo die Seitenleiste an ist (die Seitenaktionen sind in ihr); wo sie aus ist (`Enabled`), **Bearbeiten** für einen Bearbeiter der Seite |

Für noch nicht umgestellte Designs beantwortet expwt auch die drei Namen von ezwt (`parts/website_toolbar.tpl`,
`website_toolbar_edit.tpl`, `website_toolbar_versionview.tpl`) mit diesen Includes, sodass ein Design eines Dritten,
das sie noch einbindet, seine Bearbeitungsschaltflächen behält, wenn ezwt nicht mehr da ist. Beide werden nie
zusammen installiert (Composer `replace`). Die Plug-in-Templates von ezwt in `parts/websitetoolbar/` (RSS, Push,
Timeline, Multiupload, Newsletter) werden nicht gezeichnet: ihre Aktionen sind in den Bereichen der Seitenleiste
oder in der Administration.

## Erweiterungen fügen der Seitenleiste etwas hinzu

Eine andere Erweiterung fügt der Seitenleiste Bereiche, Server-Aktionen, Markierungen auf der Seite und Suchquellen
in ihrer eigenen `settings/expwt.ini.append.php` hinzu (Entscheidung 32), ohne Änderung an expwt. Ein durchgearbeitetes
Beispiel, eine Erweiterung mit dem Bereich „Page cache", liegt in `tests/example/expwtexample` (die Tests prüfen ihre
Registrierung); kopieren, `expwtexample` überall umbenennen, aktivieren und die Autoloads neu erzeugen. Die
Einstellungen sind die des englischen Leitfadens (Abschnitt „Adding to the sidebar from another extension"):
`[RegistrySettings]` mit `PanelList[]`, `ActionList[]`, `HighlightList[]`, `SearchProviderList[]` und je Eintrag eine
Gruppe `[Panel_<id>]`, `[Action_<erweiterung>.<name>]`, `[Highlight_<id>]`, `[SearchProvider_<id>]`. Ein Bereich
nennt mit `Icon=<name>` sein Icon, eines der Bootstrap Icons des Kernels (`share/icons/bootstrap-icons/svg/<name>.svg`,
im Beispiel `speedometer`); ohne Icon, oder mit einem Namen, den der Kernel nicht hat (bei den Zurückweisungen
genannt), zeigt der Reiter das Puzzle-Symbol und bei der Wahl Symbole seinen Namen in der Blase.

- **Wer was sieht.** Ein Eintrag braucht seine expwt-Funktion und jede Kernel-Richtlinie aus `Policy[]`; ohne sie gibt
  es weder den Reiter noch die Aktion (403), und eine Markierung oder Suchquelle fällt mit ihrem Bereich oder ihrer
  Aktion weg. Die Seitenleiste gewährt nichts: die Klasse der Aktion prüft die Kernel-Richtlinie dessen, was sie liest
  oder ändert, wie die eigenen Aktionen der Seitenleiste. Ein Eintrag mit falschem Namen, ohne das Nötige oder mit
  einem Namen der Seitenleiste wird ausgelassen.
- **Bereiche.** Die Seitenleiste lädt das Skript des Bereichs (über den ezjscore-Packer), wenn der Bereich zum ersten
  Mal gezeigt wird; das Skript meldet ihn mit `expwtRegister('panel', '<id>', function (ctx, root) { ... })` an. `ctx`
  ist, was die eigenen Bereiche bekommen (`h`, `t`, `call`, `say`, `node`, `registry()` ...), `root` der Inhalt des
  Bereichs im Shadow DOM der Seitenleiste, wo ihre Klassen und Tokens (`--a4-*`, mit dunklen Werten) gelten; ein
  Stylesheet nutzt nur diese Tokens.
- **Aktionen** heißen `<erweiterung>.<name>` und werden von `run($action, $input, $gateway)` ihrer Klasse
  (`expwtActionHandler`) über die Datenschicht der Installation beantwortet (`expwt/api`, `/api/expwt/v1`, GraphQL),
  geprüft wie die eigenen (registriert 404, POST für ändernde 405, Richtlinien 403); ein `expwtApiError` der Klasse
  erreicht den Bereich mit Status und Meldung. `KernelView=<modul>/<ansicht>` lässt „Bestätigen Sie, dass Sie es sind"
  fragen, wo die Administration für diese Ansicht fragt.
- **Markierungen** sind umrandet, solange ihr Bereich gezeigt wird und die Seitenleiste offen ist, und per Tastatur
  erreichbar; ein Klick oder Enter schickt das Ereignis `expwt-pick` an den Inhalt des Bereichs (`detail.highlight`,
  `detail.element`).
- **Suchquellen** stehen in der Boot-Antwort für die Suche der Seitenleiste (Entscheidung 29).

## Für Entwickler

- Tests (ohne Datenbank): `php vendor/bin/phpunit extension/expwt/tests/phpunit/unit` im Wurzelverzeichnis der
  Installation.
- Texte: Jedes `t('...')` des JavaScripts und jede Ablehnung der Klassen steht in `expwtStrings::SOURCES` (Kontext
  `extension/expwt`) und in jedem Katalog unter `translations/`.
- Die admin4-Tokens in `expwt.css` sind eine erzeugte Kopie von `design/admin4/stylesheets/components.css`; ein Test
  vergleicht sie.

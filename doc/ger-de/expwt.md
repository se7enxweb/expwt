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
| Seite: Kommentare | `expwt/edit`; jede Aktion die Richtlinie des Kernels für den Kommentar (`content/hide`, `content/remove`) oder für die Seite (`content/create` eines Kommentars) | die Kommentare der Seite, an Ort und Stelle moderiert, unter den Feldern des Bereichs Seite. Die Kommentare sind Inhalte unter der Seite (die Klasse aus expservices.ini `[Community] CommentClass`, vorgegeben `comment`), daher sind die Inhaltsrichtlinien des Kernels die Richtlinien der Kommentare. Neueste zuerst, höchstens 50; verborgene Kommentare sind die, die auf Moderation warten, und erscheinen, markiert, nur für die, die sie einblenden dürfen (`content/hide` des Kommentars). **Freigeben** zeigt einen verborgenen Kommentar, **Verbergen** verbirgt einen sichtbaren (beides `content/hide`, über die Verbergen-Operation des Kernels wie Verbergen und Einblenden der Administration); **Spam ...** fragt im Bereich noch einmal und verschiebt den Kommentar in den Papierkorb (`content/remove`; in der Administration aus dem Papierkorb wiederherstellbar). **Antworten** schreibt einen Kommentar des Benutzers unter die Seite (Betreff „Re: " und der Betreff des Kommentars, der Name des Benutzers als Autor), veröffentlicht über die Veröffentlichung des Kernels, ein Freigabe-Workflow greift also; **Kommentar schreiben** tut dasselbe für die Seite selbst. „Bestätigen Sie, dass Sie es sind" fragt genau dort, wo die Administration beim Verbergen, Entfernen und Veröffentlichen fragt. Jede Moderation und Antwort wird protokolliert (`content.comment.approve`, `.hide`, `.spam`, `.reply`). Die Zahl der Kommentare, die auf Moderation warten, steht als Zähler auf dem Reiter des Bereichs Seite (aus dem Start der Seitenleiste, keine zusätzliche Anfrage; keiner bei 0) |
| Anlegen | `expwt/create` | eine neue Seite unter oder neben dieser: die Klassen, die Sie dort anlegen dürfen, ein Titel, dann im Bereich Seite ausfüllen und veröffentlichen |
| Struktur | `expwt/structure` | ausblenden und einblenden, verschieben und einen weiteren Ort hinzufügen (der Platz wird in einem Baum auf der Website gewählt), in den Papierkorb, die Seiten darunter per Ziehen oder mit den Pfeilschaltflächen sortieren |
| SEO | `expwt/edit` | die SEO-Prüfung der Seite, wie sie dargestellt wird: Titel und Beschreibung (Länge), Überschriften (eine Hauptüberschrift, keine Ebene übersprungen), die Alternativtexte der Bilder, die kanonische Adresse und robots, und ob jeder interne Link irgendwohin führt (der Server schlägt ihn nach, wie der Kernel ihn beantworten würde: eine Ansicht, ein URL-Alias oder eine Weiterleitung, ein Platzhalter, eine Datei des Speichers oder eines Designs; Links eines anderen Siteaccess werden nicht geprüft). Probleme zuerst; ein Befund öffnet das betroffene Feld im Bereich Seite (das Titel- oder Metadatenfeld, das Feld, zu dem ein Bild oder ein Link des Textes gehört) oder zeigt seine Stelle auf der Seite. Es wird nichts gespeichert |
| Websites | `expwt/use` | die Seite auf den anderen Websites der Installation und in ihren anderen Sprachen. Die Websites sind die des Website-Menüs des Kernels (der Globus in admin4: `[SiteAccessSettings] RelatedSiteAccessList[]`, keine Administration, nur wo sich der Benutzer anmelden darf); **Öffnen** führt über den Sprachumschalter des Kernels (`switchlanguage/to`, der die Adresse der Seite in den Sprachen dieser Website findet) zur Seite dort, mit der Seitenleiste wie zuvor (offen, im selben Bereich); **Nebeneinander** zeigt diese und die andere Seite nebeneinander (auf dem Telefon untereinander), jede für sich scrollbar. Die Übersetzungen: jede Sprache der Seite mit **Hier bearbeiten** (der Bereich Seite bearbeitet diese Übersetzung; eine Schaltfläche dort führt zur Sprache der Website zurück) und **Nebeneinander** (die Vorschau des Kernels), und die Sprachen, in die der Benutzer die Seite übersetzen darf, mit **Übersetzen** (der volle Editor) |
| Formulare | `expwt/edit` (die Einsendungen zusätzlich `infocollector/read` des Kernels) | die Formulare der Seite: die Seite selbst, wenn ihre Klasse Informationen sammelt (die Informationssammlung des Kernels: ein Kontaktformular, ein Lead-Formular ...), und die Formulare, auf die sie verweist oder die sie einbettet, jeweils mit der Anzahl ihrer Einsendungen. Mit `infocollector/read` (Gesammelte Informationen der Administration, die Policy, die dort die personenbezogenen Daten schützt) auch die letzten zehn Einsendungen (wann, wer oder Besucher, die ersten zwei Antworten): eine öffnet sich mit allen Antworten, wie `infocollector/view` des Kernels sie zeigt (und genauso protokolliert: welche Einsendung geöffnet wurde, nie ihre Werte); **CSV exportieren** ist der Export `infocollector/export` des Kernels für das Formular, **In der Administration** seine Liste dort. Ein Formular, das sensible Daten sammelt (collect.ini `CollectSensitiveData`), sagt das. Ohne die Policy: nur die Anzahlen, und die Seitenleiste verweigert eine Einsendung. Hier wird nichts geändert oder entfernt |
| Newsletter | `expwt/edit` und `newsletter/editorial` von cjw_newsletter (eine Ausgabe machen) oder `newsletter/send` (versenden); nur wo cjw_newsletter aktiv ist | der Newsletter der Seite. Auf einer Ausgabe: die Ausgabe. Auf jeder anderen Seite: die Ausgaben, die die Seite als Artikel enthalten, und **Eine Ausgabe aus dieser Seite machen**: eine Newsletter-Liste (die, unter denen der Benutzer eine Ausgabe anlegen darf, mit der Zahl ihrer Abonnenten), ein Titel (vorgegeben der der Seite) und eine optionale Einleitung ergeben eine neue, veröffentlichte Ausgabe unter der Liste, mit der Seite als Artikel, so wie die Artikelauswahl der Administration ihn übernimmt (Titel, Einleitung und „Weiterlesen", von der Artikelstatistik gezählt). Jede Ausgabe zeigt Liste, Status, Abonnenten, Freigabe und letzten Versand. Mit `newsletter/send`: **Test senden** an die angegebenen Adressen (vorgegeben die Testadressen der Liste, als Test markiert, höchstens so viele wie eine Testgruppe) und **An die Abonnenten senden ...**: der Bereich nennt Ausgabe, Liste und Zahl der Abonnenten und fragt noch einmal, dann fragt „Bestätigen Sie, dass Sie es sind" im kleinen Fenster, jedes Mal, wenn keine frische Bestätigung vorliegt (eigene Auslösegruppe `newsletter`, ausgeliefert eingeschaltet; ausgeschaltet versendet die Seitenleiste gar nicht und verweist auf die Administration). Der Versand ist der der Administration: die aktuelle Version, sofort, geprüft von Freigabe, Skin, Tracking und Drosselung des Newsletters wie auf der Versandseite; der Cronjob des Newsletters verschickt ihn. Eine Ausgabe, die gerade versendet wird, im Archiv liegt, ohne nötige Freigabe ist oder deren Liste keine Abonnenten hat, sagt, warum sie nicht versendet werden kann. Wo die Installation Newsletter-Mails in Dateien schreibt (cjw_newsletter.ini `TransportMethod*=file`), landen sie dort |
| URLs | `expwt/edit` (ein Alias zusätzlich `content/edit` der Seite, ein Platzhalter zusätzlich `content/urltranslator`) | die Adressen der Seite: ihre Adresse in jeder Sprache, ihre alten Adressen (sie leiten auf die aktuelle weiter), die ihr hinzugefügten Aliase (jeweils markiert, wenn er weiterleitet) und die URL-Platzhalter, die zu ihr führen, alle so, wie dieser Siteaccess sie beantwortet (ohne seinen Pfadpräfix). **Adressen, die Besucher nicht fanden**: die Adressen des Nicht-gefunden-Protokolls des Kernels (das Ende von `404.log`, ohne die Adressen der Besucher und ohne die Abfragen), deren letzter Teil der letzte Teil einer Adresse der Seite ist und die noch immer „nicht gefunden" antworten, die häufigsten zuerst, jede mit **Hierher weiterleiten**, das diese Adresse als weiterleitenden Alias hinzufügt. **Eine Adresse hinzufügen**, wie die URL-Aliase einer Seite in der Administration einen hinzufügen (`content/urlalias`): eine Adresse ab der Wurzel der Website, eine Sprache und ob sie auf die Adresse der Seite weiterleitet oder die Seite unter ihr zeigt (der Kernel bereinigt die Adresse wie dort und sagt das; eine vergebene Adresse wird abgelehnt). **Einen Platzhalter hinzufügen**, wie die URL-Platzhalter der Administration einen hinzufügen (`content/urlwildcards`): ein Adressmuster (ein `*` passt auf den Rest), die Adresse der Seite als Ziel, weiterleitend oder nicht. Hier wird nichts entfernt. Das Hinzufügen wird als `content.urlalias.change` protokolliert, wie in der Administration; „Bestätigen Sie, dass Sie es sind" fragt, wie es bei diesen Ansichten fragt |
| Einblicke | `expwt/insights` | nur lesend, aus den eigenen Statistiken des Kernels, ohne Tracking Dritter: die Aufrufe der Seite (der Aufrufzähler, den der Cronjob `updateviewcount` aus dem Zugriffsprotokoll füllt; ist er aus, sagt der Bereich das), die Suchen auf der Website nach Wörtern des Seitentitels (das Suchprotokoll des Kernels, mit Anzahl und Treffern) und dass der Kernel keine Herkunftsseiten erfasst |
| Posteingang | `expwt/use` (für das Freigeben auch die Richtlinie `collaboration` des Kernels) | die Freigaben des Veröffentlichungs-Workflows, die auf Sie warten (das Freigabe-Ereignis des Kernels): jede Seite mit Autor und letztem Kommentar, **Entwurf öffnen**, und für ihre Freigebenden ein Kommentar mit **Freigeben** oder **Ablehnen**, entschieden über die eigene Ansicht `collaboration/action` des Kernels, genau wie in der Administration (der Workflow veröffentlicht eine freigegebene Seite bei seinem nächsten Lauf; eine abgelehnte ist wieder ein Entwurf ihres Autors). Ihre Anzahl steht als Zähler auf dem geschlossenen Reiter und auf dem Reiter des Bereichs Daneben die anderen Benachrichtigungen, aus der Zusammenarbeit und den Workflows des Kernels (keine zusätzliche Mail): **Kommentare und Erwähnungen** (Einträge mit einem ungelesenen Kommentar eines anderen, „Erwähnt Sie", wo der letzte Sie als @Login nennt), **Ihre Seiten** (Ihre Seiten, die seit Ihrem letzten Blick freigegeben oder abgelehnt wurden, innerhalb von 30 Tagen) und **Geplante Aufgaben, die fehlgeschlagen sind** (Ihre Workflow-Prozesse, die innerhalb von 30 Tagen mit einem Fehler stehen blieben, mit dem vollständigen Editor der Seite). Kommentare und Ergebnisse sieht jeder, der am Eintrag beteiligt ist (auch ein Autor ohne die Richtlinie `collaboration`, wie in der ausgelieferten Rolle Editor). **Als gelesen markieren** tut, was das Öffnen des Eintrags in der Administration tut; **In der Administration öffnen** (mit der Richtlinie `collaboration`) öffnet ihn dort. Ihre Anzahl zusammen (jeder Eintrag einmal) steht als Zähler auf dem geschlossenen Reiter und dem Reiter des Bereichs, mit wenigen Abfragen gezählt und eine Minute behalten; Öffnen des Bereichs, Markieren als gelesen oder eine Entscheidung zählen sofort neu |
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
| `expwt/use` | die Seitenleiste selbst, den Bereich Ich, das Lesen im Bereich Seite |
| `expwt/edit` | Bearbeiten im Entwurf, Vorschau, Veröffentlichen, weiche Sperren, die SEO-Prüfung |
| `expwt/create` | den Bereich Anlegen |
| `expwt/structure` | den Bereich Struktur |
| `expwt/layouts`, `expwt/templates`, `expwt/shop`, `expwt/admin` | die Bereiche späterer Phasen (schon jetzt definiert) |
| `expwt/insights` | der Bereich „Einblicke": die Aufrufe der Seite und die Suchen der Website nach Wörtern ihres Titels, aus den eigenen Statistiken des Kernels |

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
| `HeaderSelector` | header, [role=banner], #header, .header | der Kopf der Website, unter dem der Reiter und die obere Leiste sitzen |
| `FooterSelector` | footer, [role=contentinfo], Cookie-Hinweise | was die Website unten im Fenster festhalten kann; die untere Leiste sitzt über dem, was davon dort gerade fest steht |
| `[CreateSettings] HiddenClassList[]` | common_ini_settings | Klassen, die Anlegen nie anbietet |
| `[LockSettings] LockSeconds` | 300 | wie lange eine weiche Sperre ohne Lebenszeichen hält |

## Erweiterungen fügen der Seitenleiste etwas hinzu

Eine andere Erweiterung fügt der Seitenleiste Bereiche, Server-Aktionen, Markierungen auf der Seite und Suchquellen
in ihrer eigenen `settings/expwt.ini.append.php` hinzu (Entscheidung 32), ohne Änderung an expwt. Ein durchgearbeitetes
Beispiel, eine Erweiterung mit dem Bereich „Page cache", liegt in `tests/example/expwtexample` (die Tests prüfen ihre
Registrierung); kopieren, `expwtexample` überall umbenennen, aktivieren und die Autoloads neu erzeugen. Die
Einstellungen sind die des englischen Leitfadens (Abschnitt „Adding to the sidebar from another extension"):
`[RegistrySettings]` mit `PanelList[]`, `ActionList[]`, `HighlightList[]`, `SearchProviderList[]` und je Eintrag eine
Gruppe `[Panel_<id>]`, `[Action_<erweiterung>.<name>]`, `[Highlight_<id>]`, `[SearchProvider_<id>]`.

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

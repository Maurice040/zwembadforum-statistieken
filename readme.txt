=== Zwembadforum Advertentie Statistieken ===
Contributors: zwembadforum
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.4

Meet impressies en kliks op advertenties op bbPress-topicpagina's.

== Functionaliteit ==

* Ondersteunt de huidige advertentieblokken met .banner-desktop en .banner-mobile.
* Ondersteunt toekomstige advertenties met .zf-managed-topic-ad.
* Registreert impressies zodra minimaal 50 procent van de advertentie zichtbaar is.
* Registreert per bezoeker, topic en bestemming maximaal een impressie per 30 minuten.
* Registreert advertentiekliks.
* Toont een zelfstandig overzicht onder Advertentie stats in wp-admin.
* Toont de totalen van de laatste 30 dagen op het WordPress-hoofddashboard.
* Slaat impressies alleen als dagtotaal per bestemming op.
* Slaat topic- en forumgegevens alleen op wanneer op de advertentie wordt geklikt.
* Verwijdert automatisch statistieken ouder dan 365 dagen.
* Houdt maximaal 100.000 statistiekregels aan en verwijdert zo nodig de oudste regels.

== Installatie ==

1. Upload het zipbestand via Plugins > Nieuwe plugin > Plugin uploaden.
2. Activeer Zwembadforum Advertentie Statistieken.
3. Schakel de oude WPCode-snippet voor advertentiemeting uit.
4. Open een forumtopic met een advertentie.
5. Bekijk Advertentie stats in wp-admin.

De statistiektabel blijft bij deactiveren behouden.

== Changelog ==

= 1.0.4 =
* Impressies worden niet meer per bekeken topic opgeslagen.
* Bestaande gedetailleerde impressies worden eenmalig samengevoegd tot dagtotalen.
* Het topicoverzicht toont alleen pagina's waarop daadwerkelijk is geklikt.

= 1.0.3 =
* Automatische dagelijkse database-opruiming toegevoegd.
* Bewaartermijn ingesteld op 365 dagen.
* Veiligheidslimiet ingesteld op 100.000 statistiekregels.

= 1.0.2 =
* Beperkt impressies tot maximaal een meting per bezoeker, topic en bestemming per 30 minuten.
* Kliks blijven altijd meetellen.

= 1.0.1 =
* Statistieken worden correct per topic en forum gegroepeerd.
* Forum- en topictitels zijn klikbaar in het beheeroverzicht.

= 1.0.0 =
* Eerste versie.

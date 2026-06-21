=== Zwembadforum Advertentie Statistieken ===
Contributors: zwembadforum
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.3.1

Meet impressies en kliks op advertenties en kliks op handtekeninglinks op bbPress-topicpagina's.

== Functionaliteit ==

* Ondersteunt de huidige advertentieblokken met .banner-desktop en .banner-mobile.
* Ondersteunt toekomstige advertenties met .zf-managed-topic-ad.
* Registreert impressies zodra minimaal 50 procent van de advertentie zichtbaar is.
* Registreert per bezoeker, topic en bestemming maximaal een impressie per 30 minuten.
* Registreert advertentiekliks.
* Registreert kliks op links in bbPress-handtekeningen als aparte plaatsing.
* Toont een zelfstandig overzicht onder Advertentie stats in wp-admin.
* Toont de totalen van de laatste 30 dagen op het WordPress-hoofddashboard.
* Splitst advertentiekliks uit naar desktop en mobiel.
* Toont de advertentie-CTR apart voor desktop en mobiel.
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

= 1.3.1 =
* Toont desktop-, mobiele en onbekende impressies als afzonderlijke totalen.
* Toont ook het aantal advertentiekliks waarvan het apparaattype onbekend is.
* Vervangt 0,00 procent door een streepje wanneer een apparaat geen geregistreerde impressies heeft.
* Schrijft de CTR-berekening voluit als kliks gedeeld door impressies.

= 1.3.0 =
* Voegt desktop-CTR en mobiele CTR toe aan het beheeroverzicht en dashboard.
* Toont per dag de impressies, kliks en CTR per apparaattype.
* Berekent apparaat-CTR alleen met impressies waarvan het apparaattype bekend is.
* Verzendt handtekeningkliks betrouwbaar tijdens het openen van de bestemmingspagina.

= 1.2.0 =
* Voegt klikmeting toe voor links in bbPress-handtekeningen.
* Houdt handtekeningkliks apart van advertentiekliks en advertentie-CTR.
* Toont de plaatsing, het apparaat, het forum en het topic bij iedere klikgroep.

= 1.1.0 =
* Voegt apparaatmeting toe voor desktop- en mobiele advertentiekliks.
* Toont apparaattypes in het beheeroverzicht en op het dashboard.
* Bestaande statistiekregels zonder apparaatlabel worden eenmalig op onbekend gezet.

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

# Zwembadforum Advertentie Statistieken

Zelfstandige WordPress-plugin voor het meten van impressies en kliks op de vaste advertentie en kliks op handtekeninglinks onder bbPress-topics op Zwembadforum.

## Wat deze plugin doet

- Meet advertentie-impressies zodra minimaal 50 procent van het blok zichtbaar is.
- Meet advertentiekliks op vaste topicadvertenties.
- Meet links in `.forum-signature` en gangbare bbPress-handtekeningcontainers als aparte plaatsing.
- Ondersteunt de bestaande selectors `.banner-desktop a`, `.banner-mobile a` en `.zf-managed-topic-ad a`.
- Beperkt impressies per bezoeker, topic en bestemming tot maximaal een keer per 30 minuten.
- Bewaart impressies compact als dagtotalen per bestemmings-URL.
- Bewaart topic- en forumdetails alleen bij echte advertentie- of handtekeningkliks.
- Houdt advertentie- en handtekeningkliks apart, zodat de advertentie-CTR zuiver blijft.
- Toont totalen en CTR in een eigen adminpagina.
- Toont de CTR apart voor desktop en mobiel, inclusief de gebruikte impressies en kliks.
- Toont een samenvatting op het WordPress-hoofddashboard.
- Ruimt oude statistieken automatisch op.

## Bestanden

- `zwembadforum-advertentie-statistieken.php`: de plugin zelf.
- `readme.txt`: WordPress-pluginmetadata en changelog.

## Releaseflow

Deze repo is ingericht om zipbestanden automatisch te bouwen via GitHub Actions.

### Nieuwe release maken

1. Werk de pluginversie bij in `zwembadforum-advertentie-statistieken.php` en `readme.txt`.
2. Commit en push naar `main`.
3. Maak een tag in de vorm `v1.3.0`.
4. Push de tag naar GitHub.
5. De workflow bouwt automatisch `zwembadforum-advertentie-statistieken-1.3.0.zip` en hangt die aan de GitHub Release.

### Handmatige build

De workflow kan ook handmatig gestart worden via `Actions > Build plugin release > Run workflow`.

## Installatie in WordPress

1. Download de zip uit de laatste GitHub Release.
2. Upload die via `Plugins > Nieuwe plugin > Plugin uploaden`.
3. Activeer de plugin.
4. Schakel oude WPCode-snippets voor advertentiemeting uit.

## Opmerking

De GitHub Actions workflow is al toegevoegd in deze repo, maar een echte release-asset verschijnt pas zodra er een tag is gepusht.

# Code Review: depa/sulu-block-grid

**Datum:** 2026-07-03
**Stand:** Commit `07ba9f6` (main)
**Methodik:** Drei unabhängige Review-Agents mit getrennten Zuständigkeiten (PHP/Architektur/CI, Sulu-XML-Konfiguration, Twig-Templates), Findings anschließend zentral am Code verifiziert. XML-Wohlgeformtheit und XInclude-Auflösung wurden mit `xmllint --xinclude` geprüft.

---

## Executive Summary

Das Bundle ist strukturell sauber (konsistente Namespaces, PSR-4, korrekte XInclude-Pfade, kein `|raw`/XSS in den Templates), hat aber **drei schwerwiegende Probleme**:

1. Das Template `block--grid-three-col.html.twig` ist **leer** – der Block ist in beiden Slots registriert, Redakteursinhalte verschwinden im Frontend stillschweigend.
2. Die CI kann die privaten VCS-Dependencies mangels Composer-Auth **nicht installieren** – beide Jobs schlagen fehl.
3. `SuluBlockGridBundle::getPath()` zeigt auf `src/`, die Ressourcen liegen aber im Paket-Root – ein latenter Bruch der Bundle-Konvention.

Dazu kommen mehrere Copy-Paste-Fehler zwischen den fast identischen Grid-Row-Varianten und eine CSS-Injection-Möglichkeit über frei editierbare `gap_*`-Felder.

**Findings gesamt:** 3 HIGH · 9 MEDIUM · 8 LOW · 6 INFO

---

## HIGH

### H1 — Leeres Template: `block--grid-three-col` rendert nichts
**Datei:** `Resources/views/includes/blocks/block--grid-three-col.html.twig:1`

Das gesamte Template besteht aus einer Kommentarzeile (`{# block--grid-three-col #}`). Die XML-Definition verlangt zwingend 3 Image-Cards (`minOccurs="3" maxOccurs="3"`), der Block ist in `_slots.yaml` unter `section` **und** `container` registriert und wird im Test als erwarteter Block geprüft. Redakteure pflegen also Inhalte, die im Frontend stillschweigend verschwinden.

**Fix:** Template analog zu `block--grid-three-col-snippet.html.twig` implementieren (Wrapper-Div, Loop über `content.blocks` mit Include und `view.blocks[loop.index0]`) — oder den Block aus `_slots.yaml` und den XMLs entfernen, solange er nicht implementiert ist.

### H2 — CI kann private VCS-Dependencies nicht installieren
**Datei:** `.github/workflows/ci.yml:26-27, 44-45`

`composer install` muss `depa/sulu-block-helper` und `depa/sulu-block-content` aus `github.com/depa-berlin/...` ziehen (`composer.json`). Das Paket ist `proprietary`, die Repos sind mit hoher Wahrscheinlichkeit privat. Der Workflow konfiguriert keinerlei Composer-Auth (kein `COMPOSER_AUTH`, kein `github-oauth`-Token) → `composer install` schlägt fehl, beide Jobs (PHPUnit, PHPStan) sind rot.

**Fix:** Vor `composer install` in beiden Jobs:
```yaml
- name: Configure Composer auth
  run: composer config -g github-oauth.github.com ${{ secrets.COMPOSER_GITHUB_TOKEN }}
```
mit einem PAT/App-Token mit Lesezugriff auf beide Repos.

### ~~H3 — `Bundle::getPath()` zeigt auf `src/`, Ressourcen liegen im Root~~ ✅ Erledigt
**Datei:** `src/SuluBlockGridBundle.php:9`

Die Klasse erbt von `Bundle` ohne `getPath()`-Override → `getPath()` liefert `.../sulu-block-grid/src`. Templates/Configs liegen aber unter `.../sulu-block-grid/Resources/...`. Der von TwigBundle automatisch registrierte Namespace `@SuluBlockGrid` zeigt damit auf das nicht existierende `src/Resources/views`; jede Komponente, die `$bundle->getPath().'/Resources/...'` nutzt, findet nichts. Das funktioniert nur, solange `AbstractBlockExtension` alle Pfade selbst über die Extension-Klasse auflöst — ein latenter Konventionsbruch.

**Fix:**
```php
public function getPath(): string
{
    return \dirname(__DIR__);
}
```

**Erledigt (2026-07-08, überholt statt gefixt):** Statt des vorgeschlagenen manuellen Overrides
wurde das ganze Bundle auf Symfonys `AbstractBundle` + flache Struktur migriert
(`Resources/` → `config/`, `templates/`); `getPath()` liefert dadurch automatisch den
Paket-Root. Verifiziert: `(new SuluBlockGridBundle())->getPath()` gibt den Repo-Root zurück.

---

## MEDIUM

### M1 — `visibleCondition="__parent.override_layout == true"` vermutlich falscher Scope
**Datei:** `Resources/config/blocks/block--css-grid.xml:59, 71, 83, 95, 107`

`override_layout` (Z. 48) liegt in derselben Section (`grid_col`) und damit im selben Daten-Scope wie die fünf `cols_*`-Properties — Sections sind in Sulu datentransparent. `__parent` zeigt auf den umgebenden Scope, wo `override_layout` nicht existiert; die Felder wären damit nie sichtbar. ⚠️ *Im Sulu-Admin verifizieren* — falls die Felder aktuell erscheinen, evaluiert die konkrete Sulu-Version die Bedingung anders; falls nicht, ist das die Ursache.

**Fix:** `visibleCondition="override_layout == true"` (ohne `__parent.`).

### M2 — Falsche CSS-Klasse + fehlendes `|default` im Snippet-Template
**Datei:** `Resources/views/includes/blocks/block--grid-three-col-snippet.html.twig:3`

`<div class="block--grid-three-col {{ content.attr_class }}">` — Copy-Paste: Klasse des Schwester-Blocks statt `block--grid-three-col-snippet`; beide Blöcke sind im CSS nicht unterscheidbar. Zudem einziges Template ohne `|default('')` auf `attr_class` → `RuntimeError` unter `strict_variables` bei Altbestands-Blöcken.

**Fix:** `class="block--grid-three-col-snippet {{ content.attr_class|default('') }}"`.

### M3 — CSS-Injection über `style`-Attribut (freie Texteingabe)
**Datei:** `Resources/views/includes/blocks/block--css-grid.html.twig:16-21` / `block--css-grid.xml:126-138`

`gap_row`/`gap_col` sind `text_line` (freie Redakteurseingabe) und werden unvalidiert ins `style`-Attribut verkettet. Twig-Autoescaping verhindert den Attribut-Ausbruch, aber beliebige CSS-Deklarationen sind injizierbar (z. B. `1rem; position:fixed; inset:0; z-index:9999` → Overlay/Defacement).

**Fix:** Whitelist-Validierung im Template, z. B. `{% if content.gap_row|default matches '/^\\d+(\\.\\d+)?(px|rem|em|%)$/' %}`; Ausgabe mit `|e('html_attr')`.

### M4 — Property-Zugriffe ohne `|default` in `block--css-grid.html.twig`
**Datei:** `Resources/views/includes/blocks/block--css-grid.html.twig:6-11, 16-17`

`content.override_layout`, alle `content.cols_*`, `content.gap_row`, `content.gap_col` werden direkt zugegriffen. Die `cols_*`-Properties sind per `visibleCondition` nur bedingt sichtbar und bei bestehenden Inhalten oft nicht im Datensatz → `RuntimeError` unter `strict_variables`.

**Fix:** durchgängig `|default` ergänzen, z. B. `{% if content.override_layout|default(false) %}`.

### M5 — Block-XMLs referenzieren Typen aus nicht deklarierten Paketen
**Dateien:** `block--css-grid.xml:31-36`, `block--grid-col.xml:35-38`, `block--grid-three-col.xml:24`, `block--grid-three-col-snippet.xml:24`, `block--grid-row.xml:24`, `block--grid-row-1col.xml:62-65`, `block--grid-row-2col.xml:63`

`composer.json` deklariert nur `depa/sulu-block-helper` und `depa/sulu-block-content`. Referenziert werden aber u. a. `block--image-card`, `block--feature-icon`, `block--card-icon`, `block--card-project`, `block--snippet-select`, `block--seminar-list`, `block--articles-block-list`, `block--articles-block-navi`, `block--contacts-item`. Seminar-/Artikel-/Kontakt-Blöcke wirken kundenprojektspezifisch — in einem wiederverwendbaren Bundle sind das potenziell tote `type ref`s, die beim Template-Laden im Consumer-Projekt fehlschlagen.

**Fix:** Refs auf Blöcke der deklarierten Dependencies beschränken; projektspezifische Typen ins Projekt-Override verlagern oder die liefernden Bundles in `composer.json` (`require`/`suggest`) + README deklarieren.

### M6 — Copy-Paste-Drift: Typ-Listen der Grid-Row-Varianten inkonsistent
**Dateien:** `block--grid-row-1col.xml`, `block--grid-row-2col.xml`, `block--grid-row-3col.xml`

- 2col: `col1_blocks` enthält `block--content-button-content` (Z. 57), `col2_blocks` nicht — Drift innerhalb derselben Datei.
- 2col hat `block--content-text` und `block--content-asset-container`, 1col/3col nicht; 1col hat exklusiv `block--content-button-grid`, `block--articles-block-navi`, `block--content-snippet`, dafür kein `block--image-card`.

Redakteure können denselben Inhaltstyp je nach Spaltenzahl mal wählen, mal nicht.

**Fix:** Gemeinsame Typ-Liste als XInclude-Fragment (z. B. `_fragments/col_block_types.xml`) und in allen `col*_blocks` identisch verwenden; bewusste Abweichungen dokumentieren.

### M7 — `_slots.yaml`: `block--css-grid` fehlt im Slot `container`
**Datei:** `Resources/config/blocks/_slots.yaml:11-18`

`section` listet alle 8 Blöcke, `container` nur 7 (ohne `block--css-grid`). Falls unbeabsichtigt, generiert `sulu:blocks:generate-slots` den CSS-Grid nie als Container-Typ.

**Fix:** ergänzen — oder Absicht per Kommentar dokumentieren.

### M8 — Ungebundene `@dev`-Constraints
**Datei:** `composer.json:9-10`

`"depa/sulu-block-helper": "@dev"` ist unbounded (`composer validate` warnt). Jeder Breaking Change wird ungefiltert gezogen — genau so einer hat bereits den Refactor `07ba9f6` erzwungen.

**Fix:** auf `dev-main` pinnen oder Tags/Branch-Aliase einführen (`^1.0@dev`).

### ~~M9 — Kritische Extension-Pfade ungetestet~~ ✅ Erledigt/verlagert
**Datei:** `tests/Unit/DependencyInjection/SuluBlockGridExtensionTest.php`

Getestet wird nur der `bundle_metadata`-Parameter nach `load()`. Ungetestet: `prepend()` (Kernstück — Registrierung der Twig-Pfade, ohne die die relativen Includes `includes/blocks/...` nicht funktionieren), `getAlias()` (hängt nach `07ba9f6` rein an der Klassennamens-Konvention) und `getContainerExtension()`.

**Fix:** drei Tests ergänzen: Alias-Assertion, `getContainerExtension()`-Instanz-Check, `prepend()`-Test gegen `$container->getExtensionConfig('twig')`.

**Erledigt/verlagert (2026-07-08):** Grid hat keine eigene Extension-Klasse mehr (AbstractBundle-Migration).
Die komplette `prepend()`/Twig-Pfad-Logik liegt zentral in `AbstractBlockBundle` (Repo `sulu-block-helper`)
und wird dort getestet (`testPrependRegistersTwigPathWhenTwigIsAvailable`,
`testTwigPathPointsToExistingDirectory`). `getAlias()`-Äquivalent (`getBlockAlias()`, aus dem
Bundle-Klassennamen abgeleitet) verifiziert: `sulu_block_grid.bundle_metadata` löst korrekt zu
`{"bundle":"SuluBlockGridBundle","package":"depa/sulu-block-grid",...}` auf. Grids eigene
`SuluBlockGridBundleTest.php` deckt weiterhin ab, dass die Block-Metadaten korrekt aus dem
eigenen `config/blocks/` geladen werden.

---

## LOW

| # | Datei | Problem | Fix |
|---|-------|---------|-----|
| L1 | `block--grid-row-{1,2,3}col.xml:19-24` | `attr_class` inline statt per Fragment definiert; dabei in alle drei Dateien kopierter Fehler: deutscher Text als `lang="en"`-Titel („CSS Klassen Row-Element") | Fragment `_fragments/attr_class.xml` inkludieren oder en-Titel korrigieren |
| L2 | `block--grid-row-2col.html.twig:3,13` | Abweichendes Klassen-Muster ggü. 1col/3col: rendert immer `class="…"` — leer oder mit führendem Leerzeichen (`class=" foo"`) | Muster aus 1col/3col übernehmen: Attribut nur rendern, wenn Wert vorhanden |
| L3 | `block--css-grid.html.twig:1` | Twig-Blockname `content_button_grid` — Copy-Paste aus einem Button-Grid-Template | umbenennen oder Wrapper entfernen (6 von 8 Templates haben keinen) |
| L4 | `block--grid-three-col-snippet.html.twig:1` | Tippfehler im Blocknamen: `grid_grid_three_col_snippet` | `grid_three_col_snippet` |
| L5 | `block--grid-col.xml:8-9` | Abgeschnittener Block-Titel „Col -" (en+de) — erscheint so in der Blocktyp-Auswahl | z. B. en „Column" / de „Spalte" |
| L6 | `block--grid-row.xml:16-18`, `block--css-grid.xml:19` | Fehlende Meta-Titel: `Sub Blocks` ohne de-Titel; `<block name="blocks">` im CSS-Grid ganz ohne `<meta>` | Titel ergänzen |
| L7 | `README.md:52` | Link auf `LICENSE` — Datei existiert nicht im Repo | LICENSE anlegen oder Link entfernen |
| L8 | Test `SuluBlockGridExtensionTest.php:81` | Block-Assertions decken nur 4 von 8 Blöcken ab (`assertContains`-Schleife); fehlende/überzählige Blöcke fallen nicht auf | `assertSame([...alle 8 sortiert...], $meta['blocks'])` |

---

## INFO

- **I1** — `phpstan/phpstan-symfony` ist in `require-dev`, aber nie aktiviert (kein `includes:` in `phpstan.neon`, kein `extension-installer`) — totes Gewicht.
- **I2** — `_fragments/attr_id.xml` und `_fragments/config_image.xml` werden von keinem Block-XML inkludiert (nur `attr_class.xml`). Entfernen oder als paketübergreifende Fragmente im README dokumentieren. In `config_image.xml` zudem Tippfehler „kennzeichen" → „kennzeichnen".
- **I3** — `sulu_block_preview()` wird in allen 7 aktiven Templates aufgerufen, ist aber in diesem Bundle nicht definiert — harte, undokumentierte Abhängigkeit ans Host-Projekt/Helper-Bundle. Im README als Anforderung dokumentieren.
- **I4** — README-Hierarchie widerspricht den XMLs: README behauptet `block--css-grid └── block--grid-col`, die Typenliste in `block--css-grid.xml:30-37` enthält `block--grid-col` nicht.
- **I5** — CI-Härtung: kein Composer-Cache, PHP 8.4 fehlt in der Matrix (trotz `"php": "^8.2"`), kein `composer validate --strict`. `phpunit.xml.dist` ohne `cacheDirectory`/`failOnRisky`/`failOnWarning`. `repositories`-Einträge sind nicht transitiv — Consumer müssen die VCS-Repos selbst deklarieren (im README erwähnen).
- **I6** — Die „Unit"-Tests sind faktisch Integrationstests: Sie benötigen die reale `AbstractBlockExtension` aus dem Vendor-Paket und die echten XML-Dateien.

---

## Positiv verifiziert

- Kein `|raw`-Filter, keine klassischen XSS-Vektoren in den Templates; Property-Namen aller aktiv genutzten Templates stimmen mit den XML-Definitionen überein.
- Alle 11 XML-Dateien wohlgeformt; alle XInclude-Relativpfade (`../_fragments/attr_class.xml`) lösen korrekt auf; keine doppelten Property-Namen.
- Für alle 8 Block-Keys existiert die per Konvention erwartete Twig-Datei (Ausreißer: das leere Template, siehe H1).
- Namespaces/Konventionen konsistent: `SuluBlockGridBundle` findet die Extension per Symfony-Konvention; Alias `sulu_block_grid` passt zum getesteten Parameter. Vendor-Präfix `depa/` durchgängig korrekt (Fix aus `c2ec2a2`/`706cbc6` vollständig).
- PSR-4-Autoloading passt zu den Datei-/Namespace-Pfaden; `composer validate` bis auf die `@dev`-Warnungen sauber.

---

## Empfohlene Reihenfolge der Behebung

1. **H1** (leeres Template — Datenverlust im Frontend) und **M2** (falsche CSS-Klasse) — kleine, risikofreie Fixes.
2. **H2** (CI-Auth) — sonst bleibt jede weitere Änderung ungeprüft.
3. **H3** (`getPath()`) + **M9** (`prepend()`-Test) zusammen — Konvention fixen und absichern.
4. **M1** im Admin verifizieren, dann Bedingung korrigieren; **M3/M4** im selben Template-Durchgang.
5. **M5–M8** als Aufräum-Paket (Typ-Listen-Fragment, Slots, Constraints).
6. LOW/INFO nach Gelegenheit.

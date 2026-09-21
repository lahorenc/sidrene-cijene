# Sidrene cijene

Samostalni PHP modul za **objavu cjenika i sidrenih cijena** (Zakon o nepoštenoj trgovačkoj praksi / transparentnost cijena). Stavke mogu biti proizvodi, usluge, materijali ili drugi sadržaj.

Ne koristi bazu podataka i **ne ovisi** o WordPressu, Joomli, ProcessWireu ili drugom CMS-u. Ugrađuje se kao mapa `/sc` u web root i radi kao zasebna aplikacija; na postojeću stranicu se ubacuje iframe embedom.

**Izdavač:** [Enc IT d.o.o.](https://www.enc-it.hr/) · verzija **1.0.0**

---

## Značajke

- Više cjenika (kataloga) po slugovima (`?catalog=main`, `?catalog=ordinacija`…)
- Kategorije i stavke s cijenom, sidrenom cijenom i datumom sidrenja
- Javni prikaz + XML / CSV / JSON izvoz
- Dnevna arhiva (XML+CSV) uz CLI ili HTTP cron
- Admin: TinyMCE, file manager, tema/boje, vlastiti CSS
- Responsivni **iframe embed** s automatskom visinom (`postMessage`)
- Podaci u JSON datotekama (zaključavanje + backup prije spremanja)
- Bez ugrađene početne lozinke — credentiali se kreiraju pri prvom ulasku u admin

---

## Zahtjevi

- PHP **7.4+** (testirano i na 8.x)
- ekstenzije `json`, `mbstring`, `session`
- zapisive mape: `data/`, `data/catalogs/`, `data/history/`, `data/archives/`, `data/backups/`, `uploads/`
- Apache s `.htaccess` **ili** Nginx pravila (vidi dolje)

---

## Brza instalacija

1. Kopiraj sadržaj repozitorija u web root kao **`/sc`** (npr. `https://domena.hr/sc/`).
2. Po potrebi kopiraj `config/local.php.example` → `config/local.php` i prilagodi `base_url`.
3. Osiguraj da su `data/` i `uploads/` zapisivi od PHP procesa (npr. `www-data` / `uadmin`).
4. Otvori `https://domena.hr/sc/admin.php`.
5. Pri prvom ulasku unesi naziv firme, OIB, admin korisničko ime i lozinku.
6. Napravi kategorije i stavke, spremi cjenik.
7. Kartica **Ugradnja** → kopiraj embed kod u CMS (Custom HTML / članak / stranicu).

Početna lozinka **nije** hardkodirana. Hash se sprema u `data/admin.json`.

Primjer `config/local.php`:

```php
<?php
declare(strict_types=1);

return [
    'base_url' => '/sc',
    'timezone' => 'Europe/Zagreb',
    'archive_days' => 30,
    'embed_origins' => ['https://www.domena.hr'],
];
```

---

## Glavne adrese

| Opis | URL |
|------|-----|
| Admin | `/sc/admin.php` |
| Javni cjenik | `/sc/cjenik.php?catalog=main` |
| XML | `/sc/cjenik.xml?catalog=main` ili `/sc/cjenik.php?catalog=main&format=xml` |
| CSV | `/sc/cjenik.csv?catalog=main` |
| JSON | `/sc/cjenik.php?catalog=main&format=json` |
| Arhiva | `/sc/arhiva-cjenika/?catalog=main` |

Svaki cjenik ima vlastiti slug (`catalog`). Isti parametar koristi se za XML/CSV/JSON i cron. Arhive su odvojene po slugovima u `data/archives/{slug}/`.

Opcionalno filtriranje kategorija u embedu:

```text
/sc/cjenik.php?catalog=main&categories=slug-1,slug-2
```

---

## Embed (ugradnja)

U adminu: **Ugradnja** → označi kategorije (opcionalno) → kopiraj „Responsivni embed kod“.

Nakon promjene širine cjenika, **ponovno kopiraj** embed kod.

Iframe šalje `postMessage` tipa `sc-cjenik-height` pa roditeljska stranica nema unutarnji scrollbar. Font, boje i vlastiti CSS dolaze iz Postavki unutar iframea — ne pišu se u embed HTML.

---

## Arhiva i cron

Preporučeno (CLI, bez tokena u URL-u):

```cron
45 6 * * * /usr/bin/php /var/www/html/sc/cli/archive.php main >/dev/null 2>&1
```

Ako hosting nema CLI cron, admin prikazuje HTTP URL s tokenom:

```text
/sc/cjenik.php?catalog=main&action=archive&token=...
```

Broj dana čuvanja arhive: `archive_days` u konfiguraciji (default 30).

---

## Nginx

Nginx ne čita `.htaccess`. U virtualni host dodaj:

```nginx
location ~ ^/sc/(data|config|includes)/ {
    deny all;
    return 404;
}
location = /sc/bootstrap.php {
    deny all;
    return 404;
}
location = /sc/cjenik.xml {
    rewrite ^ /sc/cjenik.php?format=xml&$args last;
}
location = /sc/cjenik.csv {
    rewrite ^ /sc/cjenik.php?format=csv&$args last;
}
location ~ ^/sc/arhiva-cjenika/?$ {
    rewrite ^ /sc/cjenik.php?view=archive&$args last;
}
location ~ ^/sc/arhiva-cjenika/([^/]+\.(xml|csv))$ {
    rewrite ^ /sc/cjenik.php?file=$1&$args last;
}
location ~ ^/sc/uploads/.*\.(php[0-9]?|phtml|phar)$ {
    deny all;
    return 404;
}
```

---

## Struktura podataka

Sve živi u `data/` (nije u gitu — samo `.htaccess` / `.gitkeep`):

| Datoteka / mapa | Sadržaj |
|-----------------|--------|
| `settings.json` | firma, tema, cron token, embed layout |
| `admin.json` | korisničko ime + hash lozinke |
| `catalogs/*.json` | cjenici |
| `history/*.jsonl` | povijest cijena |
| `archives/` | dnevne XML/CSV snimke |
| `backups/` | kopije prije spremanja |
| `audit.jsonl` | prijave i admin radnje |

JSON se piše preko privremene datoteke uz zaključavanje.

---

## Sigurnost

- Nemoj commitati `config/local.php`, `data/*` (osim `.htaccess`) ni `uploads` sadržaj
- Zaključaj `data/`, `config/`, `includes/` na web serveru
- Koristi HTTPS; admin session cookie je `HttpOnly` + `SameSite=Lax`
- Cron token drži tajnim; preferiraj CLI cron

---

## Razvoj / lokalno

```bash
# npr. PHP ugrađeni server iz mape projekta
php -S 127.0.0.1:8080 -t .
# zatim http://127.0.0.1:8080/admin.php
# (po potrebi u local.php: 'base_url' => '')
```

---

## Licenca

Copyright © Enc IT d.o.o.  
Kod je javno dostupan za uvid, evaluaciju i implementaciju uz navođenje izdavača.  
Za komercijalnu podršku i prilagodbe: [enc-it.hr](https://www.enc-it.hr/) · info@enc-it.hr

# ProxiTok (fork)

Un frontend alternativo y respetuoso con la privacidad para TikTok, inspirado en Nitter. **Fork de [pablouser1/ProxiTok](https://github.com/pablouser1/ProxiTok)**, devuelto a la vida en 2026.

Todas las peticiones a TikTok se hacen server-side, así que tu navegador nunca habla directamente con TikTok. Navega el feed For You, perfiles de usuario, tags, búsqueda y vídeos individuales, con feeds RSS y temas.

## Qué cambia en este fork

El upstream dejó de funcionar cuando TikTok endureció su firma anti-bot y blindó la entrega de vídeo. Además, varios endpoints que antes daban datos hoy responden vacíos. Este fork arregla el conjunto apoyándose en dos sidecars pequeños y cambiando cómo se resuelve cada pieza:

1. **API web (navegación) → [carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature)** vía el [fork de TikScraperPHP](https://github.com/darlopvil/TikScraperPHP). Firma real `X-Bogus` + `X-Gnarly`, sin el ChromeDriver muerto. Con esto van el FYP (`/recommend/item_list/`), los tags (`/challenge/item_list/`) y la búsqueda.

2. **Perfiles (info) → SSR `webapp.user-detail`.** El antiguo `/api/user/detail/` está muerto (200 vacío) y `/api/search/user/full/` está WAF-capado, así que `User::info()` lee la página `/@usuario` y extrae el `userInfo` rehidratado (esquema canónico camelCase, cero remapeo).

3. **Página propia del vídeo → API `/item/detail/`.** `Video::info()` ya no raspa el HTML (que pillaba el reto WAF intermitente, `STATE_DECODE_ERROR`): pide el detalle por API firmada y la reescritura de CDN se aplica sola sobre `itemInfo.itemStruct`.

4. **Feed de usuario + audio original → [ttdlp](https://github.com/darlopvil/ttdlp)** (sidecar yt-dlp). `/api/post/item_list/` devuelve 200 vacío (WAF-capado), así que la lista de vídeos de un perfil se obtiene con `yt-dlp --flat-playlist`. El mismo sidecar sirve la reproducción y descarga de vídeo (forzando H.264) y extrae el audio original.

5. **Reproducción / descarga de vídeo → ttdlp.** La mayoría de los `playAddr` se sirven desde hosts `*-webapp-prime.tiktok.com` que dan 403 a cualquier petición server-side; yt-dlp es lo único que resuelve un stream H.264 reproducible de forma fiable. Por eso el player y los enlaces de descarga van por `/video?id=` y `/dl?id=`. Imágenes y portadas siguen sirviéndose directas por `/stream` (CDNs abiertos).

6. **Whitelist de CDN** en `ProxyController` ampliada para incluir `tiktokcdn-eu.com` y otros hosts de media actuales.

Así que una instancia completa son **tres contenedores**: ProxiTok + el firmador carcabot + ttdlp (más Redis para la caché).

## Arquitectura

```
                         ┌─────────────────────┐
 navegador ──► ProxiTok ─┤ navegación (firma)  ├─► carcabot ──► TikTok (API web)
                         │                     │
                         │ vídeo / audio /     ├─► ttdlp ─────► yt-dlp ──► TikTok
                         │ lista de perfil     │
                         └─────────────────────┘
```

- **Navegación** (FYP, tags, búsqueda, info de perfil, detalle de vídeo): TikScraperPHP + carcabot.
- **Lista de vídeos de un perfil, reproducción, descarga y audio original**: ttdlp (yt-dlp).
- **Imágenes / portadas**: proxy directo `/stream`.

## Características

- Feed For You, feeds de usuario, tags, búsqueda, vídeos sueltos
- Reproducción + descarga de vídeo (con / sin marca de agua)
- Audio original reproducible en los feeds
- Feeds RSS para usuarios y tags (añade `/rss`)
- Temas

## Self-hosting

Levanta el [firmador carcabot](https://github.com/carcabot/tiktok-signature) y [ttdlp](https://github.com/darlopvil/ttdlp) en la misma red Docker, y luego construye esto desde fuente. Variables clave:

```
API_CHROMEDRIVER=http://tiktok-signer_app:8080   # reutilizada: URL base del firmador carcabot
API_DEVICE_ID=<device id de 19 dígitos>
API_CACHE=redis
REDIS_HOST=...  REDIS_PORT=6379
APP_URL=https://<tu-dominio>
```

El `composer.json` apunta la dependencia `pablouser1/tikscraper` al [fork de TikScraperPHP](https://github.com/darlopvil/TikScraperPHP) mediante un repositorio VCS. Como `dev-master` no se fija en un `composer.lock`, una reconstrucción que solo cambie el fork necesita `docker compose build --no-cache` para que Composer traiga el commit nuevo.

La URL base de ttdlp se resuelve por defecto a `http://ttdlp_app:8080` (configurable con `API_TTDLP`).

## Extensiones de redirección

Usa [LibRedirect](https://github.com/libredirect/libredirect) o [Redirector](https://github.com/einaregilsson/Redirector). Ejemplo de regla en Redirector:

```
Include pattern: (.*//.*)(tiktok.com)(.*)
Redirect to:     https://<tu-instancia>$3
Pattern type:    Regular Expression
```

## Estado / limitaciones conocidas

- ✅ Feed For You, tags, búsqueda
- ✅ Perfiles: info (SSR), lista de vídeos (ttdlp), stats y feeds RSS
- ✅ Reproducción y descarga de vídeo (con / sin marca de agua)
- ✅ Audio original en los feeds
- ✅ Página propia del vídeo (vía API `/item/detail/`)
- ⚠️ Los posts de fotos/carrusel del feed de un perfil intentan reproducirse como vídeo; los vídeos normales van todos
- ⚠️ El listado de un perfil grande tarda 1-2 s la primera carga (yt-dlp lista; luego cachea)

## Créditos

ProxiTok original de [Pablo Ferreiro](https://github.com/pablouser1) y colaboradores; librerías del upstream (Latte, bramus/router, Bulma/Bulmaswatch, phpdotenv). Este fork añade la capa de resurrección carcabot + yt-dlp y el rerouteo de perfiles/feed/audio.

## Licencia

AGPL-3.0-or-later (la misma que el upstream).
# ProxiTok (fork)

Un frontend alternativo y respetuoso con la privacidad para TikTok, inspirado en Nitter. **Fork de [pablouser1/ProxiTok](https://github.com/pablouser1/ProxiTok)**, devuelto a la vida en 2026.

Todas las peticiones a TikTok se hacen server-side, así que tu navegador nunca habla directamente con TikTok. Navega el feed For You, perfiles de usuario, tags y vídeos individuales, con feeds RSS y temas.

## Qué cambia en este fork

El upstream dejó de funcionar cuando TikTok endureció su firma anti-bot y blindó la entrega de vídeo. Este fork arregla las dos mitades del problema con dos sidecars pequeños:

1. **API web (navegación) → [carcabot/tiktok-signature](https://github.com/carcabot/tiktok-signature)** vía el [fork de TikScraperPHP](https://github.com/darlopvil/TikScraperPHP). Firma real `X-Bogus` + `X-Gnarly`, sin el ChromeDriver muerto.
2. **Reproducción y descarga de vídeo → [ttdlp](https://github.com/darlopvil/ttdlp)**, un sidecar yt-dlp. La mayoría de los `playAddr` de TikTok se sirven desde hosts `webapp-prime` que dan 403 a cualquier petición server-side; yt-dlp es lo único que resuelve un stream H.264 reproducible de forma fiable, así que el player y los enlaces de descarga van por ahí (`/video?id=`, `/dl?id=`). Imágenes, portadas y audio siguen sirviéndose directos por `/stream` (esos vienen de CDNs abiertos).
3. **Whitelist de CDN** en `ProxyController` ampliada para incluir `tiktokcdn-eu.com` y otros hosts de media actuales.

Así que una instancia completa son **tres contenedores**: ProxiTok + el firmador carcabot + ttdlp (más Redis para la caché).

## Características

- Feed For You, feeds de usuario, tags, vídeos sueltos
- Reproducción + descarga de vídeo (con / sin marca de agua)
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

El `composer.json` apunta la dependencia `pablouser1/tikscraper` al [fork de TikScraperPHP](https://github.com/darlopvil/TikScraperPHP) mediante un repositorio VCS.

## Extensiones de redirección

Usa [LibRedirect](https://github.com/libredirect/libredirect) o [Redirector](https://github.com/einaregilsson/Redirector). Ejemplo de regla en Redirector:

```
Include pattern: (.*//.*)(tiktok.com)(.*)
Redirect to:     https://<tu-instancia>$3
Pattern type:    Regular Expression
```

## Estado / limitaciones conocidas

- ✅ Feed For You, reproducción de vídeo, descargas
- ⚠️ Perfiles: en proceso de remapeo (`User::info` del caducado `/user/detail/` a `/search/user/full/`)
- ⚠️ La página propia de un vídeo (`/@user/video/id`) puede pillar el WAF de TikTok al raspar el HTML; el player inline del feed no se ve afectado

## Créditos

ProxiTok original de [Pablo Ferreiro](https://github.com/pablouser1) y colaboradores; librerías del upstream (Latte, bramus/router, Bulma/Bulmaswatch, phpdotenv). Este fork añade la capa de resurrección carcabot + yt-dlp.

## Licencia

AGPL-3.0-or-later (la misma que el upstream).
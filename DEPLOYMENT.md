# Despliegue de MisLucas

Esta configuración pertenece exclusivamente a `mislucas.listocasa.es`. Caddy publica los puertos 80 y 443, obtiene el certificado de Let's Encrypt y redirige HTTP a HTTPS automáticamente. Nginx y PHP permanecen dentro de la red privada de Docker.

## Primera instalación en el Droplet

Requisitos: Docker con el complemento Compose, DNS de `mislucas.listocasa.es` apuntando al Droplet y puertos TCP 80/443 (y UDP 443, opcional para HTTP/3) abiertos.

```bash
git clone https://github.com/yrojaseduca/mislucas.git /opt/mislucas
cd /opt/mislucas
cp .env.example .env
```

Edita `/opt/mislucas/.env` y define, como mínimo:

```dotenv
APP_NAME=MisLucas
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mislucas.listocasa.es
SESSION_SECURE_COOKIE=true
DB_PASSWORD=una-clave-larga-y-unica
DB_ROOT_PASSWORD=otra-clave-larga-y-unica
```

No copies un `.env` de otro proyecto. Genera la clave de Laravel y arranca el servicio:

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d db
docker compose -f docker-compose.prod.yml run --rm php php artisan key:generate
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T php php artisan app:make-superadmin yesica10_09@hotmail.com
```

El último comando crea la cuenta si no existe y muestra una contraseña temporal una sola vez.

## Actualizaciones

Haz antes una copia de seguridad o snapshot del Droplet. Después:

```bash
cd /opt/mislucas
git pull --ff-only origin main
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d --remove-orphans
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T php php artisan optimize
```

## Verificación

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 caddy nginx php
curl -I http://mislucas.listocasa.es
curl -I https://mislucas.listocasa.es
```

La primera respuesta debe redirigir a HTTPS y la segunda debe responder sin errores de certificado.

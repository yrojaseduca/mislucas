# Despliegue de MisLucas

Esta configuración pertenece exclusivamente a `mislucas.listocasa.es`. Caddy publica los puertos 80 y 443, obtiene el certificado de Let's Encrypt y redirige HTTP a HTTPS automáticamente. Nginx y PHP permanecen dentro de la red privada de Docker.

## Flujo habitual: local y DigitalOcean

GitHub almacena el código. Los cambios se preparan y se suben desde el ordenador local; el Droplet de DigitalOcean descarga después esa versión y reconstruye la aplicación.

### 1. Subir cambios desde el ordenador local

Ejecuta estos comandos desde la carpeta local del proyecto:

```bash
cd /ruta/local/misLucas
git status
git add .
git commit -m "Describe el cambio"
git push origin main
```

Comprueba que el `push` termina correctamente antes de actualizar el servidor.

### 2. Actualizar la aplicación en DigitalOcean

Conéctate al Droplet mediante SSH:

```bash
ssh root@IP_DE_TU_DROPLET
```

Después ejecuta estos comandos dentro del servidor:

```bash
cd /opt/mislucas
git pull --ff-only origin main
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d --remove-orphans
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T php php artisan optimize
```

Comprueba el estado del despliegue:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=100 caddy nginx php
```

No ejecutes `migrate:fresh` en DigitalOcean: eliminaría las tablas y los datos existentes.

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

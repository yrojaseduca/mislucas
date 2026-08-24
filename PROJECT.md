# MisLucas

Aplicación para finanzas compartidas de hogares y negocios. Distingue el gasto del espacio, quién lo pagó y qué importe corresponde a cada miembro. Todos los importes se almacenan como enteros en céntimos.

## Puesta en marcha

```bash
cp .env.example .env
make up
make migrate
```

La web queda en `http://localhost:8080`; Vite usa el puerto `5173`.

## Comandos

- `make up`, `make down`: entorno Docker.
- `make migrate`, `make seed`: base de datos.
- `make test`, `make lint`, `make build`: verificación.

Los controladores coordinan, los servicios contienen reglas de negocio y los repositorios el acceso a datos. La primera iteración incluye espacios, miembros, cuentas, categorías, movimientos, repartos y liquidaciones. Autenticación, invitaciones, presupuestos, recurrencias e inversiones son evoluciones posteriores.

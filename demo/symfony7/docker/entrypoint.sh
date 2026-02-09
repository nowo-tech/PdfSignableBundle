#!/bin/sh
set -e

# Asegurar que var/cache y var/log existen y son escribibles por www-data
# (necesario cuando el proyecto se monta como volumen con permisos del host)
mkdir -p var/cache var/log
chown -R www-data:www-data var 2>/dev/null || true
chmod -R 775 var 2>/dev/null || true

exec "$@"

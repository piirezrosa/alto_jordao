#!/usr/bin/env sh
# Backup do banco e dos arquivos enviados. Uso (na pasta do projeto):  sh docker/backup.sh
# Gera a pasta backups/AAAAMMDD-HHMM/ com banco.sql.gz e uploads.tar.gz. Guarde uma cópia FORA do servidor.
set -e
cd "$(dirname "$0")/.."
. ./.env
D="backups/$(date +%Y%m%d-%H%M)"
mkdir -p "$D"
docker compose exec -T db sh -c 'mariadb-dump -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" --single-transaction --routines "$MARIADB_DATABASE"' | gzip > "$D/banco.sql.gz"
docker compose exec -T web tar czf - -C /var/www/html img/produtos img/marcas img/devolucoes relatorios_seguros > "$D/uploads.tar.gz"
echo "Backup salvo em $D"

# Scripts do banco para o Docker

Tudo que for `.sql` nesta pasta roda sozinho na **primeira** criação do banco (ordem alfabética).

O repositório não traz a estrutura das tabelas, então exporte do seu XAMPP:

1. phpMyAdmin → banco `alto_jordao` → **Exportar** → método **Personalizado** → formato SQL.
2. Em "Opções de criação de objetos" mantenha as tabelas; **desmarque** "CREATE DATABASE".
3. Salve como `docker/db/01-schema.sql` (só estrutura) e, se quiser dados de teste, `02-dados.sql`.

Para recriar o banco do zero (apaga os dados): `docker compose down -v && docker compose up -d --build`.

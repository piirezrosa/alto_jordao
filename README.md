# Alto Jordão — E-commerce de Vestuário

Loja virtual de vestuário feita do zero em **PHP puro** (sem frameworks): da vitrine ao pagamento, pós-venda, logística e gestão financeira, com painel administrativo completo, relatórios exportáveis e implantação em Docker.

> Projeto de portfólio em desenvolvimento ativo, em ambiente de testes (sandbox de Mercado Pago, PagBank e Melhor Envio).

## Destaques técnicos

- **Regras de negócio no servidor.** Preço, cupom, desconto e frete são sempre recalculados no servidor (`loja_helpers.php` é a fonte única de verdade); o valor exibido no navegador é apenas conferido.
- **Pagamentos resilientes.** Mercado Pago como principal e PagBank como fallback automático; webhooks que reconsultam o gateway e fazem transições de status idempotentes (o mesmo aviso duas vezes não duplica cobrança nem baixa de estoque).
- **Frete com cotação real e plano B.** Melhor Envio com cache de sessão e tabela própria de fallback; o painel mostra o motivo exato quando a cotação falha.
- **Financeiro de verdade.** DRE com margem de contribuição, lucro líquido e ponto de equilíbrio; custo, taxa e frete são gravados como *snapshot* no momento da compra.
- **Relatórios exportáveis.** 11 relatórios em CSV (Excel) e em arquivo criptografado AES com verificação de integridade, com log de auditoria e dados de custo restritos ao superadmin.
- **Segurança.** Senhas com bcrypt, CSRF nos formulários, proteção contra robôs e limite por IP no contato, segredos fora do Git (`.env`), histórico do repositório limpo.
- **Implantação reproduzível.** Docker Compose para teste local e para produção, com HTTPS automático (Caddy), volumes persistentes e script de backup.

---

## Stack

- **Backend:** PHP 8+ (PDO), sem Laravel/Symfony
- **Banco:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3 e JavaScript puro
- **Senhas:** bcrypt
- **Relatórios criptografados:** AES-256-CBC (`.ajenc`)
- **Infraestrutura:** Docker, Docker Compose, Apache, Caddy

---

## Como rodar

### Opção 1 — Docker (recomendado)

Requer Docker com Docker Compose.

```bash
cp .env.docker.example .env        # preencha DB_ROOT_PASS e DB_PASS
docker compose up -d --build       # site em http://localhost:8080
docker compose exec web php criar_superadmin.php "Seu Nome" voce@email.com
```

- O banco é criado a partir de `docker/db/01-schema.sql` (**só estrutura**, sem dados).
- Scripts `.sql` em `docker/db/` rodam em ordem alfabética **apenas na primeira criação** do banco. Para recriar do zero: `docker compose down -v && docker compose up -d --build` (apaga os dados).
- Se quiser dados de demonstração, coloque um `02-dados-demo.sql` em `docker/db/` antes de criar o banco (ele é ignorado pelo Git). O arquivo precisa estar legível para o contêiner (`chmod 644`).
- Fotos de produtos, marcas, devoluções e relatórios ficam em volumes e sobrevivem a rebuilds.
- Cadastre produtos e categorias pelo painel administrativo.

### Opção 2 — XAMPP (desenvolvimento)

1. Coloque o projeto em `htdocs/alto_jordao/` e inicie Apache + MySQL.
2. Crie o banco `alto_jordao` e importe `docker/db/01-schema.sql`.
3. Copie `code/.env.example` para `code/.env` e preencha os valores.
4. Crie o administrador: `php code/criar_superadmin.php "Seu Nome" voce@email.com`.
5. Acesse `http://localhost/alto_jordao/code/index.php`.

O sistema também **cria e ajusta algumas tabelas e colunas sozinho** (schema autocorretivo), o que facilita atualizar uma base existente.

---

## Configuração (`.env`)

Segredos **nunca** vão para o Git: ficam em `.env` (ignorado pelo `.gitignore` e bloqueado no Apache). Modelos: `code/.env.example` (XAMPP) e `.env.docker.example` (Docker).

| Grupo | Variáveis |
|---|---|
| Banco | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (no Docker: `DB_ROOT_PASS`, `DB_NAME`, `DB_USER`, `DB_PASS`) |
| Site | `SITE_URL` (usada em redirects e webhooks), `WEB_PORT` e `DOMINIO` (Docker) |
| PagBank / PagSeguro | `PS_SANDBOX`, `PS_TOKEN`, `PS_PUBLIC_KEY` |
| Mercado Pago | `MP_SANDBOX`, `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY` |
| Melhor Envio | `MELHORENVIO_TOKEN`, `MELHORENVIO_SANDBOX`, `MELHORENVIO_EMAIL` |
| Alertas | `ALERTAS_CHAVE` (opcional, para rodar a análise por URL) |

Sem as chaves de um gateway, o pedido é registrado mas **o pagamento real não é processado**. Use sempre chaves de **sandbox** nos testes.

> Se algum token já foi exposto, **revogue-o no painel do provedor** e gere outro — apagar do Git não basta.

---

## Pagamentos

| Gateway | Papel |
|---|---|
| **Mercado Pago** | Principal |
| **PagSeguro / PagBank** | Fallback automático |

- Cartão é tokenizado no navegador; o servidor tenta o Mercado Pago e, se falhar, aciona o PagSeguro.
- **PIX** tem 5% de desconto (sobre os produtos, não sobre o frete), calculado no servidor.
- **Webhooks** (`webhook_mp.php`, `webhook_ps.php`) reconsultam o gateway antes de mudar o status do pedido, com transições idempotentes. Cadastre nos painéis dos gateways a URL pública do seu domínio.
- Cancelamento manual de pedido devolve estoque e cupom.

---

## Frete

- **Melhor Envio (cotação real):** `POST /api/v2/me/shipment/calculate`, sandbox ou produção via `.env`. Usa peso e dimensões por produto, com valores padrão configuráveis, e cache de sessão de 15 min.
- **Tabela própria (fallback):** preços por zona (mesmo estado / mesma região / demais) para PAC, SEDEX e Jadlog, editáveis em Configurações → Frete. Entra quando o Melhor Envio falha, se o fallback estiver ligado.
- Interruptores por transportadora, frete fixo e frete grátis por valor mínimo (zera apenas a opção mais barata).
- Diagnóstico do Melhor Envio no painel (token, CEP de origem, último resultado, erro por serviço) e botão "Testar conexão".

---

## Funcionalidades

**Cliente:** cadastro e login, categorias, coleções e marcas, busca, carrinho, favoritos, checkout (PIX, cartão, boleto) com frete por CEP (ViaCEP), acompanhamento de pedidos, avaliações, solicitação de devolução e **Central de ajuda com formulário de contato**.

**Administrador:** produtos (com peso e dimensões), categorias, marcas, coleções, estoque, pedidos, devoluções, cupons, logística, avaliações, **mensagens dos clientes**, logs e auditoria.

**Financeiro:** DRE completo — receita − devoluções − CMV − taxa do gateway − impostos − frete − embalagem = margem de contribuição − despesas fixas rateadas = lucro líquido, com ponto de equilíbrio. Despesas fixas em `admin_despesas.php`. Parâmetros fiscais e financeiros são **exclusivos do superadmin**.

**Alertas:** estoque crítico e outros avisos, **configuráveis por administrador**, com selo na barra lateral e **pop-up na tela** (o motivo, se for um alerta, ou "Você tem N alertas"), que cada admin pode desligar em Configurações de Alertas. Não depende de cron.

**Superadmin:** usuários e permissões granulares, configurações da plataforma, bloqueio de IPs/países, proteção contra força bruta e 2FA.

---

## Relatórios e exportação

Em **Admin → Exportar Relatórios** (`admin_exportar.php`): prévia na tela (até 100 linhas) e download em CSV, aberto direto no Excel ou Planilhas (UTF-8, separador `;`, números no formato brasileiro, proteção contra injeção de fórmulas).

| Grupo | Relatórios |
|---|---|
| Estoque e produtos | Estoque atual, reposição sugerida, produtos parados, ranking de produtos vendidos |
| Clientes, cupons e devoluções | Melhores clientes, novos cadastros, desempenho de cupons, devoluções e trocas, devoluções por motivo |
| Pedidos e financeiro | Pedidos do período, resumo financeiro (DRE) |

- **Acesso:** admin e superadmin exportam; colunas de custo, lucro e margem e o resumo financeiro aparecem **só para o superadmin**.
- **Criptografados:** todos os relatórios também podem ser gerados em arquivo `.ajenc` protegido por senha (AES) em `gerar_relatorio_criptografado.php` e abertos em `descriptografar_relatorio.php`, com verificação de integridade SHA-256.
- Cada exportação fica registrada em `logs_sistema`.
- Os relatórios ficam definidos em `relatorios_catalogo.php`; para criar outro, basta adicionar uma entrada ao catálogo.

---

## Estrutura

```
.
├── code/                                    # aplicação PHP
│   ├── index.php, produto.php, checkout.php, ajuda.php, ...   # loja
│   ├── admin_*.php, sidebar.php             # painel administrativo
│   ├── config.php, env.php                  # banco e leitura do .env
│   ├── loja_helpers.php                     # cupom, frete, totais (fonte única)
│   ├── frete_helpers.php, melhorenvio.php   # cotação de frete
│   ├── financeiro_helpers.php               # DRE e configurações financeiras
│   ├── pedido_helpers.php                   # transições de status
│   ├── alertas_helpers.php, api_alertas.php # motor de alertas e pop-up
│   ├── contato_helpers.php                  # formulário "Fale conosco"
│   ├── relatorios_catalogo.php              # catálogo de relatórios
│   ├── pagseguro.php, mercadopago.php, webhook_*.php
│   ├── Permissoes.php, CriptografiaAES.php
│   └── criar_superadmin.php                 # cria o 1º admin (só linha de comando)
├── docker/
│   ├── db/01-schema.sql                     # esquema do banco (só estrutura)
│   ├── caddy/Caddyfile                      # HTTPS automático
│   ├── php/                                 # php.ini e Apache para o contêiner
│   └── backup.sh                            # backup do banco e dos uploads
├── Dockerfile, docker-compose.yml, docker-compose.prod.yml
└── .env.docker.example
```

---

## Docker em produção

**HTTPS automático (Caddy + Let's Encrypt)**

1. No servidor, preencha o `.env` com `DOMINIO=loja.seudominio.com.br` e `SITE_URL=https://loja.seudominio.com.br`. Use chaves de produção e `PS_SANDBOX=false` só quando for vender de verdade.
2. Aponte o DNS do domínio para o IP do servidor e libere as portas 80 e 443.
3. `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`
4. Crie o administrador com `docker compose exec web php criar_superadmin.php "Seu Nome" voce@sualoja.com.br`.
5. Cadastre nos gateways as URLs de webhook com o seu domínio.

Em produção, **não** copie o `02-dados-demo.sql`: o banco deve nascer só com a estrutura.

**Backup:** `sh docker/backup.sh` gera `backups/AAAAMMDD-HHMM/` com o banco (`banco.sql.gz`) e os arquivos enviados (`uploads.tar.gz`). Agende no cron do servidor e guarde uma cópia fora dele. Restaurar o banco:

```bash
gunzip -c banco.sql.gz | docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"'
```

**Atualizar o site:** `git pull && docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build` (os dados não são tocados).

---

## Segurança

- Senhas com bcrypt; senhas antigas em texto puro são convertidas no primeiro login.
- Tokens CSRF em formulários de escrita; campo-isca e limite por IP no formulário de contato.
- Segredos fora do repositório; Apache bloqueia `.env`, `.sql`, `.log`, `.ajenc` e similares.
- Permissões por nível (cliente, admin, superadmin) e dados de custo restritos ao superadmin.
- Relatórios sensíveis podem ser exportados criptografados.

---

## Status

Em desenvolvimento ativo, em ambiente de testes.

**Ainda não implementado:** compra de etiquetas e rastreio pelo Melhor Envio; envio de e-mail automático pelo servidor (SMTP); credenciais reais de produção dos gateways.
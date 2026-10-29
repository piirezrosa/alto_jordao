# Alto Jordão — E-commerce de Vestuário

Plataforma de e-commerce de vestuário feita do zero em **PHP puro** (sem frameworks): da vitrine ao pagamento, pós-venda, logística e gestão financeira, com painel administrativo completo.

---

## Stack

- **Backend:** PHP 8+ (PDO), sem Laravel/Symfony
- **Banco:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3 e JavaScript puro
- **Senhas:** bcrypt (custo 12)
- **Relatórios internos:** AES-256-CBC (`.ajenc`)
- **Ambiente de desenvolvimento:** XAMPP (`htdocs/alto_jordao/code/`)

---

## Como rodar localmente

1. Coloque o projeto em `htdocs/alto_jordao/` e inicie Apache + MySQL no XAMPP.
2. Crie o banco `alto_jordao` e importe os dados de `code/migrations/massa_dados_producao.sql` (ou rode `popular_banco.php`).
3. Copie `code/.env.example` para `code/.env` e preencha os valores (veja abaixo).
4. Acesse `http://localhost/alto_jordao/code/index.php`.

As migrações em `code/migrations/` estão documentadas por data; o sistema também **cria/ajusta as colunas necessárias sozinho** (schema autocorretivo), então rodar as migrações é opcional em bases já existentes.

---

## Configuração (`.env`)

Segredos **nunca** vão para o Git: ficam em `code/.env` (ignorado pelo `.gitignore` e bloqueado no Apache por `code/.htaccess`). O modelo é o `.env.example`:

| Grupo | Variáveis |
|---|---|
| Banco | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` |
| Site | `SITE_URL` (usada em redirects e webhooks) |
| PagBank/PagSeguro | `PS_SANDBOX`, `PS_TOKEN`, `PS_PUBLIC_KEY` |
| Mercado Pago | `MP_SANDBOX`, `MP_ACCESS_TOKEN`, `MP_PUBLIC_KEY` |
| Melhor Envio | `MELHORENVIO_TOKEN`, `MELHORENVIO_SANDBOX`, `MELHORENVIO_EMAIL` |
| Alertas | `ALERTAS_CHAVE` (opcional, para rodar a análise por URL) |

> Se algum token já foi exposto, **revogue-o no painel do provedor** e gere outro — apagar do Git não basta.

---

## Pagamentos

| Gateway | Papel |
|---|---|
| **Mercado Pago** | Principal |
| **PagSeguro / PagBank** | Fallback automático |

- Cartão é tokenizado no navegador; o servidor tenta o MP e, se falhar, aciona o PagSeguro.
- **PIX** tem 5% de desconto (sobre os produtos, não sobre o frete), calculado no servidor.
- **Webhooks** (`webhook_mp.php`, `webhook_ps.php`) reconsultam o gateway antes de mudar o status do pedido, com transições idempotentes.
- Preços, cupom, desconto e frete são **sempre recalculados no servidor** (`loja_helpers.php` é a fonte única de verdade); o valor exibido no navegador é conferido no pagamento.

---

## Frete

- **Melhor Envio (cotação real):** `POST /api/v2/me/shipment/calculate`, sandbox ou produção via `.env`. Usa peso e dimensões por produto, com valores padrão configuráveis, e cache de sessão de 15 min.
- **Tabela própria (fallback):** preços por zona (mesmo estado / mesma região / demais) para PAC, SEDEX e Jadlog, editáveis em Configurações → Frete. Entra quando o Melhor Envio falha, se o fallback estiver ligado.
- Interruptores por transportadora (Correios, Jadlog, outras), frete fixo e frete grátis por valor mínimo (zera apenas a opção mais barata).
- O painel mostra um **diagnóstico** do Melhor Envio (token, CEP de origem, último resultado e erro por serviço) e o botão "Testar conexão".

---

## Funcionalidades

**Cliente:** cadastro e login, categorias/coleções/marcas, busca, carrinho, favoritos, checkout (PIX, cartão, boleto) com cálculo de frete por CEP (ViaCEP), acompanhamento de pedidos, avaliações e solicitação de devolução.

**Administrador:** produtos (com peso/dimensões), categorias, marcas, coleções, estoque, pedidos (cancelamento devolve estoque e cupom), devoluções, cupons, logística, logs e auditoria.

**Financeiro:** DRE completo — receita − devoluções − CMV − taxa do gateway − impostos − frete − embalagem = margem de contribuição − despesas fixas rateadas = lucro líquido, com ponto de equilíbrio. Despesas fixas em `admin_despesas.php`. Parâmetros fiscais/financeiros são **exclusivos do superadmin**. O custo, a taxa e o frete são gravados como *snapshot* no momento da compra.

**Alertas:** estoque crítico e outros avisos, **configuráveis por administrador**, com selo na barra lateral e aviso (toast) automático. Não depende de cron: a análise roda de forma controlada enquanto o painel é usado.

**Superadmin:** usuários e permissões granulares, configurações da plataforma, bloqueio de IPs/países, proteção contra força bruta e 2FA.

**Relatórios:** painel de relatórios e exportação criptografada (`.ajenc`), ambos baseados no mesmo cálculo de margem (`fin_resumo`).

---

## Estrutura

```
code/
├── index.php, produto.php, checkout.php, ...   # loja
├── admin_*.php, sidebar.php                    # painel administrativo
├── config.php, env.php                         # banco e leitura do .env
├── loja_helpers.php                            # cupom, frete, totais (fonte única)
├── frete_helpers.php, melhorenvio.php          # cotação de frete
├── financeiro_helpers.php                      # DRE e configurações financeiras
├── pedido_helpers.php                          # transições de status
├── alertas_helpers.php                         # motor de alertas
├── pagseguro.php, mercadopago.php, webhook_*.php
├── Permissoes.php, CriptografiaAES.php
└── migrations/                                 # scripts SQL datados
```

---

## Status

Em desenvolvimento ativo, em ambiente de testes (sandbox de Mercado Pago, PagBank e Melhor Envio).

**Ainda não implementado:** compra de etiquetas e rastreio pelo Melhor Envio; credenciais reais de produção dos gateways.

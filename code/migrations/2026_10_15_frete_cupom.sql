-- Colunas usadas pelo checkout (frete e cupom).
-- O código já cria o que faltar automaticamente (loja_garantir_schema), mas este
-- script serve para aplicar manualmente / documentar. Rode uma vez no phpMyAdmin.
-- Se alguma coluna já existir, o MySQL avisa — pode ignorar aquela linha.

ALTER TABLE pedidos ADD COLUMN frete          DECIMAL(10,2) NOT NULL DEFAULT 0;
ALTER TABLE pedidos ADD COLUMN cupom_codigo   VARCHAR(50)   NULL;
ALTER TABLE pedidos ADD COLUMN desconto_cupom DECIMAL(10,2) NOT NULL DEFAULT 0;

-- Frete cotado por CEP. O sistema cria isto sozinho; rode à mão só se o MySQL negar o ALTER.
ALTER TABLE pedidos ADD COLUMN frete_servico VARCHAR(30) NULL;
ALTER TABLE pedidos ADD COLUMN frete_prazo INT NULL;

-- Peso e medidas por produto (cotação de frete). O sistema cria sozinho; rode à mão só se o MySQL negar o ALTER.
ALTER TABLE produtos ADD COLUMN peso_kg DECIMAL(6,3) NULL;
ALTER TABLE produtos ADD COLUMN largura_cm DECIMAL(6,1) NULL;
ALTER TABLE produtos ADD COLUMN altura_cm DECIMAL(6,1) NULL;
ALTER TABLE produtos ADD COLUMN comprimento_cm DECIMAL(6,1) NULL;

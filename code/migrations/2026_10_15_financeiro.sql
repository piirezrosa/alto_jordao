-- Módulo financeiro (DRE realista). O sistema também cria isto sozinho (fin_garantir_schema);
-- rode manualmente só se o usuário do MySQL não tiver permissão de ALTER.
-- Se alguma coluna já existir, o MySQL avisa — ignore aquela linha.
ALTER TABLE pedidos ADD COLUMN gateway VARCHAR(10) NULL;
ALTER TABLE pedidos ADD COLUMN taxa_gateway DECIMAL(10,2) NULL;
ALTER TABLE pedidos ADD COLUMN imposto_valor DECIMAL(10,2) NULL;
ALTER TABLE pedidos ADD COLUMN frete_custo DECIMAL(10,2) NULL;
ALTER TABLE itens_pedido ADD COLUMN custo_unitario DECIMAL(10,2) NULL;

CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    descricao VARCHAR(120) NOT NULL,
    categoria VARCHAR(30) NOT NULL DEFAULT 'outros',
    valor DECIMAL(12,2) NOT NULL,
    recorrencia VARCHAR(10) NOT NULL DEFAULT 'mensal',
    data_inicio DATE NOT NULL,
    data_fim DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    observacao VARCHAR(255) NULL,
    criado_por INT NULL,
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

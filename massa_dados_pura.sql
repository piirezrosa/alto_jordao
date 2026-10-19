-- =========================================================
-- Script SQL Gerado em PHP Puro (Sem Dependências)
-- =========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Inserção de Usuários

-- Inserção de Produtos

-- Inserção de Pedidos e Itens
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (0, 0.00, 'cancelado', '2026-09-11 16:54:05');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 3, 168.29);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 3, 105.05);
UPDATE pedidos SET total = 820.02 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (1, 0.00, 'pendente', '2026-10-15 18:00:53');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 1, 129.51);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 157.3);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 85.54);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 2, 55.95);
UPDATE pedidos SET total = 727.09 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (0, 0.00, 'enviado', '2026-08-18 08:49:46');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 3, 120.52);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 88.75);
UPDATE pedidos SET total = 539.06 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (1, 0.00, 'enviado', '2026-08-20 06:10:23');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 1, 227.4);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 263.05);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 1, 271.94);
UPDATE pedidos SET total = 1025.44 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id,total, status, data_pedido) VALUES (0, 0.00, 'pendente', '2026-08-31 04:10:52');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 1, 41.68);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 1, 61);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 269.98);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 3, 172.9);
UPDATE pedidos SET total = 1161.34 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (0, 0.00, 'enviado', '2026-07-22 15:07:56');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 2, 202.86);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 3, 284.69);
UPDATE pedidos SET total = 1259.79 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (1, 0.00, 'enviado', '2026-08-18 19:50:14');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 1, 199.16);
UPDATE pedidos SET total = 199.16 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (0, 0.00, 'enviado', '2026-08-19 01:56:33');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 3, 211.02);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 1, 249.87);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 227.84);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 93.73);
UPDATE pedidos SET total = 1526.07 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (1, 0.00, 'pendente', '2026-08-31 19:32:16');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 3, 103.69);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 125.83);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 140.28);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 3, 296);
UPDATE pedidos SET total = 1731.29 WHERE id = LAST_INSERT_ID();
INSERT INTO pedidos (usuario_id, total, status, data_pedido) VALUES (1, 0.00, 'pago', '2026-08-25 18:07:32');
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 3, 284.08);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 0, 2, 201.68);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 3, 197.48);
INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), 1, 2, 244.28);
UPDATE pedidos SET total = 2336.6 WHERE id = LAST_INSERT_ID();

COMMIT;

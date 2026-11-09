-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 09/11/2026 às 12:56
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `alto_jordao`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `alertas`
--

CREATE TABLE `alertas` (
  `id` int(11) NOT NULL,
  `tipo` enum('produto_problematico','estoque_critico','pedido_parado','avaliacao_negativa','devolucao_alta','margem_baixa') NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `referencia_tipo` varchar(50) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `nivel` enum('info','aviso','critico') DEFAULT 'aviso',
  `lido` tinyint(1) DEFAULT 0,
  `lido_por` int(11) DEFAULT NULL,
  `lido_em` datetime DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `alerta_configuracoes`
--

CREATE TABLE `alerta_configuracoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `alerta_produto_problematico` tinyint(1) DEFAULT 1,
  `alerta_estoque_critico` tinyint(1) DEFAULT 1,
  `alerta_pedido_parado` tinyint(1) DEFAULT 1,
  `alerta_avaliacao_negativa` tinyint(1) DEFAULT 1,
  `alerta_devolucao_alta` tinyint(1) DEFAULT 1,
  `alerta_margem_baixa` tinyint(1) DEFAULT 0,
  `limiar_taxa_devolucao` decimal(5,2) DEFAULT 10.00,
  `limiar_nota_media` decimal(3,1) DEFAULT 3.0,
  `limiar_dias_pedido_parado` int(11) DEFAULT 7,
  `limiar_estoque_critico` int(11) DEFAULT 3,
  `limiar_margem_minima` decimal(5,2) DEFAULT 20.00,
  `notificar_email` tinyint(1) DEFAULT 0,
  `email_alternativo` varchar(150) DEFAULT NULL,
  `notificar_popup` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `pedido_id` int(11) DEFAULT NULL,
  `nota` tinyint(4) NOT NULL CHECK (`nota` between 1 and 5),
  `titulo` varchar(150) DEFAULT NULL,
  `comentario` text DEFAULT NULL,
  `status` enum('pendente','aprovado','reprovado') DEFAULT 'pendente',
  `data` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `campanhas`
--

CREATE TABLE `campanhas` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `descricao` text DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `campanhas_recorrentes`
--

CREATE TABLE `campanhas_recorrentes` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `tipo_data` enum('fixa','movel') DEFAULT 'fixa',
  `mes` tinyint(4) DEFAULT NULL,
  `dia` tinyint(4) DEFAULT NULL,
  `regra_movel` varchar(50) DEFAULT NULL,
  `dias_antecedencia` int(11) DEFAULT 0,
  `dias_duracao` int(11) DEFAULT 7,
  `desconto_tipo` enum('percentual','fixo') DEFAULT 'percentual',
  `desconto_valor` decimal(10,2) NOT NULL,
  `valor_minimo` decimal(10,2) DEFAULT 0.00,
  `limite_uso` int(11) DEFAULT NULL,
  `regra_produtos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`regra_produtos`)),
  `prefixo_cupom` varchar(20) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `ultima_geracao` year(4) DEFAULT NULL,
  `data_criacao` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `campanha_produtos`
--

CREATE TABLE `campanha_produtos` (
  `id` int(11) NOT NULL,
  `campanha_recorrente_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `cupom_id` int(11) DEFAULT NULL,
  `ano` year(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `pai_id` int(11) DEFAULT NULL,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `ordem` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `colecoes`
--

CREATE TABLE `colecoes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_em` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `cupons`
--

CREATE TABLE `cupons` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `tipo` enum('percentual','fixo') DEFAULT 'percentual',
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_minimo` decimal(10,2) DEFAULT 0.00,
  `limite_uso` int(11) DEFAULT NULL,
  `total_usado` int(11) DEFAULT 0,
  `data_inicio` date DEFAULT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `descricao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `despesas`
--

CREATE TABLE `despesas` (
  `id` int(11) NOT NULL,
  `descricao` varchar(120) NOT NULL,
  `categoria` varchar(30) NOT NULL DEFAULT 'outros',
  `valor` decimal(12,2) NOT NULL,
  `recorrencia` varchar(10) NOT NULL DEFAULT 'mensal',
  `data_inicio` date NOT NULL,
  `data_fim` date DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `observacao` varchar(255) DEFAULT NULL,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `devolucoes`
--

CREATE TABLE `devolucoes` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `motivo` varchar(200) NOT NULL,
  `detalhes` text DEFAULT NULL,
  `foto_defeito` varchar(255) DEFAULT NULL,
  `status` enum('pendente','aprovado','recusado','concluido') DEFAULT 'pendente',
  `resposta_admin` text DEFAULT NULL,
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_resolucao` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `enderecos`
--

CREATE TABLE `enderecos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `cep` varchar(10) DEFAULT NULL,
  `rua` varchar(200) DEFAULT NULL,
  `numero` varchar(10) DEFAULT NULL,
  `bairro` varchar(100) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `estado` varchar(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `estoque_movimentacoes`
--

CREATE TABLE `estoque_movimentacoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `variacao_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','saida','ajuste') NOT NULL,
  `quantidade` int(11) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `referencia_id` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `data` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `frete_configuracoes`
--

CREATE TABLE `frete_configuracoes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `transportadora` varchar(100) DEFAULT NULL,
  `regiao` varchar(100) DEFAULT NULL,
  `uf` varchar(2) DEFAULT NULL,
  `peso_min` decimal(8,3) DEFAULT 0.000,
  `peso_max` decimal(8,3) DEFAULT 999.000,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `prazo_dias` int(11) DEFAULT 7,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `itens_pedido`
--

CREATE TABLE `itens_pedido` (
  `id` int(11) NOT NULL,
  `pedido_id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `variacao_id` int(11) DEFAULT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `preco_unitario` decimal(10,2) NOT NULL,
  `custo_unitario` decimal(10,2) DEFAULT NULL,
  `variacoes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `acao` varchar(100) NOT NULL,
  `tabela` varchar(50) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `detalhes` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `data` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `marcas`
--

CREATE TABLE `marcas` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `mensagens_contato`
--

CREATE TABLE `mensagens_contato` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `nome` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `pedido_numero` varchar(30) DEFAULT NULL,
  `assunto` varchar(160) NOT NULL,
  `mensagem` text NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'nova',
  `data_criacao` datetime DEFAULT current_timestamp(),
  `respondida_em` datetime DEFAULT NULL,
  `respondida_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `pedidos`
--

CREATE TABLE `pedidos` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `status` enum('pendente','pago','em_separacao','enviado','entregue','cancelado') DEFAULT 'pendente',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) DEFAULT 0.00,
  `desconto` decimal(10,2) DEFAULT 0.00,
  `frete` decimal(10,2) DEFAULT 0.00,
  `cupom_id` int(11) DEFAULT NULL,
  `forma_pagamento` enum('pix','cartao','boleto') DEFAULT 'pix',
  `status_pagamento` enum('pendente','aprovado','recusado','estornado') DEFAULT 'pendente',
  `end_cep` varchar(10) DEFAULT NULL,
  `end_rua` varchar(200) DEFAULT NULL,
  `end_numero` varchar(10) DEFAULT NULL,
  `end_bairro` varchar(100) DEFAULT NULL,
  `end_cidade` varchar(100) DEFAULT NULL,
  `end_estado` varchar(2) DEFAULT NULL,
  `codigo_rastreio` varchar(100) DEFAULT NULL,
  `transportadora` varchar(100) DEFAULT NULL,
  `data_pedido` datetime DEFAULT current_timestamp(),
  `data_pagamento` datetime DEFAULT NULL,
  `data_envio` datetime DEFAULT NULL,
  `data_entrega` datetime DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `cupom_codigo` varchar(50) DEFAULT NULL,
  `desconto_cupom` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gateway` varchar(10) DEFAULT NULL,
  `taxa_gateway` decimal(10,2) DEFAULT NULL,
  `imposto_valor` decimal(10,2) DEFAULT NULL,
  `frete_custo` decimal(10,2) DEFAULT NULL,
  `frete_servico` varchar(30) DEFAULT NULL,
  `frete_prazo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `permissoes`
--

CREATE TABLE `permissoes` (
  `id` int(11) NOT NULL,
  `codigo` varchar(80) NOT NULL,
  `descricao` varchar(200) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `critico` tinyint(1) DEFAULT 0 COMMENT 'Ações críticas exigem superadmin para liberar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produtos`
--

CREATE TABLE `produtos` (
  `id` int(11) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `preco` decimal(10,2) NOT NULL DEFAULT 0.00,
  `preco_antigo` decimal(10,2) DEFAULT NULL,
  `custo` decimal(10,2) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `colecao_id` int(11) DEFAULT NULL,
  `marca_id` int(11) DEFAULT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `genero` enum('masculino','feminino','unissex','kids') DEFAULT 'unissex',
  `tipo` varchar(100) DEFAULT NULL,
  `tamanho` varchar(200) DEFAULT NULL,
  `cor` varchar(200) DEFAULT NULL,
  `estoque` int(11) DEFAULT 0,
  `imagem` varchar(255) DEFAULT NULL,
  `destaque` tinyint(1) DEFAULT 0,
  `em_promocao` tinyint(1) DEFAULT 0,
  `ativo` tinyint(1) DEFAULT 1,
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `peso_kg` decimal(6,3) DEFAULT NULL,
  `largura_cm` decimal(6,1) DEFAULT NULL,
  `altura_cm` decimal(6,1) DEFAULT NULL,
  `comprimento_cm` decimal(6,1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_imagens`
--

CREATE TABLE `produto_imagens` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `arquivo` varchar(255) NOT NULL,
  `principal` tinyint(1) DEFAULT 0,
  `ordem` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `produto_variacoes`
--

CREATE TABLE `produto_variacoes` (
  `id` int(11) NOT NULL,
  `produto_id` int(11) NOT NULL,
  `tamanho` varchar(10) DEFAULT NULL,
  `cor` varchar(50) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `estoque` int(11) DEFAULT 0,
  `preco_extra` decimal(10,2) DEFAULT 0.00,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `relatorios_seguros`
--

CREATE TABLE `relatorios_seguros` (
  `id` int(11) NOT NULL,
  `nome` varchar(200) NOT NULL,
  `tipo` enum('financeiro_mensal','financeiro_anual','produtos_desempenho','clientes','margem_lucro') NOT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fim` date NOT NULL,
  `arquivo` varchar(255) NOT NULL COMMENT 'Caminho relativo do arquivo .ajenc no servidor',
  `tamanho_bytes` int(11) DEFAULT 0,
  `algoritmo` varchar(30) DEFAULT 'AES-256-CBC',
  `hash_integridade` varchar(64) NOT NULL COMMENT 'SHA-256 do conteúdo original — verifica adulteração',
  `gerado_por` int(11) DEFAULT NULL,
  `data_geracao` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL,
  `acessos` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` enum('cliente','operador','gerente','admin','superadmin') DEFAULT 'cliente',
  `status` enum('ativo','bloqueado') DEFAULT 'ativo',
  `cpf` varchar(20) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `permissoes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissoes`)),
  `data_cadastro` datetime DEFAULT current_timestamp(),
  `ultimo_acesso` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario_permissoes`
--

CREATE TABLE `usuario_permissoes` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `permissao_id` int(11) NOT NULL,
  `concedida` tinyint(1) DEFAULT 1 COMMENT '1 = concedida explicitamente | 0 = negada explicitamente',
  `concedida_por` int(11) DEFAULT NULL,
  `data_concessao` datetime DEFAULT current_timestamp(),
  `observacao` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lido_por` (`lido_por`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_lido` (`lido`),
  ADD KEY `idx_data` (`data_criacao`),
  ADD KEY `idx_referencia` (`referencia_tipo`,`referencia_id`),
  ADD KEY `idx_alertas_usuario` (`usuario_id`,`lido`);

--
-- Índices de tabela `alerta_configuracoes`
--
ALTER TABLE `alerta_configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_id` (`usuario_id`);

--
-- Índices de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_produto` (`produto_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `campanhas`
--
ALTER TABLE `campanhas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `campanhas_recorrentes`
--
ALTER TABLE `campanhas_recorrentes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `campanha_produtos`
--
ALTER TABLE `campanha_produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_camp_prod_ano` (`campanha_recorrente_id`,`produto_id`,`ano`),
  ADD KEY `produto_id` (`produto_id`),
  ADD KEY `cupom_id` (`cupom_id`);

--
-- Índices de tabela `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_pai` (`pai_id`);

--
-- Índices de tabela `colecoes`
--
ALTER TABLE `colecoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices de tabela `cupons`
--
ALTER TABLE `cupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_codigo` (`codigo`),
  ADD KEY `idx_ativo` (`ativo`);

--
-- Índices de tabela `despesas`
--
ALTER TABLE `despesas`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `devolucoes`
--
ALTER TABLE `devolucoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pedido_id` (`pedido_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `enderecos`
--
ALTER TABLE `enderecos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`);

--
-- Índices de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variacao_id` (`variacao_id`),
  ADD KEY `idx_produto` (`produto_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_data` (`data`);

--
-- Índices de tabela `frete_configuracoes`
--
ALTER TABLE `frete_configuracoes`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  ADD PRIMARY KEY (`id`),
  ADD KEY `variacao_id` (`variacao_id`),
  ADD KEY `idx_pedido` (`pedido_id`),
  ADD KEY `idx_produto` (`produto_id`);

--
-- Índices de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acao` (`acao`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_data` (`data`);

--
-- Índices de tabela `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `mensagens_contato`
--
ALTER TABLE `mensagens_contato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contato_status` (`status`,`data_criacao`);

--
-- Índices de tabela `pedidos`
--
ALTER TABLE `pedidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cupom_id` (`cupom_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_data` (`data_pedido`);

--
-- Índices de tabela `permissoes`
--
ALTER TABLE `permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `idx_modulo` (`modulo`);

--
-- Índices de tabela `produtos`
--
ALTER TABLE `produtos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `colecao_id` (`colecao_id`),
  ADD KEY `marca_id` (`marca_id`),
  ADD KEY `idx_categoria` (`categoria_id`),
  ADD KEY `idx_genero` (`genero`),
  ADD KEY `idx_destaque` (`destaque`),
  ADD KEY `idx_ativo` (`ativo`),
  ADD KEY `idx_estoque` (`estoque`);

--
-- Índices de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `produto_id` (`produto_id`);

--
-- Índices de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_produto` (`produto_id`),
  ADD KEY `idx_sku` (`sku`);

--
-- Índices de tabela `relatorios_seguros`
--
ALTER TABLE `relatorios_seguros`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gerado_por` (`gerado_por`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_data` (`data_geracao`);

--
-- Índices de tabela `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_nivel` (`nivel`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_perm` (`usuario_id`,`permissao_id`),
  ADD KEY `permissao_id` (`permissao_id`),
  ADD KEY `concedida_por` (`concedida_por`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alertas`
--
ALTER TABLE `alertas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `alerta_configuracoes`
--
ALTER TABLE `alerta_configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `campanhas`
--
ALTER TABLE `campanhas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `campanhas_recorrentes`
--
ALTER TABLE `campanhas_recorrentes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `campanha_produtos`
--
ALTER TABLE `campanha_produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `colecoes`
--
ALTER TABLE `colecoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT de tabela `cupons`
--
ALTER TABLE `cupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `despesas`
--
ALTER TABLE `despesas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `devolucoes`
--
ALTER TABLE `devolucoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `enderecos`
--
ALTER TABLE `enderecos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de tabela `frete_configuracoes`
--
ALTER TABLE `frete_configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `itens_pedido`
--
ALTER TABLE `itens_pedido`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT de tabela `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT de tabela `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `mensagens_contato`
--
ALTER TABLE `mensagens_contato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `pedidos`
--
ALTER TABLE `pedidos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de tabela `permissoes`
--
ALTER TABLE `permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de tabela `produtos`
--
ALTER TABLE `produtos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT de tabela `produto_imagens`
--
ALTER TABLE `produto_imagens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `relatorios_seguros`
--
ALTER TABLE `relatorios_seguros`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1987;

--
-- AUTO_INCREMENT de tabela `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `alertas_ibfk_1` FOREIGN KEY (`lido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `alerta_configuracoes`
--
ALTER TABLE `alerta_configuracoes`
  ADD CONSTRAINT `alerta_configuracoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `avaliacoes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `avaliacoes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `campanha_produtos`
--
ALTER TABLE `campanha_produtos`
  ADD CONSTRAINT `campanha_produtos_ibfk_1` FOREIGN KEY (`campanha_recorrente_id`) REFERENCES `campanhas_recorrentes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campanha_produtos_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `campanha_produtos_ibfk_3` FOREIGN KEY (`cupom_id`) REFERENCES `cupons` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `categorias_ibfk_1` FOREIGN KEY (`pai_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `devolucoes`
--
ALTER TABLE `devolucoes`
  ADD CONSTRAINT `devolucoes_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`),
  ADD CONSTRAINT `devolucoes_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Restrições para tabelas `enderecos`
--
ALTER TABLE `enderecos`
  ADD CONSTRAINT `enderecos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `estoque_movimentacoes`
--
ALTER TABLE `estoque_movimentacoes`
  ADD CONSTRAINT `estoque_movimentacoes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `estoque_movimentacoes_ibfk_2` FOREIGN KEY (`variacao_id`) REFERENCES `produto_variacoes` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `itens_pedido`
--
ALTER TABLE `itens_pedido`
  ADD CONSTRAINT `itens_pedido_ibfk_1` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `itens_pedido_ibfk_2` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`),
  ADD CONSTRAINT `itens_pedido_ibfk_3` FOREIGN KEY (`variacao_id`) REFERENCES `produto_variacoes` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `pedidos`
--
ALTER TABLE `pedidos`
  ADD CONSTRAINT `pedidos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `pedidos_ibfk_2` FOREIGN KEY (`cupom_id`) REFERENCES `cupons` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `produtos`
--
ALTER TABLE `produtos`
  ADD CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `produtos_ibfk_2` FOREIGN KEY (`colecao_id`) REFERENCES `colecoes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `produtos_ibfk_3` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `produto_imagens`
--
ALTER TABLE `produto_imagens`
  ADD CONSTRAINT `produto_imagens_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `produto_variacoes`
--
ALTER TABLE `produto_variacoes`
  ADD CONSTRAINT `produto_variacoes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `relatorios_seguros`
--
ALTER TABLE `relatorios_seguros`
  ADD CONSTRAINT `relatorios_seguros_ibfk_1` FOREIGN KEY (`gerado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `usuario_permissoes`
--
ALTER TABLE `usuario_permissoes`
  ADD CONSTRAINT `usuario_permissoes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_permissoes_ibfk_2` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usuario_permissoes_ibfk_3` FOREIGN KEY (`concedida_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

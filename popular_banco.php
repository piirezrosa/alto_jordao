<?php

/**
 * Script em PHP Puro para Geração de Massa de Dados SQL
 */

// Quantidade de registos
$totalUsuarios = 0;    // Reduzido para testar rápido (ajuste como quiser)
$totalProdutos = 0;
$totalPedidos  = 10;

$nomeFicheiro = 'massa_dados_pura.sql';
$handle = fopen($nomeFicheiro, 'w');

if (!$handle) {
    die("Erro ao criar o ficheiro SQL.\n");
}

// Cabeçalho SQL
fwrite($handle, "-- =========================================================\n");
fwrite($handle, "-- Script SQL Gerado em PHP Puro (Sem Dependências)\n");
fwrite($handle, "-- =========================================================\n\n");
fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($handle, "START TRANSACTION;\n\n");

// ---------------------------------------------------------
// DADOS BASE EM PORTUGUÊS (Para geração aleatória)
// ---------------------------------------------------------
$nomes = ['Lucas', 'Mateus', 'Gabriel', 'Ana', 'Maria', 'Beatriz', 'Carlos', 'Juliana', 'Fernanda', 'Rafael', 'Thiago', 'Patricia', 'Camila', 'Bruno', 'Rodrigo'];
$sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Ribeiro', 'Carvalho', 'Lopes', 'Soares'];
$dominiosEmail = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br'];

$categorias = ['Camiseta', 'Tênis', 'Calça', 'Jaqueta', 'Mochila', 'Relógio', 'Óculos', 'Boné', 'Carteira', 'Cinto'];
$estilos = ['Esportivo', 'Casual', 'Executivo', 'Impermeável', 'Em Couro', 'Urbano', 'Classic', 'Premium', 'Minimalista'];
$cores = ['Preto', 'Branco', 'Azul', 'Vermelho', 'Cinza', 'Verde', 'Marrom'];

$frasesCurtas = [
    'Produto de excelente qualidade para o dia a dia.',
    'Design moderno, elegante e confortável.',
    'Ideal para quem busca praticidade e estilo.',
    'Fabricado com materiais de alta resistência.'
];

// Hash de senha padrão (Password: 123456)
$senhaHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

// ---------------------------------------------------------
// FUNÇÕES AUXILIARES (PHP PURO)
// ---------------------------------------------------------
function itemAleatorio(array $arr) {
    return $arr[array_rand($arr)];
}

function gerarCpf() {
    return str_pad(rand(100000000, 999999999), 11, '0', STR_PAD_LEFT);
}

function gerarTelefone() {
    return '119' . rand(10000000, 99999999);
}

function gerarDataAleatoria($diasAtras = 365) {
    $timestampRandom = time() - rand(0, $diasAtras * 86400);
    return date('Y-m-d H:i:s', $timestampRandom);
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('[^-\w]+', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('-+', '-', $text);
    return strtolower(empty($text) ? 'n-a' : $text);
}

// ---------------------------------------------------------
// 1. GERAR USUÁRIOS
// ---------------------------------------------------------
echo "1. A gerar comandos SQL para Usuários...\n";
fwrite($handle, "-- Inserção de Usuários\n");

for ($i = 1; $i <= $totalUsuarios; $i++) {
    $nomeCompleto = itemAleatorio($nomes) . ' ' . itemAleatorio($sobrenomes);
    $nomeEscapado = addslashes($nomeCompleto);
    $primeiroNome = strtolower(explode(' ', $nomeCompleto)[0]);
    $email = $primeiroNome . $i . '@' . itemAleatorio($dominiosEmail);
    $cpf = gerarCpf();
    $telefone = gerarTelefone();
    $dataCadastro = gerarDataAleatoria(365);

    $sql = "INSERT INTO usuarios (nome, email, senha, nivel, status, cpf, telefone, data_cadastro) VALUES ('{$nomeEscapado}', '{$email}', '{$senhaHash}', 'cliente', 'ativo', '{$cpf}', '{$telefone}', '{$dataCadastro}');\n";
    fwrite($handle, $sql);
}

// ---------------------------------------------------------
// 2. GERAR PRODUTOS
// ---------------------------------------------------------
echo "2. A gerar comandos SQL para Produtos...\n";
fwrite($handle, "\n-- Inserção de Produtos\n");

for ($i = 1; $i <= $totalProdutos; $i++) {
    $nomeProduto = itemAleatorio($categorias) . ' ' . itemAleatorio($estilos) . ' ' . itemAleatorio($cores) . ' ' . $i;
    $nomeProdutoEscapado = addslashes($nomeProduto);
    $slug = slugify($nomeProduto);
    $descCurta = addslashes(itemAleatorio($frasesCurtas));
    $descricao = addslashes("Este item é fabricado com rigorosos padrões de qualidade. " . $descCurta);
    
    $preco = rand(5000, 60000) / 100; // Preço entre 50.00 e 600.00
    $custo = round($preco * 0.4, 2);
    $generos = ['masculino', 'feminino', 'unissex', 'kids'];
    $genero = itemAleatorio($generos);
    $estoque = rand(10, 100);
    $dataCadastro = gerarDataAleatoria(180);

    $sql = "INSERT INTO produtos (nome, slug, descricao_curta, descricao, preco, custo, genero, estoque, ativo, data_cadastro) VALUES ('{$nomeProdutoEscapado}', '{$slug}', '{$descCurta}', '{$descricao}', {$preco}, {$custo}, '{$genero}', {$estoque}, 1, '{$dataCadastro}');\n";
    fwrite($handle, $sql);
}

// ---------------------------------------------------------
// 3. GERAR PEDIDOS E ITENS DO PEDIDO
// ---------------------------------------------------------
echo "3. A gerar comandos SQL para Pedidos e Itens...\n";
fwrite($handle, "\n-- Inserção de Pedidos e Itens\n");

$statusOpcoes = ['pendente', 'pago', 'enviado', 'entregue', 'cancelado'];

for ($i = 1; $i <= $totalPedidos; $i++) {
    $usuarioId = rand(1, $totalUsuarios);
    $status = itemAleatorio($statusOpcoes);
    $dataPedido = gerarDataAleatoria(90);
    
    // Geramos primeiro uma estimativa ou valor base e inserimos o pedido
    $valorTotal = 0; 
    
    $sqlPedido = "INSERT INTO pedidos (usuario_id, valor_total, status, data_criacao) VALUES ({$usuarioId}, 0.00, '{$status}', '{$dataPedido}');\n";
    fwrite($handle, $sqlPedido);

    // Gerar de 1 a 4 itens para este pedido usando LAST_INSERT_ID()
    $qtdItens = rand(1, 4);
    $subtotalPedido = 0;

    for ($j = 1; $j <= $qtdItens; $j++) {
        $produtoId = rand(1, $totalProdutos);
        $quantidade = rand(1, 3);
        $precoUnitario = rand(3000, 30000) / 100; // R$ 30,00 a R$ 300,00
        
        $subtotalPedido += ($quantidade * $precoUnitario);

        $sqlItem = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (LAST_INSERT_ID(), {$produtoId}, {$quantidade}, {$precoUnitario});\n";
        fwrite($handle, $sqlItem);
    }

    // Atualiza o valor total real do pedido calculado pelos itens
    $sqlUpdateTotal = "UPDATE pedidos SET valor_total = {$subtotalPedido} WHERE id = LAST_INSERT_ID();\n";
    fwrite($handle, $sqlUpdateTotal);
}

// Finalização da transação
fwrite($handle, "\nCOMMIT;\n");
fclose($handle);

echo "\nConcluído com sucesso! O ficheiro '$nomeFicheiro' foi gerado na pasta atual.\n";
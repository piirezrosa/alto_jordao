<?php

require_once 'vendor/autoload.php';

// Inicializa o Faker em português do Brasil
$faker = Faker\Factory::create('pt_BR');

// Configurações de conexão com o banco de dados
$host = 'localhost';
$db   = 'alto_jordao';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Conexão estabelecida com sucesso!\n\n";

    // Definir a quantidade total de registros que deseja gerar (ex: 10.000)
    $totalRegistros = 10000;
    $tamanhoLote = 1000;

    // 1. Populando Usuários (Clientes) em massa
    echo "Iniciando a inserção de $totalRegistros usuários em lotes...\n";
    $stmtUser = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel, status, cpf, telefone, data_cadastro) VALUES (?, ?, ?, 'cliente', 'ativo', ?, ?, ?)");
    
    $pdo->beginTransaction();
    
    for ($i = 1; $i <= $totalRegistros; $i++) {
        $nome = $faker->name;
        $email = $faker->unique()->safeEmail;
        $senha = password_hash('12345678', PASSWORD_BCRYPT);
        $cpf = $faker->numerify('###########'); 
        $telefone = $faker->cellphoneNumber;
        $dataCadastro = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s');

        $stmtUser->execute([$nome, $email, $senha, $cpf, $telefone, $dataCadastro]);

        // A cada lote de 1000, faz o commit e abre uma nova transação
        if ($i % $tamanhoLote === 0) {
            $pdo->commit();
            echo "Inseridos $i usuários...\n";
            $pdo->beginTransaction();
        }
    }
    
    // Confirma o restante caso o total não seja exato do lote
    $pdo->commit();
    echo "Usuários populados com sucesso!\n\n";

    // 2. Populando Produtos em massa
    $totalProdutos = 500; // Exemplo de quantidade para produtos
    echo "Iniciando a inserção de $totalProdutos produtos...\n";
    
    $stmtProd = $pdo->prepare("INSERT INTO produtos (nome, slug, descricao_curta, descricao, preco, custo, genero, estoque, ativo, data_cadastro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
    
    $pdo->beginTransaction();

    for ($i = 1; $i <= $totalProdutos; $i++) {
        $nomeProduto = $faker->unique()->words(3, true) . " " . $i; // Garante unicidade
        $slug = slugify($nomeProduto);
        $descCurta = $faker->sentence(10);
        $descricao = $faker->paragraph(3);
        $preco = $faker->randomFloat(2, 50, 600);
        $custo = $preco * 0.4; 
        $genero = $faker->randomElement(['masculino', 'feminino', 'unissex', 'kids']);
        $estoque = $faker->numberBetween(10, 100);
        $dataCadastro = $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s');

        $stmtProd->execute([ucwords($nomeProduto), $slug, $descCurta, $descricao, $preco, $custo, $genero, $estoque, $dataCadastro]);

        if ($i % $tamanhoLote === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }

    $pdo->commit();
    echo "Produtos populados com sucesso!\n";
    echo "\nProcesso de população em massa concluído com sucesso!\n";

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Erro na execução (transação revertida): " . $e->getMessage() . "\n";
}

/**
 * Função auxiliar simples para gerar slugs
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}
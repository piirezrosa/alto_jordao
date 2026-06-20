<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Segurança: Bloqueia quem não é admin
if (!isset($_SESSION['usuario_nivel']) || $_SESSION['usuario_nivel'] !== 'admin') {
    header("Location: login.php"); exit();
}

// Atualização de estoque via POST
if(isset($_POST['atualizar_estoque'])) {
    $id = $_POST['id'];
    $novo_estoque = $_POST['quantidade'];
    $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ?")->execute([$novo_estoque, $id]);
    header("Location: admin_estoque.php?msg=atualizado"); exit();
}

// Busca produtos: Críticos primeiro (menor estoque)
$produtos = $pdo->query("SELECT id, nome, estoque, imagem FROM produtos ORDER BY estoque ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Estoque | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-width: 260px; }
        body { display: flex; background: #f8f9fa; margin: 0; font-family: 'Inter', sans-serif; }

        /* SIDEBAR PADRONIZADA SEM DASHBOARD */
        .admin-sidebar {
            width: var(--sidebar-width);
            background: #000;
            color: #fff;
            height: 100vh;
            position: fixed;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            z-index: 1000;
        }
        .sidebar-brand { font-weight: 900; font-size: 20px; letter-spacing: 2px; margin-bottom: 40px; text-align: center; }
        .sidebar-brand span { color: #555; display: block; font-size: 10px; }

        .nav-section { margin-bottom: 30px; }
        .nav-section-title { font-size: 10px; color: #444; font-weight: 800; text-transform: uppercase; margin-bottom: 15px; display: block; letter-spacing: 1px; }
        
        .nav-item {
            color: #888; text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 12px 15px; border-radius: 8px; display: block; transition: 0.3s; margin-bottom: 5px;
        }
        .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }

        /* CONTEÚDO */
        .admin-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; box-sizing: border-box; }
        
        h1 { font-weight: 900; letter-spacing: -1px; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }

        /* GRID DE ESTOQUE */
        .stock-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        
        .stock-card { 
            background: #fff; padding: 20px; border-radius: 15px; border: 1px solid #eee; 
            display: flex; align-items: center; gap: 15px; transition: 0.3s;
        }
        .stock-card:hover { border-color: #000; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }

        .low-stock { border-left: 4px solid #ff4d4d; background: #fffcfc; }

        .prod-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; background: #f0f0f0; }
        
        .prod-info h4 { margin: 0 0 8px 0; font-size: 14px; font-weight: 700; }

        .quick-form { display: flex; gap: 8px; align-items: center; }
        .stock-input { 
            width: 65px; padding: 8px; border: 1px solid #ddd; border-radius: 8px; 
            font-weight: 800; text-align: center; font-size: 14px; 
        }
        .btn-update { 
            background: #000; color: #fff; border: none; padding: 9px 15px; 
            border-radius: 8px; cursor: pointer; font-size: 10px; font-weight: 800; 
            text-transform: uppercase; transition: 0.2s;
        }
        .btn-update:hover { background: #333; }

        .msg-sucesso { background: #000; color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 800; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

    <aside class="admin-sidebar">
        <div class="sidebar-brand">ALTO JORDÃO <span>ADMIN PANEL</span></div>
        
        <div class="nav-section">
            <span class="nav-section-title">Financeiro</span>
            <a href="admin_vendas.php" class="nav-item">Controle de Vendas</a>
        </div>

        <div class="nav-section">
            <span class="nav-section-title">Catálogo</span>
            <a href="admin_produtos.php" class="nav-item">Controle de Produtos</a>
            <a href="admin_estoque.php" class="nav-item active">Gestão de Estoque</a>
            <a href="cadastrar_produto.php" class="nav-item">Novo Produto</a>
        </div>

        <div class="nav-section" style="margin-top: auto;">
            <a href="index.php" class="nav-item">Voltar para Loja</a>
            <a href="logout.php" class="nav-item" style="color: #ff4d4d;">Sair da Conta</a>
        </div>
    </aside>

    <main class="admin-content">
        <h1>Gestão de Estoque</h1>
        <p class="subtitle">Acompanhe e atualize as quantidades em tempo real.</p>

        <?php if(isset($_GET['msg'])): ?>
            <div class="msg-sucesso">✓ ESTOQUE ATUALIZADO</div>
        <?php endif; ?>

        <div class="stock-grid">
            <?php foreach($produtos as $p): 
                $critical = ($p['estoque'] <= 3); 
            ?>
            <div class="stock-card <?= $critical ? 'low-stock' : '' ?>">
                <img src="img/produtos/<?= $p['imagem'] ?>" class="prod-img">
                
                <div class="prod-info">
                    <h4><?= htmlspecialchars($p['nome']) ?></h4>
                    <form method="POST" class="quick-form">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <input type="number" name="quantidade" value="<?= $p['estoque'] ?>" class="stock-input">
                        <button type="submit" name="atualizar_estoque" class="btn-update">Salvar</button>
                    </form>
                    <?php if($critical): ?>
                        <small style="color: #ff4d4d; font-weight: 800; font-size: 9px; text-transform: uppercase; margin-top: 5px; display: block;">Urgente: Repor</small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

</body>
</html>
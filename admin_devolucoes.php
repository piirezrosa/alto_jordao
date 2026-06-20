<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Segurança: Bloqueia quem não é admin
if (!isset($_SESSION['usuario_nivel']) || !in_array($_SESSION['usuario_nivel'], ['admin','superadmin','gerente'])) {
    header("Location: login.php"); exit();
}

// Lógica de Processamento (Aprovar ou Recusar)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $novo_status = ($_GET['action'] === 'aprovar') ? 'aprovado' : 'recusado';
    
    $stmt = $pdo->prepare("UPDATE devolucoes SET status = ? WHERE id = ?");
    $stmt->execute([$novo_status, $id]);
    header("Location: admin_devolucoes.php?status_updated=1");
    exit;
}

// Busca todas as devoluções com os nomes dos clientes
$devolucoes = $pdo->query("SELECT d.*, u.nome as cliente FROM devolucoes d 
                           JOIN usuarios u ON d.usuario_id = u.id 
                           ORDER BY d.data_solicitacao DESC")->fetchAll(PDO::FETCH_ASSOC);

define('CONTEUDO_AUTORIZADO', true); // Define a constante para autorizar o conteúdo
$pagina_atual = 'devolucoes'; // Define a página ativa para a sidebar
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>SAC | Devoluções Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --sidebar-width: 260px; }
        body { display: flex; background: #f8f9fa; margin: 0; font-family: 'Inter', sans-serif; color: #1a1a1a; }

        /* SIDEBAR PADRONIZADA */
        .admin-sidebar {
            width: var(--sidebar-width); background: #000; color: #fff; height: 100vh;
            position: fixed; padding: 30px 20px; display: flex; flex-direction: column;
            box-sizing: border-box; z-index: 1000;
        }
        .sidebar-brand { font-weight: 900; font-size: 20px; letter-spacing: 2px; margin-bottom: 40px; text-align: center; }
        .sidebar-brand span { color: #555; display: block; font-size: 10px; letter-spacing: 1px; }

        .nav-section { margin-bottom: 30px; }
        .nav-section-title { font-size: 10px; color: #444; font-weight: 800; text-transform: uppercase; margin-bottom: 15px; display: block; letter-spacing: 1px; }
        
        .nav-item {
            color: #888; text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 12px 15px; border-radius: 8px; display: block; transition: 0.3s; margin-bottom: 5px;
        }
        .nav-item:hover, .nav-item.active { background: #1a1a1a; color: #fff; }

        /* CONTEÚDO */
        .admin-content { margin-left: var(--sidebar-width); flex: 1; padding: 40px; box-sizing: border-box; }
        h1 { font-weight: 900; letter-spacing: -1.5px; margin: 0 0 10px 0; text-transform: uppercase; }
        .subtitle { color: #888; margin-bottom: 40px; font-size: 14px; }

        /* CARDS DE DEVOLUÇÃO */
        .dev-card { 
            background: #fff; padding: 30px; border-radius: 20px; border: 1px solid #eee; 
            margin-bottom: 25px; display: flex; gap: 30px; align-items: flex-start;
            transition: 0.3s;
        }
        .dev-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }

        .dev-photo-wrapper { position: relative; }
        .dev-photo { 
            width: 200px; height: 200px; border-radius: 15px; object-fit: cover; 
            border: 1px solid #eee; cursor: zoom-in; transition: 0.3s; 
        }
        .dev-photo:hover { filter: brightness(0.8); }

        .dev-info { flex: 1; }
        
        .status-badge { 
            padding: 6px 12px; border-radius: 6px; font-size: 10px; font-weight: 900; 
            text-transform: uppercase; letter-spacing: 1px; 
        }
        .status-pendente { background: #fff8e1; color: #f57c00; }
        .status-aprovado { background: #e8f5e9; color: #2e7d32; }
        .status-recusado { background: #ffebee; color: #c62828; }

        .client-name { font-weight: 800; font-size: 18px; margin: 10px 0 5px 0; }
        .order-id { font-size: 12px; color: #bbb; font-weight: 700; text-transform: uppercase; }
        
        .reason-box { 
            background: #fbfbfb; padding: 15px; border-radius: 12px; border-left: 4px solid #000; 
            margin: 15px 0; font-size: 14px; line-height: 1.6;
        }

        .action-btns { margin-top: 25px; display: flex; gap: 25px; align-items: center; }
        .btn-ok { 
            color: #2e7d32; text-decoration: none; font-weight: 900; font-size: 11px; 
            letter-spacing: 1px; border-bottom: 2px solid transparent; transition: 0.3s;
        }
        .btn-ok:hover { border-bottom-color: #2e7d32; }
        
        .btn-no { 
            color: #d32f2f; text-decoration: none; font-weight: 900; font-size: 11px; 
            letter-spacing: 1px; border-bottom: 2px solid transparent; transition: 0.3s;
        }
        .btn-no:hover { border-bottom-color: #d32f2f; }
    </style>
</head>
<body>
    <?php require_once 'sidebar.php'; ?>
    
    <main class="admin-content">
        <h1>Análise de Devoluções</h1>
        <p class="subtitle">Gerencie solicitações de troca e analise evidências de defeitos.</p>

        <?php if(empty($devolucoes)): ?>
            <div style="text-align:center; padding: 100px; color: #ccc;">
                <p style="font-size: 40px;">✅</p>
                <p style="font-weight: 800; font-size: 12px; text-transform: uppercase;">Nenhuma pendência no momento</p>
            </div>
        <?php endif; ?>

        <?php foreach($devolucoes as $d): ?>
        <div class="dev-card">
            <div class="dev-photo-wrapper">
                <?php if($d['foto_defeito']): ?>
                    <a href="img/devolucoes/<?= $d['foto_defeito'] ?>" target="_blank">
                        <img src="img/devolucoes/<?= $d['foto_defeito'] ?>" class="dev-photo" title="Clique para ampliar">
                    </a>
                <?php else: ?>
                    <div class="dev-photo" style="display:flex; align-items:center; justify-content:center; background:#f5f5f5; color:#ccc; font-size:9px; text-align:center; padding: 20px; box-sizing: border-box;">CLIENTE NÃO ENVIOU FOTO</div>
                <?php endif; ?>
            </div>

            <div class="dev-info">
                <div style="display:flex; justify-content: space-between; align-items: center;">
                    <span class="order-id">Pedido #<?= $d['pedido_id'] ?></span>
                    <span class="status-badge status-<?= $d['status'] ?>"><?= $d['status'] ?></span>
                </div>
                
                <h3 class="client-name"><?= htmlspecialchars($d['cliente']) ?></h3>
                <small style="color:#bbb; font-weight: 700;"><?= date('d/m/Y \à\s H:i', strtotime($d['data_solicitacao'])) ?></small>

                <div class="reason-box">
                    <strong style="display:block; text-transform: uppercase; font-size: 10px; margin-bottom: 5px; color: #000;">Motivo: <?= htmlspecialchars($d['motivo']) ?></strong>
                    <?= nl2br(htmlspecialchars($d['detalhes'])) ?>
                </div>

                <?php if($d['status'] == 'pendente'): ?>
                    <div class="action-btns">
                        <a href="?action=aprovar&id=<?= $d['id'] ?>" class="btn-ok" onclick="return confirm('Autorizar esta devolução?')">✓ AUTORIZAR ESTORNO/TROCA</a>
                        <a href="?action=recusar&id=<?= $d['id'] ?>" class="btn-no" onclick="return confirm('Recusar esta solicitação?')">✕ RECUSAR SOLICITAÇÃO</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </main>

</body>
</html>
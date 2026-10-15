<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$stmt = $pdo->prepare("SELECT u.*, e.cep, e.rua, e.numero, e.bairro, e.cidade, e.estado 
                       FROM usuarios u
                       LEFT JOIN enderecos e ON u.id = e.usuario_id
                       WHERE u.id = :id");
$stmt->execute([':id' => $_SESSION['usuario_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Minha Conta | Alto Jordão</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

    <?php if (isset($_GET['sucesso'])): ?>
        <div id="alerta-sucesso" style="
            position: fixed; 
            top: 20px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 10000; 
            background: #000; 
            color: #fff; 
            padding: 15px 35px; 
            border-radius: 50px; 
            font-weight: 900; 
            text-transform: uppercase; 
            font-size: 12px; 
            letter-spacing: 1px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            pointer-events: none;
        ">
            <span style="font-size: 18px;">✅</span> Alterações salvas com sucesso!
        </div>
        <script>
            setTimeout(() => {
                const alerta = document.getElementById('alerta-sucesso');
                if(alerta) {
                    alerta.style.transition = 'opacity 0.6s ease';
                    alerta.style.opacity = '0';
                    setTimeout(() => alerta.remove(), 600);
                }
            }, 3000);
        </script>
    <?php endif; ?> 

    <div style="max-width: 1200px; margin: 20px auto; padding: 0 20px; text-align: center;">
        <a href="meus_pedidos.php" class="btn-black-capsule" style="text-decoration: none; display: inline-block; padding: 15px 40px;">
            📦 ACESSAR MEUS PEDIDOS
        </a>
    </div>

    <div class="user-full-wrapper"> 
        <main class="user-main-wide">

            <section id="perfil" class="user-content-section-wide">
                <h2>Meu Perfil</h2>
                <form action="usuario_atualizar.php" method="POST" class="profile-grid">
                    <div class="input-grupo span-2">
                        <label class="auth-label">NOME COMPLETO</label>
                        <input type="text" name="nome" class="auth-input" value="<?= htmlspecialchars($user['nome'] ?? '') ?>">
                    </div>
                    
                    <div class="input-grupo span-2">
                        <label class="auth-label">E-MAIL (Login)</label>
                        <input type="email" class="auth-input input-readonly" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                    </div>

                    <div class="input-grupo">
                        <label class="auth-label">CPF</label>
                        <input type="text" name="cpf" class="auth-input" value="<?= htmlspecialchars($user['cpf'] ?? '') ?>" placeholder="000.000.000-00">
                    </div>

                    <div class="input-grupo">
                        <label class="auth-label">TELEFONE</label>
                        <input type="text" name="telefone" class="auth-input" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-actions span-4">
                        <button type="submit" class="btn-black-capsule">SALVAR ALTERAÇÕES</button>
                    </div>
                </form>
            </section>

            <section id="enderecos" class="user-content-section-wide">
                <h2>Endereço de Entrega</h2>
                <form action="usuario_atualizar.php" method="POST" class="profile-grid">
                    <div class="input-grupo">
                        <label class="auth-label">CEP</label>
                        <input type="text" name="cep" class="auth-input" value="<?= htmlspecialchars($user['cep'] ?? '') ?>">
                    </div>

                    <div class="input-grupo span-3">
                        <label class="auth-label">ENDEREÇO / RUA</label>
                        <input type="text" name="rua" class="auth-input" value="<?= htmlspecialchars($user['rua'] ?? '') ?>">
                    </div>

                    <div class="input-grupo">
                        <label class="auth-label">NÚMERO</label>
                        <input type="text" name="numero" class="auth-input" value="<?= htmlspecialchars($user['numero'] ?? '') ?>">
                    </div>

                    <div class="input-grupo span-1">
                        <label class="auth-label">BAIRRO</label>
                        <input type="text" name="bairro" class="auth-input" value="<?= htmlspecialchars($user['bairro'] ?? '') ?>">
                    </div>

                    <div class="input-grupo span-1">
                        <label class="auth-label">CIDADE</label>
                        <input type="text" name="cidade" class="auth-input" value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
                    </div>

                    <div class="input-grupo">
                        <label class="auth-label">ESTADO (UF)</label>
                        <input type="text" name="estado" class="auth-input" value="<?= htmlspecialchars($user['estado'] ?? '') ?>" maxlength="2">
                    </div>

                    <div class="form-actions span-4">
                        <button type="submit" class="btn-black-capsule">ATUALIZAR ENDEREÇO</button>
                    </div>
                </form>
            </section>

            <section class="user-content-section-wide logout-section">
                <div class="logout-container" style="text-align: center;">
                    <p style="color: #888; text-transform: uppercase; letter-spacing: 1px; font-size: 13px; margin-bottom: 20px;">Deseja encerrar sua sessão?</p>
                    <a href="logout.php" class="btn-outline-red">SAIR DA CONTA</a>
                </div>
            </section>

        </main>
    </div>

    <script>
    document.querySelector('input[name="cep"]').addEventListener('blur', function() {
        let cep = this.value.replace(/\D/g, ''); 

        if (cep.length === 8) {
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(dados => {
                    if (!dados.erro) {
                        document.querySelector('input[name="rua"]').value = dados.logradouro;
                        document.querySelector('input[name="bairro"]').value = dados.bairro;
                        document.querySelector('input[name="cidade"]').value = dados.localidade;
                        document.querySelector('input[name="estado"]').value = dados.uf;
                        document.querySelector('input[name="numero"]').focus();
                    } else {
                        alert("CEP não encontrado.");
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar o CEP:', error);
                });
        }
    });
    </script>

</body>
</html>
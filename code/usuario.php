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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha conta | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<?php if (isset($_GET['sucesso'])): ?>
    <div id="ajOk" class="aj-toast-top" role="status">Alterações salvas.</div>
    <script>setTimeout(() => { const t = document.getElementById('ajOk'); if (t) { t.style.opacity = '0'; setTimeout(() => t.remove(), 600); } }, 3000);</script>
<?php endif; ?>

<main class="aj-wrap">
    <section class="aj-pagehead">
        <h1>Olá, <?= htmlspecialchars(explode(' ', trim($user['nome'] ?? 'cliente'))[0]) ?></h1>
        <p>Atualize seus dados e o endereço usado no checkout.</p>
    </section>

    <div class="aj-account">
        <nav class="aj-side" aria-label="Minha conta">
            <a class="active" href="usuario.php">Meus dados</a>
            <a href="meus_pedidos.php">Meus pedidos</a>
            <a href="favoritos.php">Favoritos</a>
            <a class="sair" href="logout.php">Sair da conta</a>
        </nav>

        <div>
            <section class="aj-panel" id="perfil">
                <h2>Dados pessoais</h2>
                <form action="usuario_atualizar.php" method="POST" class="aj-form">
                    <div class="aj-field">
                        <label for="u-nome">Nome completo</label>
                        <input type="text" id="u-nome" name="nome" value="<?= htmlspecialchars($user['nome'] ?? '') ?>" required>
                    </div>
                    <div class="aj-field">
                        <label for="u-email">E-mail de acesso</label>
                        <input type="email" id="u-email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                    </div>
                    <div class="aj-grid2">
                        <div class="aj-field">
                            <label for="u-cpf">CPF</label>
                            <input type="text" id="u-cpf" name="cpf" value="<?= htmlspecialchars($user['cpf'] ?? '') ?>" placeholder="000.000.000-00" maxlength="14">
                        </div>
                        <div class="aj-field">
                            <label for="u-tel">Telefone</label>
                            <input type="text" id="u-tel" name="telefone" value="<?= htmlspecialchars($user['telefone'] ?? '') ?>" placeholder="(00) 00000-0000" maxlength="15">
                        </div>
                    </div>
                    <button type="submit" class="aj-btn">Salvar dados</button>
                </form>
            </section>

            <section class="aj-panel" id="enderecos">
                <h2>Endereço de entrega</h2>
                <form action="usuario_atualizar.php" method="POST" class="aj-form">
                    <div class="aj-grid3">
                        <div class="aj-field">
                            <label for="u-cep">CEP</label>
                            <input type="text" id="u-cep" name="cep" value="<?= htmlspecialchars($user['cep'] ?? '') ?>" placeholder="00000-000" maxlength="9" autocomplete="postal-code">
                        </div>
                        <div class="aj-field">
                            <label for="u-rua">Rua</label>
                            <input type="text" id="u-rua" name="rua" value="<?= htmlspecialchars($user['rua'] ?? '') ?>">
                        </div>
                        <div class="aj-field">
                            <label for="u-num">Número</label>
                            <input type="text" id="u-num" name="numero" value="<?= htmlspecialchars($user['numero'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="aj-grid3">
                        <div class="aj-field">
                            <label for="u-bairro">Bairro</label>
                            <input type="text" id="u-bairro" name="bairro" value="<?= htmlspecialchars($user['bairro'] ?? '') ?>">
                        </div>
                        <div class="aj-field">
                            <label for="u-cidade">Cidade</label>
                            <input type="text" id="u-cidade" name="cidade" value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
                        </div>
                        <div class="aj-field">
                            <label for="u-uf">Estado (UF)</label>
                            <input type="text" id="u-uf" name="estado" value="<?= htmlspecialchars($user['estado'] ?? '') ?>" maxlength="2">
                        </div>
                    </div>
                    <p class="aj-hint" id="cepMsg" style="margin:-4px 0 14px"></p>
                    <button type="submit" class="aj-btn">Salvar endereço</button>
                </form>
            </section>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
// Preenche o endereço pelo CEP (ViaCEP)
document.querySelector('input[name="cep"]').addEventListener('blur', function () {
    const cep = this.value.replace(/\D/g, ''), msg = document.getElementById('cepMsg');
    msg.textContent = '';
    if (cep.length !== 8) return;
    fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(r => r.json())
        .then(d => {
            if (d.erro) { msg.textContent = 'CEP não encontrado. Confira os números.'; return; }
            document.querySelector('input[name="rua"]').value    = d.logradouro;
            document.querySelector('input[name="bairro"]').value = d.bairro;
            document.querySelector('input[name="cidade"]').value = d.localidade;
            document.querySelector('input[name="estado"]').value = d.uf;
            document.querySelector('input[name="numero"]').focus();
        })
        .catch(() => { msg.textContent = 'Não foi possível buscar o CEP agora. Preencha o endereço manualmente.'; });
});
</script>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>

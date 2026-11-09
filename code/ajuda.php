<?php
require_once 'config.php';
require_once 'contato_helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
contato_garantir_schema($pdo);

$erro = '';
$enviado = isset($_GET['enviado']);
$v = ['nome' => '', 'email' => '', 'pedido' => '', 'assunto' => '', 'mensagem' => ''];

// Se o cliente está logado, já preenche nome e e-mail
if (!empty($_SESSION['usuario_id'])) {
    try {
        $q = $pdo->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
        $q->execute([(int)$_SESSION['usuario_id']]);
        if ($u = $q->fetch(PDO::FETCH_ASSOC)) { $v['nome'] = $u['nome']; $v['email'] = $u['email']; }
    } catch (Throwable $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_contato'])) {
    foreach (['nome', 'email', 'pedido', 'assunto', 'mensagem'] as $k) { $v[$k] = trim((string)($_POST[$k] ?? '')); }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!contato_csrf_ok($_POST['csrf'] ?? null)) {
        $erro = 'Sessão expirada. Atualize a página e tente de novo.';
    } elseif (!empty($_POST['site'])) {
        $enviado = true; $erro = '';   // campo-isca preenchido (robô): finge sucesso e não grava
        $v = array_map(fn() => '', $v);
    } elseif ($v['nome'] === '' || $v['assunto'] === '' || $v['mensagem'] === '') {
        $erro = 'Preencha nome, assunto e mensagem.';
    } elseif (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido para podermos responder.';
    } elseif (mb_strlen($v['mensagem']) < 10) {
        $erro = 'Escreva um pouco mais na mensagem, para entendermos sua dúvida.';
    } else {
        try {
            $q = $pdo->prepare("SELECT COUNT(*) FROM mensagens_contato WHERE ip = ? AND data_criacao > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $q->execute([$ip]);
            if ((int)$q->fetchColumn() >= 5) {
                $erro = 'Você já enviou várias mensagens. Aguarde um pouco e tente de novo.';
            } else {
                $pdo->prepare("INSERT INTO mensagens_contato (usuario_id, nome, email, pedido_numero, assunto, mensagem, ip) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$_SESSION['usuario_id'] ?? null, mb_substr($v['nome'], 0, 120), mb_substr($v['email'], 0, 160),
                               $v['pedido'] !== '' ? mb_substr($v['pedido'], 0, 30) : null, mb_substr($v['assunto'], 0, 160),
                               mb_substr($v['mensagem'], 0, 4000), $ip]);
                header('Location: ajuda.php?enviado=1#contato'); exit;
            }
        } catch (Throwable $e) {
            error_log('[CONTATO] ' . $e->getMessage());
            $erro = 'Não foi possível enviar agora. Tente novamente em instantes.';
        }
    }
}
$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central de ajuda | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page">

<?php include 'header.php'; ?>

<main class="aj-wrap">
    <section class="aj-pagehead">
        <h1>Central de ajuda</h1>
        <p>Respostas sobre entrega, pagamento, trocas e devoluções.</p>
    </section>

    <section class="aj-help aj-section" style="padding-top:32px">
        <article>
            <h3>Entrega e prazos</h3>
            <p>No checkout você informa o CEP e vê as opções de frete com preço e prazo de cada uma, antes de pagar. A entrega começa depois da confirmação do pagamento.</p>
        </article>
        <article>
            <h3>Trocas e devoluções</h3>
            <p>Você tem até 7 dias após o recebimento para pedir troca ou devolução. O pedido é feito em Minha conta, na página Meus pedidos, com foto do produto.</p>
        </article>
        <article>
            <h3>Pagamento</h3>
            <p>Aceitamos PIX (com 5% de desconto), cartão de crédito e boleto. Os dados do cartão são enviados direto ao gateway de pagamento e não ficam guardados na loja.</p>
        </article>
    </section>

    <section class="aj-section">
        <div class="aj-section-head"><h2>Dúvidas frequentes</h2></div>
        <div class="aj-faq">
            <details>
                <summary>Como acompanho meu pedido?</summary>
                <p>Entre na sua conta e abra Meus pedidos. Lá você vê o status de cada compra: aguardando pagamento, pago, em separação, enviado ou entregue.</p>
            </details>
            <details>
                <summary>Quando meu pedido é confirmado?</summary>
                <p>No PIX e no cartão, a confirmação costuma ser em até 1 minuto. No boleto, depois da compensação, que leva de 1 a 3 dias úteis.</p>
            </details>
            <details>
                <summary>Posso cancelar um pedido?</summary>
                <p>Pedidos ainda não enviados podem ser cancelados. Fale com a gente pelo formulário abaixo informando o número do pedido.</p>
            </details>
            <details>
                <summary>Como uso um cupom?</summary>
                <p>No checkout, digite o código no campo Cupom de desconto, no resumo do pedido, e toque em Aplicar. O desconto aparece no total na hora.</p>
            </details>
        </div>
    </section>

    <section class="aj-section" id="contato">
        <div class="aj-section-head"><h2>Ainda com dúvidas?</h2></div>
        <div class="aj-panel" style="max-width:640px">
            <?php if ($enviado): ?>
                <p style="font-weight:700;margin-bottom:6px">Mensagem enviada!</p>
                <p style="color:var(--aj-soft)">Recebemos sua mensagem e vamos responder no e-mail informado.</p>
            <?php else: ?>
            <p style="color:var(--aj-soft);margin-bottom:18px">Escreva para a nossa equipe e responderemos por e-mail.</p>
            <?php if ($erro): ?><p role="alert" style="color:#c62828;font-weight:600;margin-bottom:14px"><?= $h($erro) ?></p><?php endif; ?>
            <form method="POST" action="ajuda.php#contato" class="aj-form" novalidate>
                <input type="hidden" name="csrf" value="<?= $h(contato_csrf_token()) ?>">
                <div style="position:absolute;left:-9999px" aria-hidden="true"><input type="text" name="site" tabindex="-1" autocomplete="off"></div>
                <div class="aj-field"><label for="c_nome">Nome</label><input id="c_nome" name="nome" maxlength="120" required value="<?= $h($v['nome']) ?>"></div>
                <div class="aj-field"><label for="c_email">E-mail</label><input id="c_email" type="email" name="email" maxlength="160" required value="<?= $h($v['email']) ?>"></div>
                <div class="aj-field"><label for="c_pedido">Número do pedido (opcional)</label><input id="c_pedido" name="pedido" maxlength="30" value="<?= $h($v['pedido']) ?>"></div>
                <div class="aj-field"><label for="c_assunto">Assunto</label><input id="c_assunto" name="assunto" maxlength="160" required value="<?= $h($v['assunto']) ?>"></div>
                <div class="aj-field"><label for="c_msg">Mensagem</label><textarea id="c_msg" name="mensagem" rows="5" maxlength="4000" required><?= $h($v['mensagem']) ?></textarea></div>
                <button class="aj-btn" type="submit" name="enviar_contato" value="1">Enviar mensagem</button>
            </form>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>

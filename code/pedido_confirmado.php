<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php"); exit;
}

$pedido_id = (int)($_GET['id']     ?? 0);
$metodo    = $_GET['metodo']       ?? 'pix';

if (!$pedido_id) { header("Location: index.php"); exit; }

// Busca o pedido e valida que pertence ao usuário logado
$stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ? AND usuario_id = ?");
$stmt->execute([$pedido_id, $_SESSION['usuario_id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) { header("Location: index.php"); exit; }

// Verifica se o pagamento já foi aprovado pelo webhook
$aprovado = ($pedido['status'] === 'pago');

// Para PIX/Boleto que ainda estão pendentes: busca dados do MP nas observações
// (o processar_pedido.php salvou o QR/boleto no frontend via JSON — aqui é só fallback)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Confirmado | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body class="aj-page aj-conf">

<?php include 'header.php'; ?>

<main class="container">

    <?php if ($aprovado): ?>
        <!-- ── CARTÃO APROVADO NA HORA ── -->
        <span class="status-icon"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
        <span class="badge-status badge-pago">Pagamento aprovado</span>
        <h1>Pedido confirmado</h1>
        <p class="subtitulo">
            Seu pagamento foi aprovado. Já estamos separando seu pedido.<br>
            Você receberá um e-mail com os detalhes e o código de rastreio quando for despachado.
        </p>

    <?php elseif ($metodo === 'pix'): ?>
        <!-- ── PIX AGUARDANDO ── -->
        <span class="status-icon"><svg viewBox="0 0 24 24"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/></svg></span>
        <span class="badge-status badge-pendente">Aguardando pagamento</span>
        <h1>Falta pouco</h1>
        <p class="subtitulo">
            Escaneie o QR Code ou copie o código PIX abaixo.<br>
            O pedido é confirmado automaticamente em até 1 minuto após o pagamento.
        </p>

        <div class="pix-box">
            <h3>Pague com PIX</h3>
            <img id="qrCodeImg" src="" alt="QR Code PIX">
            <div class="copia-cola" id="pixCopiaCola">Carregando código...</div>
            <button class="btn-copiar" onclick="copiarPix()">Copiar código PIX</button>
            <p class="expiracao" id="pixExpiracao"></p>

            <div class="polling-bar" id="pollingBar">
                <p class="polling-txt">Aguardando confirmação do pagamento...</p>
                <div class="progress-wrap"><div class="progress-anim"></div></div>
            </div>
        </div>

    <?php elseif ($metodo === 'boleto'): ?>
        <!-- ── BOLETO AGUARDANDO ── -->
        <span class="status-icon"><svg viewBox="0 0 24 24"><path d="M4 6v12M8 6v12M12 6v12M15 6v12M19 6v12"/></svg></span>
        <span class="badge-status badge-pendente">Boleto gerado</span>
        <h1>Boleto pronto</h1>
        <p class="subtitulo">
            Pague o boleto em qualquer banco, lotérica ou pelo app do seu banco.<br>
            O pedido é confirmado após a compensação (1 a 3 dias úteis).
        </p>

        <div class="boleto-box">
            <h3>Boleto bancário</h3>
            <a id="boletoUrl" href="#" target="_blank" class="btn-boleto">Abrir ou imprimir boleto</a>
            <p style="font-size:12px; color:#888; margin-bottom:12px;">
                Ou copie o código de barras:
            </p>
            <div class="barcode-txt" id="barcodeDisplay">Carregando...</div>
            <p style="font-size:12px; color:#f57c00; font-weight:700; margin-top:14px;">
                Vencimento: <span id="boletoVencimento"></span>
            </p>
        </div>

    <?php else: ?>
        <!-- ── FALLBACK GENÉRICO ── -->
        <span class="status-icon"><svg viewBox="0 0 24 24"><path d="M3 7.5L12 3l9 4.5v9L12 21l-9-4.5v-9z"/><path d="M3 7.5L12 12l9-4.5M12 12v9"/></svg></span>
        <span class="badge-status badge-pendente">Pedido recebido</span>
        <h1>Pedido registrado</h1>
        <p class="subtitulo">Seu pedido foi recebido e está aguardando confirmação de pagamento.</p>
    <?php endif; ?>

    <!-- INFO DO PEDIDO -->
    <div class="pedido-info">
        <h3>Detalhes do pedido</h3>
        <div class="info-linha">
            <span>Número do pedido</span>
            <span>#<?= str_pad($pedido['id'], 4, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="info-linha">
            <span>Forma de pagamento</span>
            <span style="text-transform:uppercase;"><?= htmlspecialchars($pedido['forma_pagamento']) ?></span>
        </div>
        <?php if ($pedido['desconto'] > 0): ?>
        <div class="info-linha">
            <span>Desconto PIX (5%)</span>
            <span style="color:#2e7d32;">- R$ <?= number_format($pedido['desconto'],2,',','.') ?></span>
        </div>
        <?php endif; ?>
        <div class="info-linha">
            <span>Total</span>
            <span style="font-size:18px;">R$ <?= number_format($pedido['total'],2,',','.') ?></span>
        </div>
        <div class="info-linha">
            <span>Status</span>
            <span><?= $aprovado ? 'Pago' : 'Aguardando pagamento' ?></span>
        </div>
    </div>

    <div class="acoes">
        <a href="meus_pedidos.php" class="btn-black">Acompanhar pedidos</a>
        <a href="index.php" class="btn-outline">Continuar comprando</a>
    </div>
</main>

<?php include 'footer.php'; ?>

<script>
const METODO     = <?= json_encode(in_array($metodo, ['pix','cartao','boleto'], true) ? $metodo : 'pix') ?>;
const PEDIDO_ID  = <?= $pedido_id ?>;
const JA_APROVADO= <?= $aprovado ? 'true' : 'false' ?>;

// Recupera os dados do MP que o processar_pedido.php retornou
// e o checkout.js guardou no sessionStorage antes do redirect
const mpData = JSON.parse(sessionStorage.getItem('mp_payment_data') || '{}');

document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('fashion_cart');

    if (JA_APROVADO) return; // Cartão já aprovado, nada a fazer

    if (METODO === 'pix' && (mpData.qr_base64 || mpData.qr_img_url)) {
        document.getElementById('qrCodeImg').src = mpData.qr_base64
            ? 'data:image/png;base64,' + mpData.qr_base64
            : mpData.qr_img_url;
        document.getElementById('pixCopiaCola').textContent = mpData.qr_code || '';
        if (mpData.expiracao) {
            document.getElementById('pixExpiracao').textContent = 'Expira às ' + mpData.expiracao;
        }
        document.getElementById('pollingBar').style.display = 'block';
        iniciarPolling();
    }

    if (METODO === 'boleto') {
        if (mpData.boleto_url) {
            document.getElementById('boletoUrl').href = mpData.boleto_url;
        }
        document.getElementById('barcodeDisplay').textContent = mpData.barcode || 'Não disponível';
        document.getElementById('boletoVencimento').textContent = mpData.vencimento || '';
    }
});

// Copia o código PIX
function copiarPix() {
    const codigo = document.getElementById('pixCopiaCola').textContent;
    navigator.clipboard.writeText(codigo).then(() => {
        const btn = document.querySelector('.btn-copiar');
        btn.textContent = 'Código copiado';
        btn.classList.add('copiado');
        setTimeout(() => {
            btn.textContent = 'Copiar código PIX';
            btn.classList.remove('copiado');
        }, 3000);
    });
}

// Polling: consulta o servidor a cada 5s para verificar se o PIX foi pago
let pollingInterval;

function iniciarPolling() {
    pollingInterval = setInterval(async () => {
        try {
            const res  = await fetch('verificar_pagamento.php?pedido_id=' + PEDIDO_ID);
            const data = await res.json();
            if (data.pago) {
                clearInterval(pollingInterval);
                // Redireciona atualizando a página com status aprovado
                window.location.href = 'pedido_confirmado.php?id=' + PEDIDO_ID + '&metodo=pix&aprovado=1';
            }
        } catch(e) {}
    }, 5000);

    // Para o polling após 30 minutos (expiração do PIX)
    setTimeout(() => clearInterval(pollingInterval), 30 * 60 * 1000);
}
</script>
</body>
</html>
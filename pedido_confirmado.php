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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter',sans-serif; background:#fcfcfc; margin:0; }

        .container {
            max-width: 680px; margin: 60px auto 100px;
            padding: 0 20px; text-align: center;
        }

        .status-icon { font-size: 60px; margin-bottom: 20px; display: block; }

        .badge-status {
            display: inline-block; padding: 8px 20px;
            border-radius: 50px; font-size: 11px; font-weight: 900;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 24px;
        }
        .badge-pago     { background: #e8f5e9; color: #2e7d32; }
        .badge-pendente { background: #fff8e1; color: #f57c00; }

        h1 { font-size: 2.5rem; font-weight: 900; letter-spacing: -1.5px; margin-bottom: 16px; }

        .subtitulo { color: #666; line-height: 1.7; max-width: 500px; margin: 0 auto 40px; }

        /* ── PIX ── */
        .pix-box {
            background: #fff; border: 1px solid #eee; border-radius: 24px;
            padding: 36px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,.04);
        }

        .pix-box h3 { font-weight: 900; margin-bottom: 20px; font-size: 15px; text-transform: uppercase; }

        #qrCodeImg {
            width: 200px; height: 200px; border-radius: 12px; border: 1px solid #eee;
            display: block; margin: 0 auto 20px;
        }

        .copia-cola {
            background: #f8f8f8; border: 1px solid #eee; border-radius: 12px;
            padding: 14px 16px; font-size: 12px; font-family: monospace;
            word-break: break-all; text-align: left; margin-bottom: 14px;
            color: #333; max-height: 80px; overflow: auto;
        }

        .btn-copiar {
            background: #000; color: #fff; border: none; padding: 14px 32px;
            border-radius: 50px; font-weight: 800; font-size: 12px; cursor: pointer;
            text-transform: uppercase; letter-spacing: 1px; transition: .3s;
        }
        .btn-copiar:hover { background: #333; }
        .btn-copiar.copiado { background: #2e7d32; }

        .expiracao { font-size: 12px; color: #f57c00; font-weight: 700; margin-top: 14px; }

        /* ── BOLETO ── */
        .boleto-box {
            background: #fff; border: 1px solid #eee; border-radius: 24px;
            padding: 36px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,.04);
        }

        .boleto-box h3 { font-weight: 900; margin-bottom: 14px; font-size: 15px; text-transform: uppercase; }

        .btn-boleto {
            display: inline-block; background: #000; color: #fff;
            padding: 16px 36px; border-radius: 50px; text-decoration: none;
            font-weight: 800; font-size: 13px; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 16px; transition: .3s;
        }
        .btn-boleto:hover { background: #333; }

        .barcode-txt {
            font-family: monospace; font-size: 12px; color: #555;
            word-break: break-all; background: #f8f8f8;
            border-radius: 10px; padding: 12px 16px; text-align: left;
        }

        /* ── INFO DO PEDIDO ── */
        .pedido-info {
            background: #fff; border: 1px solid #eee; border-radius: 24px;
            padding: 30px; margin-bottom: 24px; text-align: left;
        }

        .pedido-info h3 { font-weight: 900; margin-bottom: 18px; font-size: 14px; text-transform: uppercase; }

        .info-linha {
            display: flex; justify-content: space-between;
            padding: 10px 0; border-bottom: 1px solid #f5f5f5; font-size: 14px;
        }
        .info-linha:last-child { border-bottom: none; }
        .info-linha span:first-child { color: #888; }
        .info-linha span:last-child  { font-weight: 700; }

        /* ── POLLING (PIX aguardando) ── */
        .polling-bar { display: none; margin: 20px 0 0; }
        .polling-txt { font-size: 12px; color: #888; margin-bottom: 8px; }
        .progress-wrap { height: 4px; background: #eee; border-radius: 4px; overflow: hidden; }
        .progress-anim { height: 100%; background: #000; border-radius: 4px;
            animation: progress 2s linear infinite; width: 30%; }
        @keyframes progress { 0%{transform:translateX(-100%)} 100%{transform:translateX(400%)} }

        /* ── BOTÕES FINAIS ── */
        .acoes { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; margin-top: 10px; }
        .btn-black {
            background: #000; color: #fff; padding: 16px 36px;
            border-radius: 50px; text-decoration: none; font-weight: 800;
            font-size: 12px; text-transform: uppercase; letter-spacing: 1px; transition: .3s;
        }
        .btn-black:hover { background: #333; }
        .btn-outline {
            background: #fff; color: #000; padding: 16px 36px;
            border-radius: 50px; text-decoration: none; font-weight: 800;
            font-size: 12px; text-transform: uppercase; border: 1.5px solid #000; transition: .3s;
        }
        .btn-outline:hover { background: #000; color: #fff; }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<div class="container">

    <?php if ($aprovado): ?>
        <!-- ── CARTÃO APROVADO NA HORA ── -->
        <span class="status-icon">✅</span>
        <span class="badge-status badge-pago">Pagamento Aprovado</span>
        <h1>Pedido Confirmado!</h1>
        <p class="subtitulo">
            Seu pagamento foi aprovado. Já estamos separando seu pedido.<br>
            Você receberá um e-mail com os detalhes e o código de rastreio quando for despachado.
        </p>

    <?php elseif ($metodo === 'pix'): ?>
        <!-- ── PIX AGUARDANDO ── -->
        <span class="status-icon">⚡</span>
        <span class="badge-status badge-pendente">Aguardando Pagamento</span>
        <h1>Quase lá!</h1>
        <p class="subtitulo">
            Escaneie o QR Code ou copie o código PIX abaixo.<br>
            O pedido é confirmado automaticamente em até 1 minuto após o pagamento.
        </p>

        <div class="pix-box">
            <h3>Pague com PIX</h3>
            <img id="qrCodeImg" src="" alt="QR Code PIX">
            <div class="copia-cola" id="pixCopiaCola">Carregando código...</div>
            <button class="btn-copiar" onclick="copiarPix()">Copiar Código PIX</button>
            <p class="expiracao" id="pixExpiracao"></p>

            <div class="polling-bar" id="pollingBar">
                <p class="polling-txt">Aguardando confirmação do pagamento...</p>
                <div class="progress-wrap"><div class="progress-anim"></div></div>
            </div>
        </div>

    <?php elseif ($metodo === 'boleto'): ?>
        <!-- ── BOLETO AGUARDANDO ── -->
        <span class="status-icon">🏦</span>
        <span class="badge-status badge-pendente">Boleto Gerado</span>
        <h1>Boleto Pronto!</h1>
        <p class="subtitulo">
            Pague o boleto em qualquer banco, lotérica ou pelo app do seu banco.<br>
            O pedido é confirmado após a compensação (1 a 3 dias úteis).
        </p>

        <div class="boleto-box">
            <h3>Boleto Bancário</h3>
            <a id="boletoUrl" href="#" target="_blank" class="btn-boleto">Abrir / Imprimir Boleto</a>
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
        <span class="status-icon">📦</span>
        <span class="badge-status badge-pendente">Pedido Recebido</span>
        <h1>Pedido Registrado!</h1>
        <p class="subtitulo">Seu pedido foi recebido e está aguardando confirmação de pagamento.</p>
    <?php endif; ?>

    <!-- INFO DO PEDIDO -->
    <div class="pedido-info">
        <h3>Detalhes do Pedido</h3>
        <div class="info-linha">
            <span>Número do Pedido</span>
            <span>#<?= str_pad($pedido['id'], 4, '0', STR_PAD_LEFT) ?></span>
        </div>
        <div class="info-linha">
            <span>Forma de Pagamento</span>
            <span style="text-transform:uppercase;"><?= $pedido['forma_pagamento'] ?></span>
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
            <span><?= $aprovado ? '✅ Pago' : '⏳ Aguardando pagamento' ?></span>
        </div>
    </div>

    <div class="acoes">
        <a href="meus_pedidos.php" class="btn-black">Acompanhar Pedidos</a>
        <a href="index.php" class="btn-outline">Continuar Comprando</a>
    </div>
</div>

<script>
const METODO     = '<?= $metodo ?>';
const PEDIDO_ID  = <?= $pedido_id ?>;
const JA_APROVADO= <?= $aprovado ? 'true' : 'false' ?>;

// Recupera os dados do MP que o processar_pedido.php retornou
// e o checkout.js guardou no sessionStorage antes do redirect
const mpData = JSON.parse(sessionStorage.getItem('mp_payment_data') || '{}');

document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('fashion_cart');

    if (JA_APROVADO) return; // Cartão já aprovado, nada a fazer

    if (METODO === 'pix' && mpData.qr_base64) {
        document.getElementById('qrCodeImg').src = 'data:image/png;base64,' + mpData.qr_base64;
        document.getElementById('pixCopiaCola').textContent = mpData.qr_code || '';
        if (mpData.expiracao) {
            document.getElementById('pixExpiracao').textContent = '⏱ Expira às ' + mpData.expiracao;
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
        btn.textContent = '✓ Copiado!';
        btn.classList.add('copiado');
        setTimeout(() => {
            btn.textContent = 'Copiar Código PIX';
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
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php?msg=faca_login_para_finalizar");
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.*, e.cep, e.rua, e.numero, e.bairro, e.cidade, e.estado
    FROM usuarios u
    LEFT JOIN enderecos e ON e.usuario_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['usuario_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica quais gateways estão configurados
$mp_ok = defined('MP_ACCESS_TOKEN') && MP_ACCESS_TOKEN !== '' && MP_ACCESS_TOKEN !== 'SEU_ACCESS_TOKEN_AQUI';
$ps_ok = defined('PS_TOKEN')        && PS_TOKEN        !== '' && PS_TOKEN        !== '7312ac40-5b19-4a72-bcb3-4f63523d9c89f542729a4711be1a5295634ea30cba24defd-2a78-4ba7-abd0-793569c88edb';

// Public keys para o front-end
$mp_public_key = defined('MP_PUBLIC_KEY') ? MP_PUBLIC_KEY : '';
$ps_public_key = defined('MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAr+ZqgD892U9/HXsa7XqBZUayPquAfh9xx4iwUbTSUAvTlmiXFQNTp0Bvt/5vK2FhMj39qSv1zi2OuBjvW38q1E374nzx6NNBL5JosV0+SDINTlCG0cmigHuBOyWzYmjgca+mtQu4WczCaApNaSuVqgb8u7Bd9GCOL4YJotvV5+81frlSwQXralhwRzGhj/A57CGPgGKiuPT+AOGmykIGEZsSD9RKkyoKIoc0OS8CPIzdBOtTQCIwrLn2FxI83Clcg55W8gkFSOS6rWNbG5qFZWMll6yl02HtunalHmUlRUL66YeGXdMDC2PuRcmZbGO5a/2tbVppW6mfSWG3NPRpgwIDAQAB') ? PS_PUBLIC_KEY : ''; // chave pública do PS (opcional)
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <?php if ($mp_ok && $mp_public_key): ?>
    <!-- SDK Mercado Pago -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <?php endif; ?>

    <?php if ($ps_ok): ?>
    <!-- SDK PagSeguro (tokenização de cartão) -->
    <script src="https://assets.pagseguro.com.br/checkout-sdk/js/pagseguro.min.js"></script>
    <?php endif; ?>

    <style>
        body { background:#fff; color:#000; font-family:'Inter',sans-serif; }

        .checkout-container {
            max-width:1100px; margin:40px auto 100px;
            padding:0 20px; display:grid;
            grid-template-columns:1fr 380px; gap:80px;
        }

        .checkout-form h2 {
            font-size:2.5rem; font-weight:900;
            text-transform:uppercase; margin-bottom:50px; letter-spacing:-2px;
        }

        .section-title {
            font-size:10px; font-weight:900; text-transform:uppercase;
            letter-spacing:3px; color:#bbb; margin:40px 0 20px;
            display:flex; align-items:center; gap:15px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:#efefef; }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-weight:800; font-size:11px; margin-bottom:8px; text-transform:uppercase; }
        .form-group input,
        .form-group select {
            width:100%; padding:16px; border:1.5px solid #efefef;
            border-radius:12px; font-size:14px; font-family:inherit;
            background:#fbfbfb; transition:.3s; box-sizing:border-box;
        }
        .form-group input:focus,
        .form-group select:focus { border-color:#000; background:#fff; outline:none; }
        .form-group input[readonly] { opacity:.6; cursor:not-allowed; }

        /* ── MÉTODOS DE PAGAMENTO ── */
        .metodos-pagamento { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:10px; }
        .metodo-card {
            border:2px solid #eee; border-radius:16px; padding:18px 10px;
            text-align:center; cursor:pointer; transition:.3s; background:#fff;
            user-select:none;
        }
        .metodo-card:hover { border-color:#aaa; }
        .metodo-card.ativo { border-color:#000; background:#000; color:#fff; }
        .metodo-card .icon  { font-size:26px; display:block; margin-bottom:8px; }
        .metodo-card .label { font-size:12px; font-weight:800; text-transform:uppercase; }
        .metodo-card .sub   { font-size:10px; opacity:.6; margin-top:3px; }

        /* ── PAINEL PIX ── */
        #painel-pix { display:none; margin-top:10px; }
        .pix-info { background:#f9f9f9; border-radius:16px; padding:20px; border-left:4px solid #000; font-size:13px; line-height:1.7; }

        /* ── PAINEL BOLETO ── */
        #painel-boleto { display:none; margin-top:10px; }
        .boleto-info { background:#f9f9f9; border-radius:16px; padding:20px; font-size:13px; line-height:1.7; }

        /* ── PAINEL CARTÃO ── */
        #painel-cartao { display:none; margin-top:10px; }
        .input-wrapper { position:relative; }
        .card-flag { width:40px; height:26px; object-fit:contain; position:absolute; right:14px; top:50%; transform:translateY(-50%); }

        /* ── BADGE GATEWAY ── */
        .gateway-badge {
            display:inline-flex; align-items:center; gap:6px;
            background:#f0f7ff; border:1px solid #c8dff7; border-radius:50px;
            padding:5px 12px; font-size:11px; font-weight:700; color:#1565c0;
            margin-top:10px;
        }

        /* ── AVISO SEM GATEWAY ── */
        .aviso-gateway {
            background:#fff8e1; border:1px solid #ffe082; border-radius:14px;
            padding:16px 18px; font-size:13px; color:#b45309; line-height:1.6;
            margin-bottom:20px;
        }
        .aviso-gateway strong { display:block; margin-bottom:4px; }

        /* ── BOTÃO ── */
        .btn-finalizar {
            width:100%; padding:22px; background:#000; color:#fff; border:none;
            border-radius:50px; font-weight:900; font-size:13px; letter-spacing:2px;
            text-transform:uppercase; cursor:pointer; transition:.4s; margin-top:20px;
        }
        .btn-finalizar:hover { background:#333; transform:translateY(-3px); box-shadow:0 15px 30px rgba(0,0,0,.15); }
        .btn-finalizar:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }

        /* ── ERRO ── */
        .msg-erro {
            background:#fff0f0; border:1px solid #ffc0c0; color:#c62828;
            padding:14px 18px; border-radius:12px; font-size:13px;
            font-weight:600; margin-top:16px; display:none;
        }

        /* ── RESUMO ── */
        .resumo-pedido {
            background:#fff; padding:35px; border-radius:30px;
            border:1px solid #efefef; position:sticky; top:120px; height:fit-content;
        }
        .item-checkout { display:flex; gap:15px; margin-bottom:20px; align-items:center; }
        .item-checkout img { width:60px; height:60px; object-fit:cover; background:#f5f5f5; border-radius:10px; }
        .item-info h4 { font-size:12px; font-weight:800; text-transform:uppercase; margin:0; }
        .item-info p  { font-size:10px; color:#888; margin:2px 0; }
        .item-info span { font-size:12px; font-weight:700; }
        .summary-totals { margin-top:25px; padding-top:25px; border-top:1px solid #eee; }
        .total-line { display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; }
        .total-line.final { font-size:24px; font-weight:900; margin-top:15px; border-top:2px solid #000; padding-top:15px; }
        .desconto-pix { background:#e8f5e9; color:#2e7d32; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:700; margin-top:6px; display:none; }

        @media (max-width:900px) {
            .checkout-container { grid-template-columns:1fr; }
            .metodos-pagamento  { grid-template-columns:1fr; }
            .resumo-pedido { position:static; margin-top:40px; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="checkout-container">
    <section class="checkout-form">
        <h2>Finalizar Pedido</h2>

        <?php if (!$mp_ok && !$ps_ok): ?>
        <div class="aviso-gateway">
            <strong>⚠ Nenhum gateway configurado</strong>
            O pedido será registrado, mas o pagamento real não será processado.
            Configure as credenciais em <code>mercadopago.php</code> ou <code>pagseguro.php</code>.
        </div>
        <?php endif; ?>

        <!-- DADOS DO COMPRADOR -->
        <span class="section-title">Dados do Comprador</span>
        <div class="form-row">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" id="campo-nome" required placeholder="Seu nome completo"
                       value="<?= htmlspecialchars($user['nome'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input type="email" id="campo-email"
                       value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>CPF *</label>
                <input type="text" id="campo-cpf" placeholder="000.000.000-00" maxlength="14"
                       value="<?= htmlspecialchars($user['cpf'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input type="text" id="campo-telefone" placeholder="(00) 00000-0000"
                       value="<?= htmlspecialchars($user['telefone'] ?? '') ?>">
            </div>
        </div>

        <!-- ENDEREÇO -->
        <span class="section-title">Endereço de Entrega</span>
        <div class="form-row">
            <div class="form-group">
                <label>CEP</label>
                <input type="text" id="campo-cep" required placeholder="00000-000" maxlength="9"
                       value="<?= htmlspecialchars($user['cep'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Estado (UF)</label>
                <input type="text" id="campo-estado" required placeholder="SP" maxlength="2"
                       value="<?= htmlspecialchars($user['estado'] ?? '') ?>">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:2.5fr 1fr; gap:20px;">
            <div class="form-group">
                <label>Rua / Logradouro</label>
                <input type="text" id="campo-rua" required placeholder="Ex: Av. Paulista"
                       value="<?= htmlspecialchars($user['rua'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Número</label>
                <input type="text" id="campo-numero" required placeholder="123"
                       value="<?= htmlspecialchars($user['numero'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Bairro</label>
                <input type="text" id="campo-bairro"
                       value="<?= htmlspecialchars($user['bairro'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Cidade</label>
                <input type="text" id="campo-cidade" required
                       value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
            </div>
        </div>

        <!-- MÉTODO DE PAGAMENTO -->
        <span class="section-title">Forma de Pagamento</span>

        <div class="metodos-pagamento">
            <div class="metodo-card ativo" onclick="selecionarMetodo('pix', this)">
                <span class="icon">⚡</span>
                <div class="label">PIX</div>
                <div class="sub">5% de desconto</div>
            </div>
            <div class="metodo-card" onclick="selecionarMetodo('cartao', this)">
                <span class="icon">💳</span>
                <div class="label">Cartão</div>
                <div class="sub">Até 12x</div>
            </div>
            <div class="metodo-card" onclick="selecionarMetodo('boleto', this)">
                <span class="icon">🏦</span>
                <div class="label">Boleto</div>
                <div class="sub">Vence em 3 dias</div>
            </div>
        </div>

        <!-- Badge mostrando quais gateways estão ativos -->
        <?php if ($mp_ok || $ps_ok): ?>
        <div class="gateway-badge">
            🔒 Pagamento processado por:
            <?= $mp_ok ? ' Mercado Pago' : '' ?>
            <?= ($mp_ok && $ps_ok) ? ' +' : '' ?>
            <?= $ps_ok ? ' PagSeguro' : '' ?>
            <?= ($mp_ok && $ps_ok) ? ' (fallback automático)' : '' ?>
        </div>
        <?php endif; ?>

        <!-- PAINEL PIX -->
        <div id="painel-pix">
            <div class="pix-info">
                ⚡ <strong>Pagamento via PIX</strong> — aprovação em até 1 minuto.<br>
                Após confirmar, você receberá o QR Code e o código copia-e-cola.
                O PIX expira em <strong>30 minutos</strong>.
            </div>
        </div>

        <!-- PAINEL BOLETO -->
        <div id="painel-boleto">
            <div class="boleto-info">
                🏦 <strong>Boleto Bancário</strong><br>
                Vence em 3 dias úteis. O pedido só é confirmado após a compensação (1 a 3 dias úteis).
            </div>
        </div>

        <!-- PAINEL CARTÃO -->
        <div id="painel-cartao">
            <div class="form-group input-wrapper">
                <label>Número do Cartão</label>
                <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19"
                       autocomplete="cc-number">
                <img id="cardFlag" class="card-flag" src="" alt="" style="display:none;">
            </div>
            <div class="form-group">
                <label>Nome no Cartão</label>
                <input type="text" id="cardholderName"
                       placeholder="Como está impresso no cartão"
                       style="text-transform:uppercase;" autocomplete="cc-name">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Validade (MM/AA)</label>
                    <input type="text" id="expirationDate" placeholder="MM/AA" maxlength="5"
                           autocomplete="cc-exp">
                </div>
                <div class="form-group">
                    <label>CVV</label>
                    <input type="text" id="securityCode" placeholder="000" maxlength="4"
                           autocomplete="cc-csc">
                </div>
            </div>
            <div class="form-group">
                <label>Parcelamento</label>
                <select id="selectParcelas">
                    <option value="1">1x sem juros</option>
                </select>
            </div>
        </div>

        <div class="msg-erro" id="msgErro"></div>

        <button class="btn-finalizar" id="btnFinalizar" onclick="finalizarPedido()">
            Confirmar e Pagar
        </button>
    </section>

    <!-- RESUMO -->
    <aside class="resumo-pedido">
        <h3 style="font-weight:900; margin-bottom:30px; text-transform:uppercase; font-size:13px; letter-spacing:1px;">
            Sua Sacola
        </h3>
        <div id="listaCheckout"></div>
        <div class="summary-totals">
            <div class="total-line">
                <span style="color:#888;">Subtotal</span>
                <span id="subtotalCheckout" style="font-weight:700;">R$ 0,00</span>
            </div>
            <div class="total-line">
                <span style="color:#888;">Frete</span>
                <span style="color:#27ae60; font-weight:800; font-size:11px;">GRÁTIS</span>
            </div>
            <div class="desconto-pix" id="descontoPix">
                🎉 Desconto PIX 5%: <span id="valorDesconto"></span>
            </div>
            <div class="total-line final">
                <span>TOTAL</span>
                <span id="totalCheckout">R$ 0,00</span>
            </div>
        </div>
    </aside>
</main>

<script>
// ── CONFIGURAÇÃO DOS GATEWAYS ──────────────────────────────
const CFG = {
    mp_ok:        <?= $mp_ok ? 'true' : 'false' ?>,
    ps_ok:        <?= $ps_ok ? 'true' : 'false' ?>,
    mp_public_key:'<?= htmlspecialchars($mp_public_key) ?>',
    ps_public_key:'<?= htmlspecialchars($ps_public_key) ?>',
};

// Inicializa SDK do MP se disponível
let mp = null;
if (CFG.mp_ok && CFG.mp_public_key) {
    try {
        mp = new MercadoPago(CFG.mp_public_key, { locale: 'pt-BR' });
    } catch(e) {
        console.warn('SDK do MP não carregou:', e.message);
    }
}

let metodoPagamento = 'pix';
let carrinhoGlobal  = [];
let totalBruto      = 0;

// ── INICIALIZAÇÃO ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    carrinhoGlobal = JSON.parse(sessionStorage.getItem('fashion_cart') || '[]');
    if (!carrinhoGlobal.length) { window.location.href = 'index.php'; return; }

    renderizarResumo();
    selecionarMetodo('pix', document.querySelector('.metodo-card.ativo'));
    setupCEP();
    setupMascaras();
});

// ── RESUMO ────────────────────────────────────────────────
function renderizarResumo() {
    totalBruto = 0;
    const lista = document.getElementById('listaCheckout');
    lista.innerHTML = carrinhoGlobal.map(item => {
        totalBruto += parseFloat(item.preco) * (item.qtd || 1);
        return `<div class="item-checkout">
            <img src="${item.img||''}" alt="${item.nome}" onerror="this.style.opacity='0'">
            <div class="item-info">
                <h4>${item.nome}</h4>
                <p>${item.tamanho_escolhido||''} ${item.cor_escolhida ? '| '+item.cor_escolhida : ''}</p>
                <span>${item.qtd||1}x R$ ${parseFloat(item.preco).toLocaleString('pt-br',{minimumFractionDigits:2})}</span>
            </div></div>`;
    }).join('');
    atualizarTotal();
}

function atualizarTotal() {
    const descPix = document.getElementById('descontoPix');
    const valDesc = document.getElementById('valorDesconto');
    let total = totalBruto;

    if (metodoPagamento === 'pix' && totalBruto > 0) {
        const desc = totalBruto * 0.05;
        total -= desc;
        descPix.style.display = 'block';
        valDesc.textContent   = '- R$ ' + desc.toLocaleString('pt-br',{minimumFractionDigits:2});
    } else {
        descPix.style.display = 'none';
    }
    document.getElementById('subtotalCheckout').textContent =
        'R$ ' + totalBruto.toLocaleString('pt-br',{minimumFractionDigits:2});
    document.getElementById('totalCheckout').textContent =
        'R$ ' + total.toLocaleString('pt-br',{minimumFractionDigits:2});
}

// ── SELETOR DE MÉTODO ─────────────────────────────────────
function selecionarMetodo(metodo, el) {
    metodoPagamento = metodo;
    document.querySelectorAll('.metodo-card').forEach(c => c.classList.remove('ativo'));
    el.classList.add('ativo');
    document.getElementById('painel-pix').style.display    = metodo==='pix'    ? 'block' : 'none';
    document.getElementById('painel-boleto').style.display = metodo==='boleto' ? 'block' : 'none';
    document.getElementById('painel-cartao').style.display = metodo==='cartao' ? 'block' : 'none';
    atualizarTotal();
}

// ── MÁSCARAS ──────────────────────────────────────────────
function setupMascaras() {
    // CPF
    document.getElementById('campo-cpf').addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'');
        v = v.replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d)/,'$1.$2').replace(/(\d{3})(\d{1,2})$/,'$1-$2');
        this.value = v;
    });

    // Cartão
    const cardEl = document.getElementById('cardNumber');
    if (cardEl) {
        cardEl.addEventListener('input', async function() {
            let v = this.value.replace(/\D/g,'').substring(0,16);
            this.value = v.replace(/(.{4})/g,'$1 ').trim();
            if (v.length >= 6 && mp) await detectarBandeira(v);
            if (v.length === 16 && mp) await carregarParcelas(v);
        });
    }

    // Validade
    const expEl = document.getElementById('expirationDate');
    if (expEl) {
        expEl.addEventListener('input', function() {
            let v = this.value.replace(/\D/g,'');
            if (v.length > 2) v = v.substring(0,2) + '/' + v.substring(2,4);
            this.value = v;
        });
    }

    // CVV
    const cvvEl = document.getElementById('securityCode');
    if (cvvEl) cvvEl.addEventListener('input', function() { this.value = this.value.replace(/\D/g,''); });

    // Nome maiúsculo
    const nomeEl = document.getElementById('cardholderName');
    if (nomeEl) nomeEl.addEventListener('input', function() { this.value = this.value.toUpperCase(); });

    // CEP
    document.getElementById('campo-cep').addEventListener('input', function() {
        let v = this.value.replace(/\D/g,'');
        if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5,8);
        this.value = v;
    });
}

// ── CEP ───────────────────────────────────────────────────
function setupCEP() {
    document.getElementById('campo-cep').addEventListener('blur', async function() {
        const cep = this.value.replace(/\D/g,'');
        if (cep.length !== 8) return;
        try {
            const r = await fetch('https://viacep.com.br/ws/' + cep + '/json/');
            const d = await r.json();
            if (!d.erro) {
                document.getElementById('campo-rua').value    = d.logradouro;
                document.getElementById('campo-bairro').value = d.bairro;
                document.getElementById('campo-cidade').value = d.localidade;
                document.getElementById('campo-estado').value = d.uf;
                document.getElementById('campo-numero').focus();
            }
        } catch(e) {}
    });
}

// ── BANDEIRA E PARCELAS (só MP) ───────────────────────────
async function detectarBandeira(numero) {
    if (!mp) return;
    try {
        const metodos = await mp.getPaymentMethods({ bin: numero.substring(0,6) });
        if (metodos.results?.length) {
            const m = metodos.results[0];
            window._mp_issuer_id         = m.issuer?.id;
            window._mp_payment_method_id = m.id;
            const flag = document.getElementById('cardFlag');
            flag.src = m.thumbnail;
            flag.style.display = 'block';
        }
    } catch(e) {}
}

async function carregarParcelas(numero) {
    if (!mp) return;
    try {
        const res = await mp.getInstallments({ amount: String(totalBruto), bin: numero.substring(0,6) });
        const sel = document.getElementById('selectParcelas');
        sel.innerHTML = '';
        (res[0]?.payer_costs || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.installments;
            opt.textContent = p.recommended_message;
            sel.appendChild(opt);
        });
    } catch(e) {}
}

// ── TOKENIZAÇÃO DUPLA (MP + PS) ───────────────────────────
async function gerarTokens() {
    const tokens = { mp: null, ps: null };

    const numero  = document.getElementById('cardNumber').value.replace(/\s/g,'');
    const nome    = document.getElementById('cardholderName').value;
    const expArr  = document.getElementById('expirationDate').value.split('/');
    const cvv     = document.getElementById('securityCode').value;
    const cpfNum  = document.getElementById('campo-cpf').value.replace(/\D/g,'');
    const mes     = expArr[0] || '';
    const ano     = '20' + (expArr[1] || '');

    // Token do Mercado Pago
    if (mp) {
        try {
            const t = await mp.createCardToken({
                cardNumber:          numero,
                cardholderName:      nome,
                cardExpirationMonth: mes,
                cardExpirationYear:  ano,
                securityCode:        cvv,
                identificationType:  'CPF',
                identificationNumber: cpfNum,
            });
            tokens.mp = t.id;
        } catch(e) {
            console.warn('Falha ao gerar token MP:', e.message);
        }
    }

    // Token do PagSeguro
    if (typeof PagSeguro !== 'undefined') {
        try {
            const encrypted = PagSeguro.encryptCard({
                publicKey:      CFG.ps_public_key || '',
                holder:         nome,
                number:         numero,
                expMonth:       mes,
                expYear:        ano,
                securityCode:   cvv,
            });
            tokens.ps = encrypted;
        } catch(e) {
            console.warn('Falha ao gerar token PS:', e.message);
        }
    }

    return tokens;
}

// ── VALIDAÇÃO ─────────────────────────────────────────────
function validar() {
    const nome   = document.getElementById('campo-nome').value.trim();
    const cpf    = document.getElementById('campo-cpf').value.replace(/\D/g,'');
    const cep    = document.getElementById('campo-cep').value.replace(/\D/g,'');
    const rua    = document.getElementById('campo-rua').value.trim();
    const numero = document.getElementById('campo-numero').value.trim();
    const cidade = document.getElementById('campo-cidade').value.trim();

    if (!nome)           return 'Preencha seu nome completo.';
    if (cpf.length !== 11) return 'CPF inválido. Digite os 11 dígitos.';
    if (cep.length !== 8)  return 'CEP inválido.';
    if (!rua)            return 'Preencha o endereço.';
    if (!numero)         return 'Preencha o número do endereço.';
    if (!cidade)         return 'Preencha a cidade.';

    if (metodoPagamento === 'cartao') {
        const cardNum = document.getElementById('cardNumber').value.replace(/\s/g,'');
        const expDate = document.getElementById('expirationDate').value;
        const cvv     = document.getElementById('securityCode').value;
        const nomeC   = document.getElementById('cardholderName').value.trim();
        if (cardNum.length < 13)  return 'Número do cartão inválido.';
        if (expDate.length !== 5) return 'Validade inválida. Use MM/AA.';
        if (cvv.length < 3)       return 'CVV inválido.';
        if (!nomeC)               return 'Digite o nome como está no cartão.';
    }
    return null;
}

// ── FINALIZAR PEDIDO ──────────────────────────────────────
async function finalizarPedido() {
    const msgBox = document.getElementById('msgErro');
    const btn    = document.getElementById('btnFinalizar');

    const erroVal = validar();
    if (erroVal) {
        msgBox.textContent   = '⚠ ' + erroVal;
        msgBox.style.display = 'block';
        msgBox.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }

    msgBox.style.display = 'none';
    btn.disabled         = true;
    btn.textContent      = 'Processando...';

    const payload = {
        metodo:    metodoPagamento,
        nome:      document.getElementById('campo-nome').value.trim(),
        cpf:       document.getElementById('campo-cpf').value.replace(/\D/g,''),
        telefone:  document.getElementById('campo-telefone').value,
        cep:       document.getElementById('campo-cep').value.replace(/\D/g,''),
        rua:       document.getElementById('campo-rua').value.trim(),
        numero:    document.getElementById('campo-numero').value.trim(),
        bairro:    document.getElementById('campo-bairro').value.trim(),
        cidade:    document.getElementById('campo-cidade').value.trim(),
        estado:    document.getElementById('campo-estado').value.trim(),
        carrinho:  carrinhoGlobal,
        parcelas:  parseInt(document.getElementById('selectParcelas')?.value || '1'),
    };

    // Gera tokens do cartão nos dois gateways simultaneamente
    if (metodoPagamento === 'cartao') {
        btn.textContent = 'Validando cartão...';
        try {
            const tokens = await gerarTokens();

            // Pelo menos um token precisa ter sido gerado
            if (!tokens.mp && !tokens.ps) {
                throw new Error('Não foi possível validar os dados do cartão. Verifique e tente novamente.');
            }

            payload.token_mp             = tokens.mp;
            payload.token_ps             = tokens.ps;
            payload.issuer_id            = window._mp_issuer_id || '';
            payload.payment_method_id    = window._mp_payment_method_id || '';

        } catch(e) {
            msgBox.textContent   = '⚠ ' + e.message;
            msgBox.style.display = 'block';
            btn.disabled         = false;
            btn.textContent      = 'Confirmar e Pagar';
            return;
        }
    }

    btn.textContent = 'Finalizando pedido...';

    // Envia ao servidor (processar_pedido_v2.php com fallback MP → PS)
    try {
        const res  = await fetch('processar_pedido.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.sucesso) {
            // Salva dados do gateway na session para a tela de confirmação
            if (data.qr_code || data.qr_base64 || data.boleto_url) {
                sessionStorage.setItem('mp_payment_data', JSON.stringify(data));
            }
            sessionStorage.removeItem('fashion_cart');
            window.location.href = 'pedido_confirmado.php?id=' + data.pedido_id + '&metodo=' + metodoPagamento;
        } else {
            msgBox.textContent   = '⚠ ' + (data.erro || 'Erro ao processar pagamento.');
            msgBox.style.display = 'block';
            btn.disabled         = false;
            btn.textContent      = 'Confirmar e Pagar';
        }
    } catch(e) {
        msgBox.textContent   = '⚠ Erro de conexão. Tente novamente.';
        msgBox.style.display = 'block';
        btn.disabled         = false;
        btn.textContent      = 'Confirmar e Pagar';
    }
}
</script>
</body>
</html>
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
    LEFT JOIN enderecos e ON u.id = e.usuario_id
    WHERE u.id = :id
");
$stmt->execute([':id' => $_SESSION['usuario_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <!-- SDK JS do Mercado Pago (tokeniza o cartão no navegador) -->
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <style>
        body { background:#fff; color:#000; font-family:'Inter',sans-serif; }

        .checkout-container {
            max-width: 1100px; margin: 40px auto 100px;
            padding: 0 20px; display: grid;
            grid-template-columns: 1fr 380px; gap: 80px;
        }

        .checkout-form h2 {
            font-size: 2.5rem; font-weight: 900;
            text-transform: uppercase; margin-bottom: 50px; letter-spacing: -2px;
        }

        .section-title {
            font-size: 10px; font-weight: 900; text-transform: uppercase;
            letter-spacing: 3px; color: #bbb; margin: 40px 0 20px;
            display: flex; align-items: center; gap: 15px;
        }
        .section-title::after { content:""; flex:1; height:1px; background:#efefef; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block; font-weight: 800; font-size: 11px;
            margin-bottom: 8px; text-transform: uppercase;
        }
        .form-group input, .form-group select {
            width: 100%; padding: 16px; border: 1.5px solid #efefef;
            border-radius: 12px; font-size: 14px; font-family: inherit;
            background: #fbfbfb; transition: .3s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #000; background: #fff; outline: none;
        }
        .form-group input[readonly] { opacity: .6; cursor: not-allowed; background: #f0f0f0; }

        /* ── SELETOR DE PAGAMENTO ── */
        .metodos-pagamento { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 28px; }

        .metodo-btn {
            border: 2px solid #eee; border-radius: 16px; padding: 18px 10px;
            text-align: center; cursor: pointer; transition: .3s; background: #fff;
        }
        .metodo-btn:hover  { border-color: #000; }
        .metodo-btn.active { border-color: #000; background: #000; color: #fff; }
        .metodo-btn .icon  { font-size: 26px; display: block; margin-bottom: 8px; }
        .metodo-btn .label { font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .metodo-btn .sub   { font-size: 10px; opacity: .6; margin-top: 3px; }

        /* ── PAINEL PIX ── */
        #painel-pix { display: none; }
        .pix-info {
            background: #f9f9f9; border-radius: 16px; padding: 20px;
            border-left: 4px solid #000; font-size: 13px; line-height: 1.7;
        }

        /* ── PAINEL BOLETO ── */
        #painel-boleto { display: none; }
        .boleto-info {
            background: #f9f9f9; border-radius: 16px; padding: 20px;
            font-size: 13px; line-height: 1.7;
        }

        /* ── PAINEL CARTÃO ── */
        #painel-cartao { display: none; }

        /* Campos do cartão (estilizados para parecer os inputs nativos) */
        #cardNumber, #expirationDate, #securityCode, #cardholderName {
            width: 100%; padding: 16px; border: 1.5px solid #efefef;
            border-radius: 12px; font-size: 14px; background: #fbfbfb;
            font-family: inherit; transition: .3s;
        }

        .parcelas-select {
            width: 100%; padding: 16px; border: 1.5px solid #efefef;
            border-radius: 12px; font-size: 14px; font-family: inherit;
            background: #fbfbfb;
        }

        .card-flag {
            width: 40px; height: 26px; object-fit: contain;
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
        }

        .input-wrapper { position: relative; }

        /* ── BOTÃO FINALIZAR ── */
        .btn-finalizar {
            width: 100%; padding: 22px; background: #000; color: #fff;
            border: none; border-radius: 50px; font-weight: 900; font-size: 13px;
            letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
            transition: .4s; margin-top: 20px;
        }
        .btn-finalizar:hover { background: #333; transform: translateY(-3px); }
        .btn-finalizar:disabled { opacity: .5; cursor: not-allowed; transform: none; }

        /* ── MENSAGEM DE ERRO ── */
        .msg-erro {
            background: #fff0f0; border: 1px solid #ffc0c0; color: #c62828;
            padding: 14px 18px; border-radius: 12px; font-size: 13px;
            font-weight: 600; margin-top: 16px; display: none;
        }

        /* ── RESUMO ── */
        .resumo-pedido {
            background: #fff; padding: 35px; border-radius: 30px;
            border: 1px solid #efefef; position: sticky; top: 120px; height: fit-content;
        }
        .item-checkout { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; }
        .item-checkout img { width: 60px; height: 60px; object-fit: cover; background: #f5f5f5; border-radius: 10px; }
        .item-info h4 { font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 0; }
        .item-info p  { font-size: 10px; color: #888; margin: 2px 0; }
        .summary-totals { margin-top: 25px; padding-top: 25px; border-top: 1px solid #eee; }
        .total-line { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
        .total-line.final { font-size: 24px; font-weight: 900; margin-top: 15px; border-top: 2px solid #000; padding-top: 15px; }

        .desconto-pix {
            background: #e8f5e9; color: #2e7d32; padding: 8px 14px;
            border-radius: 8px; font-size: 12px; font-weight: 700;
            margin-top: 8px; display: none;
        }

        @media (max-width:900px) {
            .checkout-container { grid-template-columns: 1fr; }
            .metodos-pagamento  { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<main class="checkout-container">
    <section class="checkout-form">
        <h2>Finalizar Pedido</h2>

        <!-- DADOS DO COMPRADOR -->
        <span class="section-title">Dados do Comprador</span>
        <div class="form-row">
            <div class="form-group">
                <label>Nome Completo</label>
                <input type="text" id="campo-nome" placeholder="Seu nome completo"
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
                <label>CPF <span style="color:#c00;">*</span></label>
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
                <input type="text" id="campo-cep" placeholder="00000-000" maxlength="9"
                       value="<?= htmlspecialchars($user['cep'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Estado (UF)</label>
                <input type="text" id="campo-estado" placeholder="SP" maxlength="2"
                       value="<?= htmlspecialchars($user['estado'] ?? '') ?>">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:2.5fr 1fr; gap:20px;">
            <div class="form-group">
                <label>Rua / Logradouro</label>
                <input type="text" id="campo-rua" placeholder="Ex: Av. Paulista"
                       value="<?= htmlspecialchars($user['rua'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Número</label>
                <input type="text" id="campo-numero" placeholder="123"
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
                <input type="text" id="campo-cidade"
                       value="<?= htmlspecialchars($user['cidade'] ?? '') ?>">
            </div>
        </div>

        <!-- MÉTODO DE PAGAMENTO -->
        <span class="section-title">Forma de Pagamento</span>

        <div class="metodos-pagamento">
            <div class="metodo-btn active" onclick="selecionarMetodo('pix', this)">
                <span class="icon">⚡</span>
                <div class="label">PIX</div>
                <div class="sub">5% de desconto</div>
            </div>
            <div class="metodo-btn" onclick="selecionarMetodo('cartao', this)">
                <span class="icon">💳</span>
                <div class="label">Cartão</div>
                <div class="sub">Até 12x</div>
            </div>
            <div class="metodo-btn" onclick="selecionarMetodo('boleto', this)">
                <span class="icon">🏦</span>
                <div class="label">Boleto</div>
                <div class="sub">Vence em 3 dias</div>
            </div>
        </div>

        <!-- PAINEL PIX -->
        <div id="painel-pix">
            <div class="pix-info">
                ⚡ <strong>Pagamento via PIX</strong> — aprovação em até 1 minuto.<br>
                Após confirmar o pedido, você receberá o QR Code e o código copia-e-cola.
                O PIX expira em <strong>30 minutos</strong>.
            </div>
        </div>

        <!-- PAINEL BOLETO -->
        <div id="painel-boleto">
            <div class="boleto-info">
                🏦 <strong>Boleto Bancário</strong><br>
                O boleto vence em 3 dias úteis. O pedido só é confirmado após a compensação
                (1 a 3 dias úteis). Após confirmar, você poderá imprimir ou copiar o código de barras.
            </div>
        </div>

        <!-- PAINEL CARTÃO -->
        <div id="painel-cartao">
            <div class="form-group input-wrapper">
                <label>Número do Cartão</label>
                <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19">
                <img id="cardFlag" class="card-flag" src="" alt="" style="display:none;">
            </div>
            <div class="form-group">
                <label>Nome no Cartão</label>
                <input type="text" id="cardholderName" placeholder="Como está impresso no cartão"
                       style="text-transform:uppercase;">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Validade (MM/AA)</label>
                    <input type="text" id="expirationDate" placeholder="MM/AA" maxlength="5">
                </div>
                <div class="form-group">
                    <label>CVV</label>
                    <input type="text" id="securityCode" placeholder="000" maxlength="4">
                </div>
            </div>
            <div class="form-group">
                <label>Parcelamento</label>
                <select class="parcelas-select" id="selectParcelas">
                    <option value="1">1x sem juros</option>
                </select>
            </div>
            <div class="form-group">
                <label>CPF do Titular (se diferente)</label>
                <input type="text" id="cardholderCpf" placeholder="000.000.000-00" maxlength="14">
            </div>
        </div>

        <div class="msg-erro" id="msgErro"></div>

        <button class="btn-finalizar" id="btnFinalizar" onclick="finalizarPedido()">
            Confirmar e Pagar
        </button>
    </section>

    <!-- RESUMO DO PEDIDO -->
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
                <span style="color:#27ae60; font-weight:800; font-size:11px;">GRÁTIS (EXPRESS)</span>
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
// ── CONFIGURAÇÃO MP ───────────────────────────────────────
const MP_PUBLIC_KEY = '<?= MP_PUBLIC_KEY ?>';
const mp = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });

let metodoPagamento = 'pix';
let carrinhoGlobal  = [];
let totalBruto      = 0;

// ── INICIALIZAÇÃO ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    carrinhoGlobal = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];

    if (!carrinhoGlobal.length) { window.location.href = 'index.php'; return; }

    renderizarResumo();
    selecionarMetodo('pix', document.querySelector('.metodo-btn.active'));
    setupCEP();
    setupMascaras();
});

// ── RENDERIZAR RESUMO ─────────────────────────────────────
function renderizarResumo() {
    totalBruto = 0;
    const lista = document.getElementById('listaCheckout');

    lista.innerHTML = carrinhoGlobal.map(item => {
        totalBruto += parseFloat(item.preco) * item.qtd;
        return `
            <div class="item-checkout">
                <img src="${item.img}" alt="${item.nome}" onerror="this.src='img/produtos/default.jpg'">
                <div class="item-info">
                    <h4>${item.nome}</h4>
                    <p>${item.opcoes || ''}</p>
                    <span>${item.qtd}x R$ ${parseFloat(item.preco).toLocaleString('pt-br',{minimumFractionDigits:2})}</span>
                </div>
            </div>`;
    }).join('');

    atualizarTotal();
}

function atualizarTotal() {
    let total = totalBruto;
    const descontoPix = document.getElementById('descontoPix');
    const valorDesc   = document.getElementById('valorDesconto');

    if (metodoPagamento === 'pix') {
        const desc = totalBruto * 0.05;
        total -= desc;
        descontoPix.style.display = 'block';
        valorDesc.textContent = '- R$ ' + desc.toLocaleString('pt-br',{minimumFractionDigits:2});
    } else {
        descontoPix.style.display = 'none';
    }

    document.getElementById('subtotalCheckout').textContent = 'R$ ' + totalBruto.toLocaleString('pt-br',{minimumFractionDigits:2});
    document.getElementById('totalCheckout').textContent    = 'R$ ' + total.toLocaleString('pt-br',{minimumFractionDigits:2});
}

// ── SELECIONAR MÉTODO ─────────────────────────────────────
function selecionarMetodo(metodo, el) {
    metodoPagamento = metodo;

    document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');

    document.getElementById('painel-pix').style.display    = metodo === 'pix'    ? 'block' : 'none';
    document.getElementById('painel-boleto').style.display = metodo === 'boleto' ? 'block' : 'none';
    document.getElementById('painel-cartao').style.display = metodo === 'cartao' ? 'block' : 'none';

    atualizarTotal();
}

// ── MÁSCARAS ──────────────────────────────────────────────
function setupMascaras() {
    // CPF
    ['campo-cpf','cardholderCpf'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', e => {
            let v = e.target.value.replace(/\D/g,'');
            v = v.replace(/(\d{3})(\d)/,'$1.$2')
                 .replace(/(\d{3})(\d)/,'$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/,'$1-$2');
            e.target.value = v;
        });
    });

    // Número do cartão
    document.getElementById('cardNumber').addEventListener('input', async e => {
        let v = e.target.value.replace(/\D/g,'').substring(0,16);
        e.target.value = v.replace(/(.{4})/g,'$1 ').trim();
        if (v.length >= 6) await detectarBandeira(v);
        if (v.length === 16) await carregarParcelas(v);
    });

    // Validade
    document.getElementById('expirationDate').addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g,'');
        if (v.length > 2) v = v.substring(0,2) + '/' + v.substring(2,4);
        e.target.value = v;
    });

    // CVV apenas números
    document.getElementById('securityCode').addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g,'');
    });

    // Nome em maiúsculas
    document.getElementById('cardholderName').addEventListener('input', e => {
        e.target.value = e.target.value.toUpperCase();
    });

    // CEP
    document.getElementById('campo-cep').addEventListener('input', e => {
        let v = e.target.value.replace(/\D/g,'');
        if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5,8);
        e.target.value = v;
    });
}

// ── CEP (ViaCEP) ──────────────────────────────────────────
function setupCEP() {
    document.getElementById('campo-cep').addEventListener('blur', async () => {
        const cep = document.getElementById('campo-cep').value.replace(/\D/g,'');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
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

// ── DETECTAR BANDEIRA DO CARTÃO ───────────────────────────
async function detectarBandeira(numero) {
    try {
        const metodos = await mp.getPaymentMethods({ bin: numero.substring(0,6) });
        const flag    = document.getElementById('cardFlag');
        if (metodos.results?.length) {
            const m = metodos.results[0];
            window._issuer_id        = m.issuer?.id;
            window._payment_method_id = m.id;
            flag.src     = m.thumbnail;
            flag.style.display = 'block';
        }
    } catch(e) {}
}

// ── CARREGAR PARCELAS ─────────────────────────────────────
async function carregarParcelas(numero) {
    const total = totalBruto;
    try {
        const parcelas = await mp.getInstallments({
            amount: String(total),
            bin:    numero.substring(0,6),
        });

        const select = document.getElementById('selectParcelas');
        select.innerHTML = '';

        if (parcelas[0]?.payer_costs) {
            parcelas[0].payer_costs.forEach(p => {
                const opt     = document.createElement('option');
                opt.value     = p.installments;
                opt.textContent = p.recommended_message;
                select.appendChild(opt);
            });
        }
    } catch(e) {}
}

// ── VALIDAR CAMPOS ────────────────────────────────────────
function validar() {
    const nome  = document.getElementById('campo-nome').value.trim();
    const cpf   = document.getElementById('campo-cpf').value.replace(/\D/g,'');
    const cep   = document.getElementById('campo-cep').value.replace(/\D/g,'');
    const rua   = document.getElementById('campo-rua').value.trim();
    const num   = document.getElementById('campo-numero').value.trim();
    const cidade= document.getElementById('campo-cidade').value.trim();

    if (!nome)          return 'Preencha seu nome completo.';
    if (cpf.length!==11)return 'CPF inválido. Digite os 11 dígitos.';
    if (cep.length!==8) return 'CEP inválido.';
    if (!rua)           return 'Preencha o endereço.';
    if (!num)           return 'Preencha o número do endereço.';
    if (!cidade)        return 'Preencha a cidade.';

    if (metodoPagamento === 'cartao') {
        const cardNum = document.getElementById('cardNumber').value.replace(/\s/g,'');
        const expDate = document.getElementById('expirationDate').value;
        const cvv     = document.getElementById('securityCode').value;
        const nome_c  = document.getElementById('cardholderName').value.trim();

        if (cardNum.length < 13)  return 'Número do cartão inválido.';
        if (expDate.length !== 5) return 'Validade inválida. Use o formato MM/AA.';
        if (cvv.length < 3)       return 'CVV inválido.';
        if (!nome_c)              return 'Digite o nome como está no cartão.';
    }

    return null;
}

// ── FINALIZAR PEDIDO ──────────────────────────────────────
async function finalizarPedido() {
    const erro = validar();
    const msgBox = document.getElementById('msgErro');
    const btn    = document.getElementById('btnFinalizar');

    if (erro) {
        msgBox.textContent    = '⚠ ' + erro;
        msgBox.style.display  = 'block';
        msgBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    msgBox.style.display = 'none';
    btn.disabled         = true;
    btn.textContent      = 'Processando...';

    // Monta o payload base
    const payload = {
        metodo:       metodoPagamento,
        nome:         document.getElementById('campo-nome').value.trim(),
        cpf:          document.getElementById('campo-cpf').value.replace(/\D/g,''),
        cep:          document.getElementById('campo-cep').value.replace(/\D/g,''),
        rua:          document.getElementById('campo-rua').value.trim(),
        numero:       document.getElementById('campo-numero').value.trim(),
        bairro:       document.getElementById('campo-bairro').value.trim(),
        cidade:       document.getElementById('campo-cidade').value.trim(),
        estado:       document.getElementById('campo-estado').value.trim(),
        carrinho:     carrinhoGlobal,
    };

    // Para cartão: gera o token no browser (nunca envia dados do cartão ao servidor)
    if (metodoPagamento === 'cartao') {
        try {
            const expArr = document.getElementById('expirationDate').value.split('/');
            const token  = await mp.createCardToken({
                cardNumber:          document.getElementById('cardNumber').value.replace(/\s/g,''),
                cardholderName:      document.getElementById('cardholderName').value,
                cardExpirationMonth: expArr[0],
                cardExpirationYear:  '20' + expArr[1],
                securityCode:        document.getElementById('securityCode').value,
                identificationType:  'CPF',
                identificationNumber: (document.getElementById('cardholderCpf').value || document.getElementById('campo-cpf').value).replace(/\D/g,''),
            });

            payload.token             = token.id;
            payload.parcelas          = parseInt(document.getElementById('selectParcelas').value);
            payload.issuer_id         = window._issuer_id || '';
            payload.payment_method_id = window._payment_method_id || '';

        } catch(e) {
            msgBox.textContent   = '⚠ Erro ao processar cartão: ' + (e.message || 'Verifique os dados e tente novamente.');
            msgBox.style.display = 'block';
            btn.disabled         = false;
            btn.textContent      = 'Confirmar e Pagar';
            return;
        }
    }

    // Envia ao servidor
    try {
        const res  = await fetch('processar_pedido.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(payload),
        });
        const data = await res.json();

        if (data.sucesso) {
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
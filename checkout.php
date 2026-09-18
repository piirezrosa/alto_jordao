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

// Endereço pode estar na tabela usuarios (legado) ou enderecos (novo)
// Prioriza tabela enderecos, cai para usuarios se não tiver
$end_cep    = $user['cep']    ?? '';
$end_rua    = $user['rua']    ?? $user['endereco'] ?? '';
$end_numero = $user['numero'] ?? '';
$end_bairro = $user['bairro'] ?? '';
$end_cidade = $user['cidade'] ?? '';
$end_estado = $user['estado'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Compra | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { background:#fff; color:#000; font-family:'Inter',sans-serif; }

        .checkout-container {
            max-width: 1100px; margin: 40px auto 100px;
            padding: 0 20px; display: grid;
            grid-template-columns: 1fr 380px; gap: 80px;
        }

        .checkout-form h2 {
            font-size: 2.5rem; font-weight: 900;
            text-transform: uppercase; margin-bottom: 50px;
            letter-spacing: -2px;
        }

        .section-title {
            font-size: 10px; font-weight: 900; text-transform: uppercase;
            letter-spacing: 3px; color: #bbb; margin: 40px 0 20px;
            display: flex; align-items: center; gap: 15px;
        }
        .section-title::after { content:''; flex:1; height:1px; background:#efefef; }

        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block; font-weight:800; font-size:11px; margin-bottom:8px; text-transform:uppercase; }
        .form-group input,
        .form-group select {
            width:100%; padding:16px; border:1.5px solid #efefef; border-radius:12px;
            font-size:14px; font-family:inherit; background:#fbfbfb;
            transition:.3s; box-sizing:border-box;
        }
        .form-group input:focus,
        .form-group select:focus { border-color:#000; background:#fff; outline:none; }
        .form-group input[readonly] { opacity:.6; cursor:not-allowed; }

        /* ── SELETOR DE PAGAMENTO ── */
        .metodos-pagamento { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:10px; }
        .metodo-label { display:none; }
        .metodo-card {
            border:2px solid #eee; border-radius:16px; padding:18px 10px;
            text-align:center; cursor:pointer; transition:.3s; background:#fff;
            user-select:none;
        }
        .metodo-card:hover { border-color:#aaa; }
        .metodo-card .icon  { font-size:26px; display:block; margin-bottom:8px; pointer-events:none; }
        .metodo-card .label { font-size:12px; font-weight:800; text-transform:uppercase; pointer-events:none; }
        .metodo-card .sub   { font-size:10px; color:#888; margin-top:3px; pointer-events:none; }

        /* Ativo via JS */
        .metodo-card.ativo { border-color:#000; background:#000; color:#fff; }
        .metodo-card.ativo .sub { color:#aaa; }

        /* ── BOTÃO FINALIZAR ── */
        .btn-finalizar {
            width:100%; padding:22px; background:#000; color:#fff; border:none;
            border-radius:50px; font-weight:900; font-size:13px; letter-spacing:2px;
            text-transform:uppercase; cursor:pointer; transition:.4s; margin-top:20px;
        }
        .btn-finalizar:hover { background:#333; transform:translateY(-3px); box-shadow:0 15px 30px rgba(0,0,0,.15); }
        .btn-finalizar:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }

        /* ── RESUMO ── */
        .resumo-pedido {
            background:#fff; padding:35px; border-radius:30px;
            border:1px solid #efefef; position:sticky; top:120px; height:fit-content;
        }
        .item-checkout { display:flex; gap:15px; margin-bottom:20px; align-items:center; }
        .item-checkout img { width:60px; height:60px; object-fit:cover; background:#f5f5f5; border-radius:10px; }
        .item-info h4 { font-size:12px; font-weight:800; text-transform:uppercase; margin:0; }
        .item-info p  { font-size:10px; color:#888; margin:2px 0; font-weight:600; }
        .item-info span { font-size:12px; font-weight:700; }
        .summary-totals { margin-top:25px; padding-top:25px; border-top:1px solid #eee; }
        .total-line { display:flex; justify-content:space-between; margin-bottom:10px; font-size:14px; }
        .total-line.final { font-size:24px; font-weight:900; margin-top:15px; border-top:2px solid #000; padding-top:15px; }
        .desconto-pix { background:#e8f5e9; color:#2e7d32; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:700; margin-top:6px; display:none; }

        /* ── AVISO SEM GATEWAY ── */
        .aviso-gateway {
            background:#fff8e1; border:1px solid #ffe082; border-radius:14px;
            padding:16px 18px; font-size:13px; color:#b45309; line-height:1.6;
            margin-bottom:20px;
        }
        .aviso-gateway strong { display:block; margin-bottom:4px; }

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

        <?php
        // Verifica se gateway está configurado
        $mp_configurado = defined('MP_ACCESS_TOKEN') && MP_ACCESS_TOKEN !== 'SEU_ACCESS_TOKEN_AQUI' && MP_ACCESS_TOKEN !== '';
        $ps_configurado = defined('PS_TOKEN') && PS_TOKEN !== 'SEU_TOKEN_PAGSEGURO_AQUI' && PS_TOKEN !== '';
        $gateway_ok = $mp_configurado || $ps_configurado;

        if (!$gateway_ok):
        ?>
        <div class="aviso-gateway">
            <strong>⚠ Gateway de pagamento não configurado</strong>
            O pedido será registrado normalmente, mas o pagamento real não será processado até que
            você configure as credenciais do Mercado Pago ou PagSeguro em
            <code>mercadopago.php</code> / <code>pagseguro.php</code>.
        </div>
        <?php endif; ?>

        <form action="processar_pedido.php" method="POST" id="formCheckout">

            <!-- DADOS DO COMPRADOR -->
            <span class="section-title">Dados do Comprador</span>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" required placeholder="Seu nome completo"
                           value="<?= htmlspecialchars($user['nome'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>E-mail</label>
                    <input type="email" name="email" required
                           value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>CPF</label>
                    <input type="text" name="cpf" id="campoCpf" placeholder="000.000.000-00"
                           maxlength="14" value="<?= htmlspecialchars($user['cpf'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" placeholder="(00) 00000-0000"
                           value="<?= htmlspecialchars($user['telefone'] ?? '') ?>">
                </div>
            </div>

            <!-- ENDEREÇO -->
            <span class="section-title">Endereço de Entrega</span>
            <div class="form-row">
                <div class="form-group">
                    <label>CEP</label>
                    <input type="text" id="campo-cep" name="cep" required
                           placeholder="00000-000" maxlength="9"
                           value="<?= htmlspecialchars($end_cep) ?>">
                </div>
                <div class="form-group">
                    <label>Estado (UF)</label>
                    <input type="text" id="campo-estado" name="estado" required
                           placeholder="SP" maxlength="2"
                           value="<?= htmlspecialchars($end_estado) ?>">
                </div>
            </div>
            <div style="display:grid; grid-template-columns:2.5fr 1fr; gap:20px;">
                <div class="form-group">
                    <label>Rua / Logradouro</label>
                    <input type="text" id="campo-rua" name="rua" required placeholder="Ex: Av. Paulista"
                           value="<?= htmlspecialchars($end_rua) ?>">
                </div>
                <div class="form-group">
                    <label>Número</label>
                    <input type="text" id="campo-numero" name="numero" required placeholder="123"
                           value="<?= htmlspecialchars($end_numero) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Bairro</label>
                    <input type="text" id="campo-bairro" name="bairro"
                           value="<?= htmlspecialchars($end_bairro) ?>">
                </div>
                <div class="form-group">
                    <label>Cidade</label>
                    <input type="text" id="campo-cidade" name="cidade" required
                           value="<?= htmlspecialchars($end_cidade) ?>">
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

            <!-- Campo oculto que envia o método para o servidor -->
            <input type="hidden" name="pagamento" id="campoPagamento" value="pix">

            <!-- Campo oculto com dados do carrinho (preenchido pelo JS) -->
            <input type="hidden" name="carrinho_dados" id="inputCarrinhoDados">

            <button type="submit" class="btn-finalizar" id="btnFinalizar">
                Confirmar e Pagar
            </button>
        </form>
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
            <div class="desconto-pix" id="descontoPix">🎉 Desconto PIX 5%: <span id="valorDesconto"></span></div>
            <div class="total-line final">
                <span>TOTAL</span>
                <span id="totalCheckout">R$ 0,00</span>
            </div>
        </div>
    </aside>
</main>

<script>
// ── MÉTODO DE PAGAMENTO ───────────────────────────────────
function selecionarMetodo(metodo, el) {
    document.querySelectorAll('.metodo-card').forEach(c => c.classList.remove('ativo'));
    el.classList.add('ativo');
    document.getElementById('campoPagamento').value = metodo;
    atualizarTotal();
}

// ── TOTAIS ────────────────────────────────────────────────
let totalBruto = 0;

function atualizarTotal() {
    const metodo      = document.getElementById('campoPagamento').value;
    const descPix     = document.getElementById('descontoPix');
    const valDesc     = document.getElementById('valorDesconto');
    let total = totalBruto;

    if (metodo === 'pix' && totalBruto > 0) {
        const desc = totalBruto * 0.05;
        total -= desc;
        descPix.style.display = 'block';
        valDesc.textContent   = '- R$ ' + desc.toLocaleString('pt-br', {minimumFractionDigits:2});
    } else {
        descPix.style.display = 'none';
    }

    document.getElementById('subtotalCheckout').textContent =
        'R$ ' + totalBruto.toLocaleString('pt-br', {minimumFractionDigits:2});
    document.getElementById('totalCheckout').textContent =
        'R$ ' + total.toLocaleString('pt-br', {minimumFractionDigits:2});
}

// ── MÁSCARA CPF ───────────────────────────────────────────
document.getElementById('campoCpf').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    v = v.replace(/(\d{3})(\d)/,'$1.$2')
         .replace(/(\d{3})(\d)/,'$1.$2')
         .replace(/(\d{3})(\d{1,2})$/,'$1-$2');
    this.value = v;
});

// ── CEP (ViaCEP) ──────────────────────────────────────────
document.getElementById('campo-cep').addEventListener('input', function() {
    let v = this.value.replace(/\D/g,'');
    if (v.length > 5) v = v.slice(0,5) + '-' + v.slice(5,8);
    this.value = v;
});

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

// ── CARRINHO ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart') || '[]');

    if (!carrinho.length) {
        window.location.href = 'index.php';
        return;
    }

    // Preenche dados ocultos do carrinho para o servidor
    document.getElementById('inputCarrinhoDados').value = JSON.stringify(carrinho);

    // Renderiza lista de itens
    const lista = document.getElementById('listaCheckout');
    totalBruto  = 0;

    lista.innerHTML = carrinho.map(function(item) {
        totalBruto += parseFloat(item.preco) * (item.qtd || 1);
        return '<div class="item-checkout">' +
            '<img src="' + (item.img || '') + '" alt="' + item.nome + '" onerror="this.style.opacity=\'0\'">' +
            '<div class="item-info">' +
                '<h4>' + item.nome + '</h4>' +
                '<p>' + (item.tamanho_escolhido || '') + (item.cor_escolhida ? ' | ' + item.cor_escolhida : '') + '</p>' +
                '<span>' + (item.qtd || 1) + 'x R$ ' + parseFloat(item.preco).toLocaleString('pt-br', {minimumFractionDigits:2}) + '</span>' +
            '</div></div>';
    }).join('');

    atualizarTotal();

    // Validação do form antes de enviar
    document.getElementById('formCheckout').addEventListener('submit', function(e) {
        const btn = document.getElementById('btnFinalizar');
        const cpf = document.getElementById('campoCpf').value.replace(/\D/g,'');
        const cep = document.getElementById('campo-cep').value.replace(/\D/g,'');

        if (cpf.length > 0 && cpf.length !== 11) {
            e.preventDefault();
            alert('CPF inválido. Verifique o número digitado.');
            return;
        }
        if (cep.length > 0 && cep.length !== 8) {
            e.preventDefault();
            alert('CEP inválido. Verifique o número digitado.');
            return;
        }

        // Desabilita botão para evitar duplo clique
        btn.disabled    = true;
        btn.textContent = 'Processando...';
    });
});
</script>
</body>
</html>
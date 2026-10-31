<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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
                <p>Pedidos ainda não enviados podem ser cancelados. Fale com a gente pelo e-mail abaixo informando o número do pedido.</p>
            </details>
            <details>
                <summary>Como uso um cupom?</summary>
                <p>No checkout, digite o código no campo Cupom de desconto, no resumo do pedido, e toque em Aplicar. O desconto aparece no total na hora.</p>
            </details>
        </div>
    </section>

    <section class="aj-contact">
        <div>
            <h2>Ainda com dúvidas?</h2>
            <p>Escreva para a nossa equipe.</p>
        </div>
        <a class="aj-btn" href="mailto:suporte@altojordao.com.br">Enviar e-mail</a>
    </section>
</main>

<?php include 'footer.php'; ?>
<script src="script.js?v=<?= time() ?>"></script>
</body>
</html>

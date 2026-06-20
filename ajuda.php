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
    <title>Central de Ajuda | Alto Jordão</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #000;
            --glass: rgba(255, 255, 255, 0.8);
            --border: #f0f0f0;
        }

        .help-hero {
            background: #fff;
            padding: 100px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .help-hero h1 {
            font-size: 3.5rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -2px;
            margin-bottom: 20px;
        }

        .help-container {
            max-width: 1000px;
            margin: -60px auto 100px;
            padding: 0 20px;
        }

        .help-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .help-card {
            background: #fff;
            padding: 40px;
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.02);
            transition: 0.3s;
        }

        .help-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0,0,0,0.05);
        }

        .help-card h3 {
            font-size: 1.2rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .help-card p {
            color: #666;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .faq-section {
            margin-top: 80px;
        }

        .faq-item {
            border-bottom: 1px solid var(--border);
            padding: 25px 0;
            cursor: pointer;
        }

        .faq-question {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: 0.3s ease-out;
            color: #777;
            margin-top: 0;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
            margin-top: 15px;
        }

        .contact-footer {
            background: #f9f9f9;
            padding: 80px 20px;
            text-align: center;
            border-radius: 50px;
            margin: 0 20px 60px;
        }

        .btn-contact {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 20px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 30px;
            transition: 0.3s;
        }

        .btn-contact:hover {
            transform: scale(1.05);
            background: #333;
        }
    </style>
</head>
<body>

<?php include 'header.php'; ?>

<section class="help-hero">
    <span class="brand-tag" style="letter-spacing: 5px; color: #bbb;">Suporte ao Cliente</span>
    <h1>Como podemos ajudar?</h1>
</section>

<div class="help-container">
    <div class="help-grid">
        <div class="help-card">
            <h3><span>📦</span> Envios & Prazos</h3>
            <p>Nossos produtos "Originals" possuem entrega premium. O prazo médio de entrega para capitais é de 3 a 5 dias úteis após a confirmação.</p>
        </div>

        <div class="help-card">
            <h3><span>🔄</span> Trocas e Devoluções</h3>
            <p>Não serviu? Você tem até 7 dias após o recebimento para solicitar a troca gratuita. O produto deve estar com a tag original.</p>
        </div>

        <div class="help-card">
            <h3><span>🛡️</span> Pagamento Seguro</h3>
            <p>Aceitamos todos os cartões, PIX e boleto. Nossa plataforma utiliza criptografia de ponta para proteger seus dados financeiros.</p>
        </div>
    </div>

    <section class="faq-section">
        <h2 style="font-weight: 900; text-transform: uppercase; margin-bottom: 40px;">Dúvidas Frequentes</h2>
        
        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">Como rastrear meu pedido? <span>+</span></div>
            <div class="faq-answer">
                Após o despacho, você receberá um e-mail com o código de rastreio. Você também pode consultar em "Meus Pedidos" no seu perfil.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">Os produtos são originais? <span>+</span></div>
            <div class="faq-answer">
                Sim. Todos os itens da Alto Jordão são produzidos com materiais de alta qualidade e possuem certificado de autenticidade da marca.
            </div>
        </div>

        <div class="faq-item" onclick="toggleFaq(this)">
            <div class="faq-question">Como escolher o tamanho correto? <span>+</span></div>
            <div class="faq-answer">
                Na página de cada produto, disponibilizamos uma tabela de medidas. Recomendamos medir uma peça que você já possui para comparar.
            </div>
        </div>
    </section>
</div>

<section class="contact-footer">
    <h2 style="font-weight: 900; text-transform: uppercase;">Ainda com dúvidas?</h2>
    <p style="color: #666;">Nossa equipe de especialistas está pronta para te atender.</p>
    <a href="mailto:suporte@altojordao.com.br" class="btn-contact">Fale Conosco via E-mail</a>
</section>

<script>
    function toggleFaq(element) {
        element.classList.toggle('active');
        const span = element.querySelector('span');
        span.innerText = element.classList.contains('active') ? '-' : '+';
    }
</script>

</body>
</html>
/* ==========================================================================
   FASHIONSHOP - SCRIPT GLOBAL (CARRINHO E INTERFACE)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    console.log("FashionShop: Sistema carregado.");

    setupMenuIndicator(); 
    atualizarInterfaceFavoritos();
    renderizarCarrinho(); 
    
    if (document.getElementById('favsGrid')) {
        renderizarPaginaFavoritos();
    }
});

/* --- 1. LÓGICA DE COMPRA DIRETA (PÁGINA DE PRODUTO) --- */

function adicionarAoCarrinhoDireto(produto) {
    const txtCor = document.getElementById('txt-cor-selecionada');
    const selTam = document.getElementById('select-tamanho');

    const corSelecionada = txtCor ? txtCor.innerText : 'Padrão';
    if (corSelecionada === "Selecione") {
        alert("Por favor, selecione uma cor.");
        return;
    }

    const tamSelecionado = selTam ? selTam.value : 'Único';
    if (tamSelecionado === "") {
        alert("Por favor, selecione um tamanho.");
        return;
    }

    // Normalização do caminho da imagem
    const imgRaw = produto.imagem || produto.img || 'placeholder.jpg';
    const finalImg = imgRaw.includes('/') ? imgRaw : "img/" + imgRaw;

    const itemParaCarrinho = {
        id: produto.id,
        nome: produto.nome,
        preco: parseFloat(produto.preco),
        img: finalImg,
        opcoes: `Tam: ${tamSelecionado} | Cor: ${corSelecionada}`
    };

    adicionarAoCarrinho(itemParaCarrinho);
}

/* --- 2. LÓGICA DO CARRINHO (SIDEBAR) --- */

function abrirCarrinho() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar && overlay) {
        sidebar.classList.add('active');
        overlay.classList.add('active');
        renderizarCarrinho();
    }
}

function fecharTodosModais() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if(sidebar) sidebar.classList.remove('active');
    if(overlay) overlay.classList.remove('active');
}

function adicionarAoCarrinho(p) {
    let carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    const cartId = `${p.id}-${p.opcoes}`; 

    const index = carrinho.findIndex(item => item.cartId === cartId);

    if (index > -1) {
        carrinho[index].qtd += 1;
    } else {
        carrinho.push({
            cartId: cartId,
            id: p.id,
            nome: p.nome,
            preco: p.preco,
            img: p.img,
            opcoes: p.opcoes,
            qtd: 1
        });
    }

    sessionStorage.setItem('fashion_cart', JSON.stringify(carrinho));
    renderizarCarrinho();
    abrirCarrinho(); 
}

function renderizarCarrinho() {
    const container = document.getElementById('cartListSide');
    const totalElemento = document.getElementById('totalValor');
    const badge = document.getElementById('cartCountBadge');
    
    if (!container) return;
    
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    let totalGeral = 0;
    let totalItens = 0;
    
    if (carrinho.length === 0) {
        container.innerHTML = '<p style="text-align:center; padding:30px; color:#999;">Sua sacola está vazia.</p>';
        if(totalElemento) totalElemento.innerText = "R$ 0,00";
        if(badge) badge.style.display = "none";
        return;
    }

    container.innerHTML = carrinho.map(item => {
        totalGeral += (item.preco * item.qtd);
        totalItens += item.qtd;
        
        return `
            <div style="display: flex; gap: 12px; padding: 15px 0; border-bottom: 1px solid #eee; align-items: center;">
                <img src="${item.img}" style="width:50px; height:65px; object-fit:cover; border-radius:4px;" onerror="this.src='img/placeholder.jpg'">
                <div style="flex: 1;">
                    <h5 style="margin: 0; font-size: 13px; font-weight:800;">${item.nome}</h5>
                    <p style="margin:0; font-size:10px; color:#999; text-transform:uppercase;">${item.opcoes}</p>
                    <span style="font-weight: 700; font-size: 13px;">${item.qtd}x R$ ${item.preco.toLocaleString('pt-br', {minimumFractionDigits: 2})}</span>
                </div>
                <button onclick="removerDoCarrinho('${item.cartId}')" style="background:none; border:none; color:#ff4d4d; cursor:pointer; font-size:20px; padding:5px;">&times;</button>
            </div>
        `;
    }).join('');

    if(totalElemento) totalElemento.innerText = `R$ ${totalGeral.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
    
    if(badge) {
        badge.innerText = totalItens;
        badge.style.display = totalItens > 0 ? "block" : "none";
    }
}

function removerDoCarrinho(cartId) {
    let carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    carrinho = carrinho.filter(i => i.cartId !== cartId);
    sessionStorage.setItem('fashion_cart', JSON.stringify(carrinho));
    renderizarCarrinho();
}

/* --- 3. LÓGICA DE FAVORITOS (LOCALSTORAGE) --- */

function toggleFavorito(produto) {
    if (!produto || !produto.id) return;

    let favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    const index = favoritos.findIndex(item => String(item.id) === String(produto.id));

    if (index > -1) {
        favoritos.splice(index, 1);
    } else {
        // Correção para o erro de "undefined" na imagem
        const imgRaw = produto.imagem || produto.img || 'placeholder.jpg';
        const finalImg = imgRaw.includes('/') ? imgRaw : "img/" + imgRaw;

        favoritos.push({
            id: produto.id,
            nome: produto.nome,
            preco: produto.preco,
            img: finalImg
        });
    }

    localStorage.setItem('fashion_favs', JSON.stringify(favoritos));
    atualizarInterfaceFavoritos();
    
    if (document.getElementById('favsGrid')) {
        renderizarPaginaFavoritos();
    }
}

function atualizarInterfaceFavoritos() {
    const favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    
    document.querySelectorAll('.btn-fav').forEach(btn => {
        const idProd = btn.getAttribute('data-id'); 
        if (favoritos.some(item => String(item.id) === String(idProd))) {
            btn.classList.add('active');
            btn.innerHTML = '❤️';
            btn.style.color = '#ff4d4d';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '🤍';
            btn.style.color = 'inherit';
        }
    });
}

function renderizarPaginaFavoritos() {
    const container = document.getElementById('favsGrid');
    if (!container) return;
    
    const favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    
    if (favoritos.length === 0) {
        container.innerHTML = `
            <div style="grid-column: 1/-1; text-align:center; padding:80px; color:#999;">
                <h3 style="margin-bottom:15px;">Sua lista de desejos está vazia.</h3>
                <a href="index.php" style="text-decoration:underline; color:#000;">Voltar para a loja</a>
            </div>`;
        return;
    }

    container.innerHTML = favoritos.map(prod => {
        // Garante que o objeto passado para a função seja seguro
        const prodData = JSON.stringify(prod).replace(/'/g, "&apos;");
        
        return `
            <div class="product-card">
                <div class="product-thumb">
                    <button class="btn-fav active" data-id="${prod.id}" 
                       onclick='toggleFavorito(${prodData})'>❤️</button>
                    <img src="${prod.img}" alt="${prod.nome}" onerror="this.src='img/placeholder.jpg'">
                </div>
                <button class="btn-buy-overlay" onclick="location.href='produto.php?id=${prod.id}'">VER PRODUTO</button>
                <div class="product-details">
                    <h4>${prod.nome}</h4>
                    <p class="price">R$ ${parseFloat(prod.preco).toLocaleString('pt-br', {minimumFractionDigits: 2})}</p>
                </div>
            </div>
        `;
    }).join('');
}

/* --- 4. UTILITÁRIOS DE INTERFACE --- */

function setupMenuIndicator() {
    const indicator = document.querySelector('.nav-indicator');
    const items = document.querySelectorAll('.main-nav a');
    
    if (!indicator || items.length === 0) return;

    const moveIndicator = (el) => {
        indicator.style.width = `${el.offsetWidth}px`;
        indicator.style.left = `${el.offsetLeft}px`;
        indicator.style.opacity = "1";
    };

    items.forEach(item => {
        item.addEventListener('mouseenter', () => moveIndicator(item));
        if (item.classList.contains('active')) moveIndicator(item);
    });
}

async function finalizarCompraNoBanco() {
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    
    if (carrinho.length === 0) {
        alert("Seu carrinho está vazio!");
        return;
    }

    try {
        const resposta = await fetch('processar_pedido.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(carrinho)
        });

        const resultado = await resposta.json();

        if (resultado.sucesso) {
            alert("Pedido realizado com sucesso!");
            sessionStorage.removeItem('fashion_cart');
            window.location.href = 'sucesso.php?id=' + resultado.pedido_id;
        } else {
            alert("Erro ao processar: " + resultado.erro);
        }
    } catch (error) {
        console.error("Erro na requisição:", error);
        alert("Erro de conexão com o servidor.");
    }
}


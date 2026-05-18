/* ==========================================================================
   ALTO JORDÃO - SCRIPT GLOBAL (CARRINHO, FAVORITOS E INTERFACE)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    console.log("Alto Jordão: Sistema carregado v2.4");

    verificarERepararFavoritos();
    setupHeaderActions();
    setupMenuIndicator(); 
    setupKidsFilters();
    
    atualizarInterfaceFavoritos();
    renderizarCarrinho(); 
    
    if (document.getElementById('favsGrid')) {
        renderizarPaginaFavoritos();
    }
});

/* --- UTILS --- */

function resolverCaminhoImagem(imgRaw) {
    if (!imgRaw || imgRaw === 'undefined' || imgRaw === '') {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mN8Xw8AAgcBR7Y6968AAAAASUVORK5CYII=';
    }
    if (imgRaw.startsWith('http') || imgRaw.startsWith('data:') || imgRaw.includes('/')) {
        return imgRaw;
    }
    return `img/produtos/${imgRaw}`;
}

function verificarERepararFavoritos() {
    try {
        const favs = localStorage.getItem('fashion_favs');
        if (favs) JSON.parse(favs); 
    } catch (e) {
        localStorage.setItem('fashion_favs', JSON.stringify([]));
    }
}

/* --- 0. CONFIGURAÇÃO DO HEADER --- */
function setupHeaderActions() {
    const overlay = document.getElementById('overlay');
    if (overlay) {
        overlay.addEventListener('click', fecharTodosModais);
    }
}

/* --- 1. LÓGICA DE COMPRA (PRODUTO) --- */

function adicionarAoCarrinhoDireto(produto) {
    const inputTam = document.getElementById('selected-tamanho');
    const inputCor = document.getElementById('selected-cor');
    
    const tamSelecionado = inputTam ? inputTam.value : "";
    const corSelecionada = inputCor ? inputCor.value : "";

    if (!tamSelecionado) {
        alert("Por favor, selecione um tamanho antes de adicionar.");
        return;
    }

    const itemParaCarrinho = {
        id: produto.id,
        nome: produto.nome,
        preco: parseFloat(produto.preco),
        img: produto.imagem || produto.img,
        tamanho_escolhido: tamSelecionado,
        cor_escolhida: corSelecionada || 'Padrão',
        opcoes: `Tam: ${tamSelecionado}${corSelecionada ? ' | Cor: ' + corSelecionada : ''}`
    };

    adicionarAoCarrinho(itemParaCarrinho);
}

/* --- 2. LÓGICA DO CARRINHO --- */
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
    const cartId = `${p.id}-${p.tamanho_escolhido}-${p.cor_escolhida}`; 
    const index = carrinho.findIndex(item => item.cartId === cartId);

    if (index > -1) {
        carrinho[index].qtd += 1;
    } else {
        carrinho.push({ ...p, cartId: cartId, qtd: 1 });
    }

    sessionStorage.setItem('fashion_cart', JSON.stringify(carrinho));
    renderizarCarrinho();
    abrirCarrinho(); 
}

function renderizarCarrinho() {
    const container = document.getElementById('cartListSide');
    const totalElemento = document.getElementById('totalValor');
    const badge = document.getElementById('cartCountBadge');
    
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    let totalGeral = 0;
    
    if (badge) {
        const totalItens = carrinho.reduce((acc, item) => acc + (parseInt(item.qtd) || 0), 0);
        badge.innerText = totalItens;
        badge.style.display = totalItens > 0 ? "flex" : "none";
    }

    if (!container) return;
    
    if (carrinho.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:40px; color:#bbb; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px;">Sua sacola está vazia.</div>';
        if(totalElemento) totalElemento.innerText = "R$ 0,00";
        return;
    }

    container.innerHTML = carrinho.map(item => {
        const precoNum = parseFloat(item.preco) || 0;
        const qtdNum = parseInt(item.qtd) || 1;
        totalGeral += (precoNum * qtdNum);

        return `
            <div class="cart-item" style="display: flex; gap: 15px; padding: 20px 0; border-bottom: 1px solid #f2f2f2; align-items: center;">
                <img src="${resolverCaminhoImagem(item.img)}" style="width:70px; height:90px; object-fit:cover; background:#f9f9f9;">
                <div style="flex: 1;">
                    <h5 style="margin: 0; font-size: 11px; font-weight:900; text-transform:uppercase; letter-spacing:1px;">${item.nome}</h5>
                    <p style="margin:4px 0; font-size:10px; color:#aaa; font-weight:700; text-transform:uppercase;">${item.opcoes}</p>
                    <span style="font-weight: 800; font-size: 13px; color:#000;">${qtdNum}x R$ ${precoNum.toLocaleString('pt-br', {minimumFractionDigits: 2})}</span>
                </div>
                <button onclick="removerDoCarrinho('${item.cartId}')" style="background:none; border:none; color:#ddd; cursor:pointer; font-size:22px; padding:10px;">&times;</button>
            </div>
        `;
    }).join('');

    if(totalElemento) totalElemento.innerText = `R$ ${totalGeral.toLocaleString('pt-br', {minimumFractionDigits: 2})}`;
}

function removerDoCarrinho(cartId) {
    let carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    carrinho = carrinho.filter(i => i.cartId !== cartId);
    sessionStorage.setItem('fashion_cart', JSON.stringify(carrinho));
    renderizarCarrinho();
}

/**
 * FUNÇÃO DE FINALIZAÇÃO - ATUALIZADA PARA FORM-SUBMIT
 * Esta função força o navegador a mudar para a página processar_pedido.php
 */
function finalizarCompra() {
    console.log("Tentando finalizar compra...");
    const carrinho = JSON.parse(sessionStorage.getItem('fashion_cart')) || [];
    
    if (carrinho.length === 0) {
        alert("Sua sacola está vazia.");
        return;
    }

    // Criar um formulário invisível
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'processar_pedido.php';

    // Adicionar o JSON do carrinho ao formulário
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'itens_json';
    input.value = JSON.stringify(carrinho);

    form.appendChild(input);
    document.body.appendChild(form);
    
    console.log("Enviando dados para o servidor...");
    form.submit();
}

/* --- 3. LÓGICA DE FAVORITOS --- */
function toggleFavorito(produto) {
    if (!produto || !produto.id) return;

    let favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    const index = favoritos.findIndex(item => String(item.id) === String(produto.id));

    if (index > -1) {
        favoritos.splice(index, 1);
    } else {
        favoritos.push({
            id: produto.id,
            nome: produto.nome,
            preco: produto.preco,
            img: produto.imagem || produto.img
        });
    }

    localStorage.setItem('fashion_favs', JSON.stringify(favoritos));
    atualizarInterfaceFavoritos();
}

function atualizarInterfaceFavoritos() {
    const favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    document.querySelectorAll('.btn-fav').forEach(btn => {
        const idProd = btn.getAttribute('data-id'); 
        const isFav = favoritos.some(item => String(item.id) === String(idProd));
        btn.innerHTML = isFav ? '❤️' : '🤍';
    });
}

/* --- 4. ESTÉTICA --- */
function setupMenuIndicator() {
    const indicator = document.querySelector('.nav-indicator');
    const items = document.querySelectorAll('.main-nav a');
    if (!indicator || items.length === 0) return;

    const move = (el) => {
        indicator.style.width = `${el.offsetWidth}px`;
        indicator.style.left = `${el.offsetLeft}px`;
        indicator.style.opacity = "1";
    };

    items.forEach(item => {
        item.addEventListener('mouseenter', () => move(item));
        if (item.classList.contains('active')) move(item);
    });
}

/* --- INFANTIL --- */
function setupKidsFilters() {
    const chips = document.querySelectorAll('.filter-chips .chip');
    const products = document.querySelectorAll('#kidsGrid .product-card');

    chips.forEach(chip => {
        chip.addEventListener('click', () => {
            chips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');

            const filterValue = chip.getAttribute('data-age');

            products.forEach(product => {
                const productAge = product.getAttribute('data-age-group');
                
                if (filterValue === 'todos' || productAge === filterValue) {
                    product.style.display = 'block';
                    setTimeout(() => product.style.opacity = '1', 10);
                } else {
                    product.style.opacity = '0';
                    product.style.display = 'none';
                }
            });
        });
    });
}
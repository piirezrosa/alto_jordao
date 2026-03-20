/* ==========================================================================
   FASHIONSHOP - SCRIPT GLOBAL (CARRINHO E INTERFACE)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    console.log("FashionShop: Sistema carregado.");

    setupMenuIndicator(); 
    atualizarInterfaceFavoritos();
    renderizarCarrinho(); // Carrega os itens do carrinho ao iniciar
    
    // Se estiver na página de favoritos, renderiza a grid
    if (document.getElementById('favsGrid')) {
        renderizarPaginaFavoritos();
    }
});

/* --- 1. LÓGICA DE COMPRA DIRETA (PÁGINA DE PRODUTO) --- */

/**
 * Captura as seleções de cor e tamanho e envia para o carrinho
 */
function adicionarAoCarrinhoDireto(produto) {
    const txtCor = document.getElementById('txt-cor-selecionada');
    const selTam = document.getElementById('select-tamanho');

    // 1. Validação de Cor
    const corSelecionada = txtCor ? txtCor.innerText : 'Padrão';
    if (corSelecionada === "Selecione") {
        alert("Por favor, selecione uma cor clicando em uma das opções.");
        return;
    }

    // 2. Validação de Tamanho
    const tamSelecionado = selTam ? selTam.value : 'Único';
    if (tamSelecionado === "") {
        alert("Por favor, selecione um tamanho.");
        return;
    }

    // 3. Monta o objeto (Garante que o caminho da imagem esteja correto)
    const itemParaCarrinho = {
        id: produto.id,
        nome: produto.nome,
        preco: parseFloat(produto.preco),
        img: produto.imagem.includes('/') ? produto.imagem : "img/produtos/" + produto.imagem,
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
    const cartId = `${p.id}-${p.opcoes}`; // Chave única para variações

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

/* --- 3. INTERFACE E UTILITÁRIOS --- */

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
        item.addEventListener('mouseenter', (e) => moveIndicator(e.target));
        if (item.classList.contains('active')) moveIndicator(item);
    });
}

function atualizarInterfaceFavoritos() {
    const favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    document.querySelectorAll('.btn-fav').forEach(btn => {
        const nomeProd = btn.getAttribute('data-name'); 
        if (favoritos.some(item => item.nome === nomeProd)) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fa-solid fa-heart"></i>'; // Se usar FontAwesome
        }
    });
}

function renderizarPaginaFavoritos() {
    const container = document.getElementById('favsGrid');    if (!container) return;
    
        const favoritos = JSON.parse(localStorage.getItem('fashion_favs')) || [];
    if (favoritos.length === 0) {
        container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:50px;"><h3>Você ainda não favoritou nada.</h3></div>';
        return;
    }
    // Lógica adicional de renderização de cards de favoritos aqui se necessário
}
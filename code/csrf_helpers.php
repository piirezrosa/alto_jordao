<?php
/**
 * ALTO JORDÃO — Proteção contra CSRF (um único token por sessão, usado em todo o painel).
 *
 * Formulário POST:   <?= csrf_campo() ?>              ... e no topo do handler:  csrf_exigir();
 * Link que altera:   href="x.php?id=1&<?= csrf_param() ?>"  ... e no topo:         csrf_exigir_get();
 * Ação destrutiva deve preferir POST; o link com token é só para botões simples.
 */
if (session_status() === PHP_SESSION_NONE) { session_start(); }

function csrf_token(): string
{
    if (empty($_SESSION['csrf_geral'])) $_SESSION['csrf_geral'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_geral'];
}

function csrf_ok(?string $enviado): bool
{
    return is_string($enviado) && $enviado !== ''
        && !empty($_SESSION['csrf_geral'])
        && hash_equals($_SESSION['csrf_geral'], $enviado);
}

/** <input hidden> para dentro de <form method="POST"> */
function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** "_csrf=TOKEN" para anexar em links (?id=1&_csrf=...) */
function csrf_param(): string
{
    return '_csrf=' . urlencode(csrf_token());
}

function csrf_negar(): void
{
    http_response_code(403);
    die('Sessão expirada ou requisição inválida. Volte, recarregue a página e tente de novo.');
}

/** Em POST: exige token válido (campo _csrf ou cabeçalho X-CSRF-Token). Outros métodos passam. */
function csrf_exigir(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') return;
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!csrf_ok(is_string($t) ? $t : null)) csrf_negar();
}

/** Em GET com ação (link): exige ?_csrf=... válido. */
function csrf_exigir_get(): void
{
    $t = $_GET['_csrf'] ?? null;
    if (!csrf_ok(is_string($t) ? $t : null)) csrf_negar();
}

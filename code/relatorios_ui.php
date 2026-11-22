<?php
/**
 * Interface de exportação reutilizável.
 *  - rel_exportar_botao(['estoque_atual', 'reposicao'])  → botão "Exportar" para colocar na barra de uma tela;
 *    abre uma janela com só os relatórios daquele assunto (filtros + prévia + baixar CSV).
 *  - rel_ui_form($id, $rel, $par) → formulário de UM relatório (usado pela janela e pela central).
 * Relatórios restritos (somente_super) somem para quem não é superadmin.
 */
require_once __DIR__ . '/relatorios_catalogo.php';

function rel_ui_super(): bool { return ($_SESSION['usuario_nivel'] ?? '') === 'superadmin'; }

function rel_ui_css(): void
{
    static $feito = false; if ($feito) return; $feito = true; ?>
<style>
    .rel-modal { margin:auto; border:0; border-radius:28px; padding:0; width:min(560px, calc(100vw - 28px)); max-height:calc(100vh - 40px); box-shadow:0 30px 80px rgba(0,0,0,.35); font-family:var(--font-main, 'Inter', sans-serif); color:var(--black, #000); }
    .rel-modal::backdrop { background:rgba(0,0,0,.45); }
    .rel-modal-head { display:flex; justify-content:space-between; align-items:center; padding:22px 26px 10px; }
    .rel-modal-head h3 { font-size:18px; font-weight:800; letter-spacing:-.3px; margin:0; }
    .rel-modal-x { border:0; background:var(--grey-bg, #f2f2f2); width:34px; height:34px; border-radius:50%; font-size:18px; cursor:pointer; }
    .rel-modal-body { padding:6px 26px 24px; overflow-y:auto; max-height:calc(100vh - 140px); }
    .rel-item { border:1px solid var(--border, #e5e5e5); border-radius:20px; padding:16px 18px; margin-top:12px; }
    .rel-item h4 { font-size:14px; font-weight:800; margin:0 0 4px; }
    .rel-item p { font-size:12px; line-height:1.5; color:var(--text2, #555); margin:0 0 12px; }
    .rel-form { display:flex; flex-direction:column; gap:10px; }
    .rel-campos { display:flex; flex-wrap:wrap; gap:10px; }
    .rel-campos label { display:flex; flex-direction:column; gap:5px; font-size:10px; font-weight:800; color:var(--muted, #888); text-transform:uppercase; letter-spacing:1px; flex:1 1 120px; }
    .rel-campos input { padding:10px 14px; border:1.5px solid var(--border, #e5e5e5); border-radius:50px; font:600 13px var(--font-main, 'Inter', sans-serif); background:var(--grey-bg, #f7f7f7); color:var(--black, #000); outline:none; min-width:0; }
    .rel-campos input:focus { border-color:var(--black, #000); background:#fff; }
    .rel-acoes { display:flex; gap:8px; flex-wrap:wrap; }
    .rel-acoes button { flex:1 1 auto; cursor:pointer; }
    .rel-modal-foot { margin-top:16px; font-size:12px; text-align:center; }
    .rel-modal-foot a { color:var(--muted, #888); font-weight:700; text-decoration:none; }
    .rel-modal-foot a:hover { color:var(--black, #000); }
</style>
<?php }

/** Formulário de um relatório: filtros + "Ver prévia" + "Baixar CSV". */
function rel_ui_form(string $id, array $r, array $par = [], string $action = 'admin_exportar.php'): void
{
    $par = $par + rel_parametros($r['filtros'], []);
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
    <form method="get" action="<?= $e($action) ?>" class="rel-form">
        <input type="hidden" name="r" value="<?= $e($id) ?>">
        <?php if ($r['filtros']): ?><div class="rel-campos">
            <?php if (isset($par['data_ini'])): ?>
                <label>De<input type="date" name="data_ini" value="<?= $e($par['data_ini']) ?>"></label>
                <label>Até<input type="date" name="data_fim" value="<?= $e($par['data_fim']) ?>"></label>
            <?php endif; ?>
            <?php if (isset($par['dias'])): ?>
                <label>Janela de vendas (dias)<input type="number" name="dias" min="1" max="730" value="<?= (int)$par['dias'] ?>"></label>
            <?php endif; ?>
            <?php if (isset($par['limite'])): ?>
                <label>Estoque até (un.)<input type="number" name="limite" min="0" max="9999" value="<?= (int)$par['limite'] ?>"></label>
            <?php endif; ?>
        </div><?php endif; ?>
        <div class="rel-acoes">
            <button type="submit" class="btn-admin-ghost">Ver prévia</button>
            <button type="submit" name="baixar" value="1" class="btn-admin-primary">Baixar CSV</button>
        </div>
    </form>
<?php }

/** Botão "Exportar" + janela com os relatórios do assunto da tela. */
function rel_exportar_botao(array $ids, string $rotulo = '⬇ Exportar', string $titulo = 'Exportar relatórios'): void
{
    static $n = 0; $n++;
    $super = rel_ui_super();
    $cat = rel_catalogo();
    $lista = [];
    foreach ($ids as $id) {
        if (isset($cat[$id]) && ($super || empty($cat[$id]['somente_super']))) $lista[$id] = $cat[$id];
    }
    if (!$lista) return;
    rel_ui_css();
    $dlg = 'relModal' . $n;
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); ?>
    <button type="button" class="btn-admin-ghost" onclick="document.getElementById('<?= $dlg ?>').showModal()"><?= $e($rotulo) ?></button>
    <dialog id="<?= $dlg ?>" class="rel-modal" onclick="if(event.target===this)this.close()">
        <div class="rel-modal-head"><h3><?= $e($titulo) ?></h3><button type="button" class="rel-modal-x" aria-label="Fechar" onclick="this.closest('dialog').close()">×</button></div>
        <div class="rel-modal-body">
            <?php foreach ($lista as $id => $r): ?>
            <section class="rel-item">
                <h4><?= $e($r['titulo']) ?></h4>
                <p><?= $e($r['desc']) ?></p>
                <?php rel_ui_form($id, $r); ?>
            </section>
            <?php endforeach; ?>
            <div class="rel-modal-foot"><a href="admin_exportar.php">Ver todos os relatórios →</a></div>
        </div>
    </dialog>
<?php }

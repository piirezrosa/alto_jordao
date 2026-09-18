<?php
/**
 * ============================================================
 *  ALTO JORDÃO — Classe de Permissões e Alçadas
 *  Item 2/8
 *
 *  USO EM QUALQUER ARQUIVO PHP:
 *  ─────────────────────────────
 *  require_once 'Permissoes.php';
 *  Permissoes::init($pdo);
 *
 *  // Verifica e redireciona automaticamente se não tiver acesso
 *  Permissoes::exigir('editar_produto');
 *
 *  // Verifica e retorna bool (para esconder botões na UI)
 *  if (Permissoes::pode('excluir_produto')) { ... }
 *
 *  // Verifica nível mínimo
 *  Permissoes::exigirNivel('gerente');
 *
 * ============================================================
 */

class Permissoes
{
    private static ?PDO   $pdo       = null;
    private static ?array $usuario   = null;
    private static array  $cache     = [];   // cache de permissões já verificadas

    // ── PERMISSÕES PADRÃO POR NÍVEL ──────────────────────
    // Define o que cada nível pode fazer SEM permissão customizada.
    // superadmin: tudo | admin: quase tudo | gerente: operacional
    // operador: só tarefas do dia a dia | cliente: acesso negado ao admin
    private static array $niveis = [
        'superadmin' => '*',    // curinga — tudo permitido
        'admin'      => [
            'ver_dashboard','ver_kpis',
            'ver_pedidos','editar_status_pedido','cancelar_pedido','exportar_pedidos',
            'ver_financeiro','ver_custos','exportar_financeiro',
            'ver_produtos','criar_produto','editar_produto','excluir_produto',
            'gerenciar_estoque','gerenciar_categorias','gerenciar_colecoes','gerenciar_marcas',
            'ver_clientes','ver_dados_cliente','bloquear_cliente',
            'ver_cupons','criar_cupom','editar_cupom','excluir_cupom','gerenciar_campanhas','moderar_avaliacoes',
            'ver_devolucoes','aprovar_devolucao',
            'ver_entregas','atualizar_rastreio',
            'ver_admins','criar_admin','editar_admin',
            'ver_relatorios','ver_alertas','ver_logs','configurar_alertas',
        ],
        'gerente' => [
            'ver_dashboard','ver_kpis',
            'ver_pedidos','editar_status_pedido','exportar_pedidos',
            'ver_financeiro','exportar_financeiro',
            'ver_produtos','criar_produto','editar_produto',
            'gerenciar_estoque','gerenciar_categorias','gerenciar_colecoes','gerenciar_marcas',
            'ver_clientes','ver_dados_cliente',
            'ver_cupons','criar_cupom','editar_cupom','gerenciar_campanhas','moderar_avaliacoes',
            'ver_devolucoes','aprovar_devolucao',
            'ver_entregas','atualizar_rastreio',
            'ver_relatorios','ver_alertas',
        ],
        'operador' => [
            'ver_dashboard',
            'ver_pedidos','editar_status_pedido',
            'ver_produtos','gerenciar_estoque',
            'ver_devolucoes',
            'ver_entregas','atualizar_rastreio',
            'ver_alertas',
        ],
        'cliente' => [],        // sem acesso ao admin
    ];

    // ── INICIALIZAÇÃO ─────────────────────────────────────
    public static function init(PDO $pdo): void
    {
        self::$pdo = $pdo;

        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!isset($_SESSION['usuario_id'])) return;

        // Carrega dados do usuário em memória
        $stmt = $pdo->prepare("SELECT id, nome, email, nivel, status FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        self::$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ── VERIFICAÇÃO PRINCIPAL ─────────────────────────────
    public static function pode(string $permissao): bool
    {
        if (!self::$usuario) return false;

        // Conta bloqueada nunca tem permissão
        if (self::$usuario['status'] !== 'ativo') return false;

        $nivel = self::$usuario['nivel'];

        // Cache para não repetir queries
        $chave = $nivel . ':' . self::$usuario['id'] . ':' . $permissao;
        if (isset(self::$cache[$chave])) return self::$cache[$chave];

        // superadmin tem tudo, sem consulta adicional
        if ($nivel === 'superadmin') {
            return self::$cache[$chave] = true;
        }

        // ── 1. VERIFICA PERMISSÃO CUSTOMIZADA (sobrepõe nível) ──
        if (self::$pdo) {
            try {
                $stmt = self::$pdo->prepare("
                    SELECT up.concedida
                    FROM usuario_permissoes up
                    JOIN permissoes p ON p.id = up.permissao_id
                    WHERE up.usuario_id = ?
                      AND p.codigo = ?
                    LIMIT 1
                ");
                $stmt->execute([self::$usuario['id'], $permissao]);
                $custom = $stmt->fetchColumn();

                // Permissão customizada encontrada — usa ela (pode ser 0 ou 1)
                if ($custom !== false) {
                    return self::$cache[$chave] = (bool)(int)$custom;
                }
            } catch (Exception $e) {
                // tabela não existe ainda — cai para verificação por nível
            }
        }

        // ── 2. VERIFICA PELO NÍVEL PADRÃO ─────────────────
        $permitidas = self::$niveis[$nivel] ?? [];
        $resultado  = in_array($permissao, $permitidas, true);

        return self::$cache[$chave] = $resultado;
    }

    // ── EXIGE PERMISSÃO (redireciona se não tiver) ────────
    public static function exigir(string $permissao, string $redir = 'admin_dashboard.php'): void
    {
        if (!self::logado()) {
            self::logarAcessoNegado($permissao, 'nao_autenticado');
            header("Location: login.php?erro=" . urlencode("Faça login para continuar."));
            exit;
        }

        if (!self::pode($permissao)) {
            self::logarAcessoNegado($permissao, 'sem_permissao');
            header("Location: $redir?acesso_negado=" . urlencode($permissao));
            exit;
        }
    }

    // ── EXIGE NÍVEL MÍNIMO ────────────────────────────────
    public static function exigirNivel(string $nivelMinimo): void
    {
        $hierarquia = ['cliente' => 0, 'operador' => 1, 'gerente' => 2, 'admin' => 3, 'superadmin' => 4];
        $nivelUsuario = self::$usuario['nivel'] ?? 'cliente';

        $ok = ($hierarquia[$nivelUsuario] ?? 0) >= ($hierarquia[$nivelMinimo] ?? 99);

        if (!$ok) {
            self::logarAcessoNegado("nivel:$nivelMinimo", 'nivel_insuficiente');
            header("Location: admin_dashboard.php?acesso_negado=nivel");
            exit;
        }
    }

    // ── UTILITÁRIOS ───────────────────────────────────────
    public static function logado(): bool
    {
        return self::$usuario !== null;
    }

    public static function usuario(): ?array
    {
        return self::$usuario;
    }

    public static function nivel(): string
    {
        return self::$usuario['nivel'] ?? 'cliente';
    }

    public static function ehAdmin(): bool
    {
        return in_array(self::nivel(), ['admin','superadmin','gerente','operador']);
    }

    // ── LOG DE ACESSO NEGADO ──────────────────────────────
    private static function logarAcessoNegado(string $permissao, string $motivo): void
    {
        if (!self::$pdo) return;
        try {
            self::$pdo->prepare("
                INSERT INTO logs_sistema (usuario_id, acao, tabela, detalhes, ip)
                VALUES (?, 'acesso_negado', 'permissoes', ?, ?)
            ")->execute([
                self::$usuario['id'] ?? null,
                "permissão: $permissao | motivo: $motivo | url: " . ($_SERVER['REQUEST_URI'] ?? ''),
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        } catch (Exception $e) {}
    }

    // ── LISTAR PERMISSÕES DO USUÁRIO ATUAL ────────────────
    public static function listar(): array
    {
        if (!self::$usuario) return [];
        $nivel = self::$usuario['nivel'];
        if ($nivel === 'superadmin') return ['*' => 'Acesso total'];
        return self::$niveis[$nivel] ?? [];
    }

    // ── CARREGAR TODAS AS PERMISSÕES DO BANCO ────────────
    public static function todas(): array
    {
        if (!self::$pdo) return [];
        try {
            return self::$pdo->query("
                SELECT * FROM permissoes ORDER BY modulo, codigo
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // ── PERMISSÕES CUSTOMIZADAS DE UM USUÁRIO ────────────
    public static function customizadasDe(int $usuario_id): array
    {
        if (!self::$pdo) return [];
        try {
            return self::$pdo->prepare("
                SELECT p.codigo, p.descricao, p.modulo, up.concedida, up.observacao
                FROM usuario_permissoes up
                JOIN permissoes p ON p.id = up.permissao_id
                WHERE up.usuario_id = ?
                ORDER BY p.modulo, p.codigo
            ")->execute([$usuario_id]) ? [] : [];
        } catch (Exception $e) {
            return [];
        }
    }

    // ── CONCEDER / REVOGAR PERMISSÃO ──────────────────────
    public static function conceder(
        int    $usuario_id,
        string $permissao_codigo,
        bool   $concedida,
        int    $por_quem,
        string $obs = ''
    ): bool {
        if (!self::$pdo) return false;

        // Apenas superadmin pode modificar permissões críticas
        $stmt = self::$pdo->prepare(
            "SELECT id, critico FROM permissoes WHERE codigo = ?"
        );
        $stmt->execute([$permissao_codigo]);
        $perm = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$perm) return false;

        if ($perm['critico'] && self::nivel() !== 'superadmin') return false;

        self::$pdo->prepare("
            INSERT INTO usuario_permissoes
                (usuario_id, permissao_id, concedida, concedida_por, observacao)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                concedida    = VALUES(concedida),
                concedida_por= VALUES(concedida_por),
                observacao   = VALUES(observacao),
                data_concessao = NOW()
        ")->execute([$usuario_id, $perm['id'], $concedida ? 1 : 0, $por_quem, $obs]);

        // Invalida cache
        unset(self::$cache);
        self::$cache = [];

        return true;
    }
}
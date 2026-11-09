<?php
/** Fale conosco: mensagens da Central de ajuda ficam no banco e aparecem em admin_mensagens.php. */

function contato_garantir_schema(PDO $pdo): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS mensagens_contato (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id    INT NULL,
                nome          VARCHAR(120) NOT NULL,
                email         VARCHAR(160) NOT NULL,
                pedido_numero VARCHAR(30)  NULL,
                assunto       VARCHAR(160) NOT NULL,
                mensagem      TEXT NOT NULL,
                ip            VARCHAR(45)  NULL,
                status        VARCHAR(12)  NOT NULL DEFAULT 'nova',
                data_criacao  DATETIME DEFAULT CURRENT_TIMESTAMP,
                respondida_em DATETIME NULL,
                respondida_por INT NULL,
                INDEX idx_contato_status (status, data_criacao)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        error_log('[CONTATO SCHEMA] ' . $e->getMessage());
    }
}

function contato_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['contato_csrf'])) $_SESSION['contato_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['contato_csrf'];
}

function contato_csrf_ok(?string $t): bool
{
    return is_string($t) && $t !== '' && isset($_SESSION['contato_csrf']) && hash_equals($_SESSION['contato_csrf'], $t);
}

function contato_novas(PDO $pdo): int
{
    try { contato_garantir_schema($pdo); return (int)$pdo->query("SELECT COUNT(*) FROM mensagens_contato WHERE status='nova'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

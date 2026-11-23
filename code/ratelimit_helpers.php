<?php
/**
 * ALTO JORDÃO — Limite de tentativas (login e cadastro).
 *
 * Guarda cada tentativa falha em `login_tentativas` (a tabela se cria sozinha) e bloqueia
 * por e-mail (força bruta contra uma conta) e por IP (varredura de várias contas).
 * O e-mail é guardado só como hash, nunca em texto.
 */
const RL_JANELA_MIN   = 15;   // janela de contagem
const RL_MAX_EMAIL    = 5;    // falhas por e-mail na janela
const RL_MAX_IP       = 20;   // falhas por IP na janela
const RL_MAX_CADASTRO = 8;    // contas criadas por IP em 1 hora

function rl_schema(PDO $pdo): void
{
    static $feito = false;
    if ($feito) return;
    $feito = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_tentativas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(12) NOT NULL DEFAULT 'login',
            chave CHAR(64) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chave (tipo, chave, criado_em),
            INDEX idx_ip (tipo, ip, criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('[RATELIMIT SCHEMA] ' . $e->getMessage() . ' — crie a tabela login_tentativas (veja docker/db/01-schema.sql)');
    }
}

function rl_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45); }
function rl_chave(string $email): string { return hash('sha256', strtolower(trim($email))); }

/** Minutos restantes de bloqueio (0 = liberado). */
function rl_bloqueio_min(PDO $pdo, string $tipo, string $email): int
{
    rl_schema($pdo);
    try {
        $q = $pdo->prepare("SELECT COUNT(*), MIN(criado_em) FROM login_tentativas
                            WHERE tipo = ? AND chave = ? AND criado_em > (NOW() - INTERVAL " . RL_JANELA_MIN . " MINUTE)");
        $q->execute([$tipo, rl_chave($email)]);
        [$n_email, $primeira_email] = $q->fetch(PDO::FETCH_NUM);

        $q = $pdo->prepare("SELECT COUNT(*), MIN(criado_em) FROM login_tentativas
                            WHERE tipo = ? AND ip = ? AND criado_em > (NOW() - INTERVAL " . RL_JANELA_MIN . " MINUTE)");
        $q->execute([$tipo, rl_ip()]);
        [$n_ip, $primeira_ip] = $q->fetch(PDO::FETCH_NUM);

        $primeira = null;
        if ((int)$n_email >= RL_MAX_EMAIL) $primeira = $primeira_email;
        if ((int)$n_ip    >= RL_MAX_IP && (!$primeira || $primeira_ip < $primeira)) $primeira = $primeira_ip;
        if (!$primeira) return 0;
        $falta = RL_JANELA_MIN * 60 - (time() - strtotime($primeira));
        return $falta > 0 ? (int)ceil($falta / 60) : 0;
    } catch (Throwable $e) {
        error_log('[RATELIMIT] ' . $e->getMessage());
        return 0;   // se a tabela falhar, não trava a loja inteira
    }
}

function rl_falha(PDO $pdo, string $tipo, string $email): void
{
    rl_schema($pdo);
    try {
        $pdo->prepare("INSERT INTO login_tentativas (tipo, chave, ip) VALUES (?,?,?)")->execute([$tipo, rl_chave($email), rl_ip()]);
        if (random_int(1, 50) === 1) $pdo->exec("DELETE FROM login_tentativas WHERE criado_em < (NOW() - INTERVAL 2 DAY)");   // faxina
    } catch (Throwable $e) { error_log('[RATELIMIT] ' . $e->getMessage()); }
}

function rl_sucesso(PDO $pdo, string $tipo, string $email): void
{
    try { $pdo->prepare("DELETE FROM login_tentativas WHERE tipo = ? AND chave = ?")->execute([$tipo, rl_chave($email)]); } catch (Throwable $e) {}
}

/** Cadastro: quantas contas este IP criou na última hora. */
function rl_cadastros_ip(PDO $pdo): int
{
    rl_schema($pdo);
    try {
        $q = $pdo->prepare("SELECT COUNT(*) FROM login_tentativas WHERE tipo = 'cadastro' AND ip = ? AND criado_em > (NOW() - INTERVAL 1 HOUR)");
        $q->execute([rl_ip()]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

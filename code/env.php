<?php
/**
 * Leitor de variáveis de ambiente — Alto Jordão
 *
 * Lê o arquivo `.env` (na pasta code/, FORA do Git) e expõe env('CHAVE', 'padrão').
 * Ordem: variável real do servidor (getenv) > arquivo .env > valor padrão.
 * Formato do .env:  CHAVE=valor   (linhas com # são comentários; aspas opcionais)
 */
if (!function_exists('env')) {
    function env(string $chave, $padrao = null)
    {
        static $arquivo = null;
        if ($arquivo === null) {
            $arquivo = [];
            $caminho = __DIR__ . '/.env';
            if (is_readable($caminho)) {
                foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
                    $linha = trim($linha);
                    if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) continue;
                    [$k, $v] = explode('=', $linha, 2);
                    $v = trim($v);
                    if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) {
                        $v = substr($v, 1, -1);
                    }
                    $arquivo[trim($k)] = $v;
                }
            }
        }
        $real = getenv($chave);
        if ($real !== false && $real !== '') return $real;
        return array_key_exists($chave, $arquivo) ? $arquivo[$chave] : $padrao;
    }

    /** env() interpretado como booleano (true/1/sim/on). */
    function env_bool(string $chave, bool $padrao = false): bool
    {
        $v = env($chave, null);
        if ($v === null) return $padrao;
        return in_array(strtolower(trim((string)$v)), ['1', 'true', 'sim', 'on', 'yes'], true);
    }
}

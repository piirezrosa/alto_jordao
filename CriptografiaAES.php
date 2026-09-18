<?php
/**
 * ============================================================
 *  ALTO JORDÃO — Classe de Criptografia AES-256-CBC
 *  Item 3/8
 *
 *  Algoritmo: AES-256-CBC
 *  Derivação de chave: PBKDF2-SHA256 com 100.000 iterações
 *  Formato do arquivo .ajenc:
 *  ┌──────────────────────────────────────────────────┐
 *  │ 4 bytes  → magic "AJEC" (Alto Jordão Encrypted)  │
 *  │ 1 byte   → versão do formato (0x01)              │
 *  │ 16 bytes → IV (initialization vector) aleatório  │
 *  │ 32 bytes → salt para PBKDF2                      │
 *  │ 4 bytes  → tamanho do payload (big-endian)       │
 *  │ N bytes  → payload criptografado (AES-256-CBC)   │
 *  │ 64 bytes → HMAC-SHA256 de tudo acima             │
 *  └──────────────────────────────────────────────────┘
 *
 *  O HMAC garante autenticidade e detecta adulteração.
 *  Sem a senha correta, o arquivo é completamente ilegível.
 * ============================================================
 */

class CriptografiaAES
{
    private const MAGIC      = 'AJEC';
    private const VERSAO     = "\x01";
    private const ALGORITMO  = 'aes-256-cbc';
    private const ITERACOES  = 100000;          // PBKDF2 — dificulta força bruta
    private const TAMANHO_IV = 16;
    private const TAMANHO_SALT = 32;
    private const TAMANHO_CHAVE = 32;           // 256 bits

    // ── CRIPTOGRAFAR ──────────────────────────────────────
    /**
     * Criptografa dados com AES-256-CBC.
     * @param string $dados  Conteúdo em texto plano (JSON, CSV, etc.)
     * @param string $senha  Senha fornecida pelo usuário
     * @return string        Binário do arquivo .ajenc
     * @throws RuntimeException
     */
    public static function criptografar(string $dados, string $senha): string
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('Extensão OpenSSL não disponível.');
        }

        // Gera IV e salt aleatórios e criptograficamente seguros
        $iv   = random_bytes(self::TAMANHO_IV);
        $salt = random_bytes(self::TAMANHO_SALT);

        // Deriva duas chaves com PBKDF2: uma para criptografia, outra para HMAC
        $chave_material = hash_pbkdf2(
            'sha256',
            $senha,
            $salt,
            self::ITERACOES,
            self::TAMANHO_CHAVE * 2, // 64 bytes → divide em 2 chaves de 32
            true
        );

        $chave_enc  = substr($chave_material, 0, 32);   // primeira metade → AES
        $chave_hmac = substr($chave_material, 32, 32);  // segunda metade  → HMAC

        // Comprime antes de criptografar (reduz tamanho e aumenta entropia)
        $comprimido = gzcompress($dados, 9);
        if ($comprimido === false) {
            throw new RuntimeException('Falha na compressão dos dados.');
        }

        // Criptografa
        $payload = openssl_encrypt(
            $comprimido,
            self::ALGORITMO,
            $chave_enc,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($payload === false) {
            throw new RuntimeException('Falha na criptografia: ' . openssl_error_string());
        }

        // Monta o cabeçalho
        $tamanho_payload = pack('N', strlen($payload));  // 4 bytes big-endian

        $corpo = self::MAGIC
            . self::VERSAO
            . $iv
            . $salt
            . $tamanho_payload
            . $payload;

        // HMAC-SHA256 do corpo inteiro (autenticidade + integridade)
        $hmac = hash_hmac('sha256', $corpo, $chave_hmac, true);

        return $corpo . $hmac;
    }

    // ── DESCRIPTOGRAFAR ───────────────────────────────────
    /**
     * Descriptografa um arquivo .ajenc.
     * @param string $binario  Conteúdo binário do arquivo .ajenc
     * @param string $senha    Senha do usuário
     * @return string          Conteúdo original em texto plano
     * @throws RuntimeException  Em caso de senha errada ou arquivo adulterado
     */
    public static function descriptografar(string $binario, string $senha): string
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('Extensão OpenSSL não disponível.');
        }

        // Tamanho mínimo: 4 (magic) + 1 (versão) + 16 (IV) + 32 (salt) + 4 (tam) + 64 (hmac) = 121 bytes
        if (strlen($binario) < 121) {
            throw new RuntimeException('Arquivo inválido ou corrompido.');
        }

        // Lê o HMAC (últimos 32 bytes do binário — SHA256 em raw = 32 bytes)
        $hmac_arquivo = substr($binario, -32);
        $corpo        = substr($binario, 0, -32);

        // Lê campos do cabeçalho
        $pos    = 0;
        $magic  = substr($corpo, $pos, 4); $pos += 4;
        $versao = substr($corpo, $pos, 1); $pos += 1;
        $iv     = substr($corpo, $pos, self::TAMANHO_IV);   $pos += self::TAMANHO_IV;
        $salt   = substr($corpo, $pos, self::TAMANHO_SALT); $pos += self::TAMANHO_SALT;

        $tamanho_arr    = unpack('N', substr($corpo, $pos, 4)); $pos += 4;
        $tamanho_payload= $tamanho_arr[1];
        $payload        = substr($corpo, $pos, $tamanho_payload);

        // Valida magic e versão
        if ($magic !== self::MAGIC) {
            throw new RuntimeException('Formato de arquivo inválido.');
        }
        if ($versao !== self::VERSAO) {
            throw new RuntimeException('Versão do formato não suportada.');
        }

        // Deriva chaves com a senha fornecida
        $chave_material = hash_pbkdf2(
            'sha256',
            $senha,
            $salt,
            self::ITERACOES,
            self::TAMANHO_CHAVE * 2,
            true
        );

        $chave_enc  = substr($chave_material, 0, 32);
        $chave_hmac = substr($chave_material, 32, 32);

        // Verifica HMAC antes de descriptografar (timing-safe)
        $hmac_esperado = hash_hmac('sha256', $corpo, $chave_hmac, true);
        if (!hash_equals($hmac_esperado, $hmac_arquivo)) {
            throw new RuntimeException('Senha incorreta ou arquivo adulterado.');
        }

        // Descriptografa
        $comprimido = openssl_decrypt(
            $payload,
            self::ALGORITMO,
            $chave_enc,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($comprimido === false) {
            throw new RuntimeException('Falha na descriptografia.');
        }

        // Descomprime
        $original = gzuncompress($comprimido);
        if ($original === false) {
            throw new RuntimeException('Falha na descompressão — arquivo corrompido.');
        }

        return $original;
    }

    // ── HASH DE INTEGRIDADE (SHA-256 do conteúdo original) ─
    public static function hashIntegridade(string $dados): string
    {
        return hash('sha256', $dados);
    }

    // ── INFORMAÇÕES DO ARQUIVO .ajenc ─────────────────────
    public static function infoArquivo(string $binario): array
    {
        if (strlen($binario) < 57) return ['valido' => false];

        $magic  = substr($binario, 0, 4);
        $versao = ord($binario[4]);

        return [
            'valido'      => $magic === self::MAGIC,
            'magic'       => $magic,
            'versao'      => $versao,
            'algoritmo'   => 'AES-256-CBC + HMAC-SHA256',
            'derivacao'   => 'PBKDF2-SHA256 (' . self::ITERACOES . ' iterações)',
            'tamanho'     => strlen($binario) . ' bytes',
        ];
    }
}
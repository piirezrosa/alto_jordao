<?php
/**
 * ALTO JORDÃO — Upload seguro de imagens.
 *
 * Nunca confia no nome nem na extensão enviados: o tipo vem do CONTEÚDO (getimagesize),
 * a extensão é escolhida por nós e o nome é aleatório. Assim um ".php" disfarçado de foto é recusado.
 *
 * Uso:  [$nome, $erro] = upload_imagem($_FILES['imagem'], __DIR__ . '/img/produtos');
 *       $erro === ''  → $nome é o arquivo salvo;  $nome === null e $erro === '' → nada foi enviado.
 */
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB

function upload_imagem(?array $arq, string $pasta): array
{
    if (!$arq || ($arq['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($arq['name'] ?? '') === '') {
        return [null, ''];   // nada enviado
    }
    if (($arq['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return [null, ($arq['error'] === UPLOAD_ERR_INI_SIZE || $arq['error'] === UPLOAD_ERR_FORM_SIZE)
            ? 'Imagem grande demais (máximo 5 MB).' : 'Falha no envio da imagem. Tente novamente.'];
    }
    if (!is_uploaded_file($arq['tmp_name'])) return [null, 'Arquivo de upload inválido.'];
    if (($arq['size'] ?? 0) > UPLOAD_MAX_BYTES) return [null, 'Imagem grande demais (máximo 5 MB).'];

    $info = @getimagesize($arq['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    if ($info === false || !isset($tipos[$info[2]])) {
        return [null, 'Formato inválido. Envie uma imagem JPG, PNG, WEBP ou GIF.'];
    }

    if (!is_dir($pasta) && !mkdir($pasta, 0755, true) && !is_dir($pasta)) {
        return [null, 'Não foi possível criar a pasta de imagens.'];
    }
    $nome = bin2hex(random_bytes(16)) . '.' . $tipos[$info[2]];
    if (!move_uploaded_file($arq['tmp_name'], rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . $nome)) {
        return [null, 'Não foi possível salvar a imagem.'];
    }
    @chmod(rtrim($pasta, '/\\') . DIRECTORY_SEPARATOR . $nome, 0644);
    return [$nome, ''];
}

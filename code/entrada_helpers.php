<?php
/**
 * ALTO JORDÃO — Sanitização de parâmetros da URL (antídoto de XSS refletido).
 * Valor fora do formato esperado vira o padrão, então é seguro imprimir em href/value.
 */
/** Só letras, números, _ e -  (até 40 caracteres): filtros, abas, status, períodos. */
function ent_token($valor, string $padrao = ''): string
{
    return (is_string($valor) && preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $valor)) ? $valor : $padrao;
}

/** Data AAAA-MM-DD válida; senão o padrão. */
function ent_data($valor, string $padrao): string
{
    if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
        [$a, $m, $d] = array_map('intval', explode('-', $valor));
        if (checkdate($m, $d, $a)) return $valor;
    }
    return $padrao;
}

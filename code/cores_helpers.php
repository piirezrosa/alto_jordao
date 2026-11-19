<?php
/**
 * Cores dos produtos: converte o NOME da cor (como foi digitado no cadastro) em uma cor CSS.
 * Aceita acentos, maiúsculas, espaços ou hífen ("Verde Musgo", "verde-musgo", "VERDE MUSGO"),
 * hexadecimal ("#c0ffee") e nomes CSS em inglês ("navy"). Cor desconhecida devolve null
 * (a página mostra uma bolinha listrada em vez de uma bolinha invisível).
 */
function aj_cor_chave(string $nome): string
{
    $n = mb_strtolower(trim($nome), 'UTF-8');
    $n = strtr($n, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','é'=>'e','è'=>'e','ê'=>'e','í'=>'i','ì'=>'i','î'=>'i',
                    'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c']);
    return trim(preg_replace('/[^a-z0-9]+/', '-', $n), '-');
}

function aj_cor_css(string $nome): ?string
{
    static $mapa = [
        // neutros
        'preto' => '#111111', 'branco' => '#FFFFFF', 'off-white' => '#FAF7F0', 'gelo' => '#F4F4F2', 'creme' => '#FFFDD0',
        'bege' => '#E8DCC2', 'areia' => '#D8C3A5', 'nude' => '#E3BC9A', 'khaki' => '#C3B091', 'caqui' => '#BDB76B',
        'cinza' => '#9E9E9E', 'cinza-claro' => '#D3D3D3', 'cinza-escuro' => '#555555', 'cinza-mescla' => '#B8B8B8',
        'mescla' => '#B8B8B8', 'cinza-chumbo' => '#36454F', 'chumbo' => '#36454F', 'grafite' => '#383838', 'prata' => '#C0C0C0',
        // terrosos
        'marrom' => '#6B4226', 'caramelo' => '#C68642', 'terracota' => '#E2725B', 'ferrugem' => '#B7410E', 'chocolate' => '#4A2C1A',
        'mostarda' => '#E1AD01', 'dourado' => '#D4AF37', 'amarelo' => '#F4D03F',
        // verdes
        'verde' => '#2E8B57', 'verde-militar' => '#4B5320', 'verde-oliva' => '#6B8E23', 'oliva' => '#6B8E23',
        'verde-musgo' => '#556B2F', 'musgo' => '#556B2F', 'verde-escuro' => '#1B4D3E', 'verde-claro' => '#90EE90',
        'verde-agua' => '#7FDBCA', 'menta' => '#98FF98', 'turquesa' => '#30D5C8',
        // azuis
        'azul' => '#1E5FD1', 'azul-marinho' => '#0B1F4B', 'marinho' => '#0B1F4B', 'navy' => '#0B1F4B', 'azul-royal' => '#4169E1',
        'azul-claro' => '#87CEEB', 'azul-bebe' => '#9BB2C7', 'azul-petroleo' => '#0F4C5C', 'petroleo' => '#0F4C5C',
        'jeans' => '#1560BD', 'jeans-claro' => '#5DADEC', 'jeans-escuro' => '#1C3F95', 'indigo' => '#2E3A87',
        // vermelhos / rosas / roxos
        'vermelho' => '#D32F2F', 'vinho' => '#722F37', 'bordo' => '#6D1F2B', 'marsala' => '#955251', 'goiaba' => '#E5645E',
        'coral' => '#FF7F50', 'laranja' => '#F57C00', 'salmao' => '#FA8072',
        'rosa' => '#F48FB1', 'rosa-blush' => '#F3C6CF', 'rosa-claro' => '#F8D5DC', 'rosa-pink' => '#E91E8C', 'pink' => '#E91E8C',
        'rose' => '#E8A3B0', 'magenta' => '#C2185B', 'fucsia' => '#C2185B',
        'roxo' => '#6A1B9A', 'lilas' => '#C8A2C8', 'lavanda' => '#E6E6FA', 'violeta' => '#7B3FA0', 'uva' => '#4B1E5A',
    ];
    $n = trim($nome);
    if ($n === '') return null;
    if (preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', $n, $m) && ($n[0] === '#' || strlen($m[1]) === 6)) {
        return '#' . $m[1];
    }
    $k = aj_cor_chave($n);
    if (isset($mapa[$k])) return $mapa[$k];
    // "verde-musgo-escuro", "azul-marinho-claro"...: usa a primeira palavra conhecida
    foreach (explode('-', $k) as $parte) { if (isset($mapa[$parte])) return $mapa[$parte]; }
    // nomes CSS em inglês (red, navy, teal...)
    static $css = ['red','blue','green','yellow','orange','purple','pink','brown','black','white','gray','grey','teal','navy','olive',
                   'maroon','aqua','lime','silver','gold','beige','ivory','khaki','coral','salmon','tan','crimson','indigo','violet'];
    return in_array($k, $css, true) ? $k : null;
}

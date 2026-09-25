<?php
declare(strict_types=1);

/**
 * Gerador mínimo de QR Code em PHP puro (sem dependências externas).
 * - Modo byte (UTF-8), nível ECC L, versões 1..10 (até 271 bytes).
 * - Seleção automática de máscara (0..7) com a regra padrão de penalidade.
 * - Renderização em SVG para exibir na tela / compartilhar / imprimir.
 * Especificação: ISO/IEC 18004. Matriz validada bit a bit contra a
 * biblioteca de referência `python-qrcode` (ver testes no README).
 */

// versão => [total codewords, ec codewords por bloco, qtd blocos, data codewords]
// (tabela ISO/IEC 18004, nível L, versões 1..10 — cobre URLs de até 271 bytes)
const QR_BLOCKS = [
    1 => [26, 7, 1, 19],
    2 => [44, 10, 1, 34],
    3 => [70, 15, 1, 55],
    4 => [100, 20, 1, 80],
    5 => [134, 26, 1, 108],
    6 => [172, 18, 2, 136],
    7 => [196, 20, 2, 156],
    8 => [242, 24, 2, 194],
    9 => [292, 30, 2, 232],
    10 => [346, 18, 2, 280],
];
// capacidade em bytes no modo byte, nível L
const QR_BYTE_CAP = [1 => 17, 2 => 32, 3 => 53, 4 => 78, 5 => 106, 6 => 134, 7 => 154, 8 => 192, 9 => 230, 10 => 271];
// posições dos alignment patterns por versão (a interseção com o timing é ignorada)
const QR_ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

function qr_version_for_len(int $n): int
{
    foreach (QR_BYTE_CAP as $v => $cap) {
        if ($n <= $cap) return $v;
    }
    throw new RuntimeException('Texto excede ' . max(QR_BYTE_CAP) . ' bytes (limite do QR embutido).');
}

// ---- GF(256), polinômio 0x11d ----
function qr_gf(): array
{
    static $t = null;
    if ($t === null) {
        $exp = array_fill(0, 512, 0); $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x; $log[$x] = $i;
            $x <<= 1; if ($x & 0x100) $x ^= 0x11d;
        }
        for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        $t = [$exp, $log];
    }
    return $t;
}

function qr_poly_mul(array $a, array $b): array
{
    [$exp, $log] = qr_gf();
    $r = array_fill(0, count($a) + count($b) - 1, 0);
    foreach ($a as $i => $ai) {
        if ($ai === 0) continue;
        foreach ($b as $j => $bj) {
            if ($bj === 0) continue;
            // coeficiente != 0 => log definido (log[0] nunca é lido)
            $r[$i + $j] ^= $exp[$log[$ai] + $log[$bj]];
        }
    }
    return $r;
}

function qr_generator_poly(int $degree): array
{
    static $cache = [];
    if (isset($cache[$degree])) return $cache[$degree];
    $g = [1];
    for ($i = 0; $i < $degree; $i++) {
        $g = qr_poly_mul($g, [1, (1 << $i)]);
    }
    return $cache[$degree] = $g;
}

function qr_ec_bytes(array $data, int $ecCount): array
{
    $gen = qr_generator_poly($ecCount);
    // divisão polinomial: data * x^ecCount mod gen
    [$exp, $log] = qr_gf();
    $res = array_merge($data, array_fill(0, $ecCount, 0));
    foreach ($data as $i => $fac) {
        if ($fac === 0) continue;
        foreach ($gen as $j => $gj) {
            if ($gj === 0) continue;
            $res[$i + $j] ^= $exp[$log[$fac] + $log[$gj]];
        }
    }
    return array_slice($res, -$ecCount);
}

// ---- dados: modo byte ----
function qr_data_codewords(string $text, int $version): array
{
    [$total, $ecPerBlock, $numBlocks, $dataCount] = QR_BLOCKS[$version];
    $bits = [];
    $push = function (int $val, int $len) use (&$bits) {
        for ($i = $len - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1;
    };
    $push(0b0100, 4);                 // modo byte
    $lenBits = $version <= 9 ? 8 : 16;
    $push(strlen($text), $lenBits);   // contagem
    for ($i = 0; $i < strlen($text); $i++) $push(ord($text[$i]), 8);

    $capBits = $dataCount * 8;
    // terminator (até 4 zeros)
    $push(0, min(4, $capBits - count($bits)));
    // pad até múltiplo de 8
    if (count($bits) % 8 !== 0) $push(0, 8 - (count($bits) % 8));

    $cw = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) $byte = ($byte << 1) | $bits[$i + $j];
        $cw[] = $byte;
    }
    // pad bytes alternados 0xEC, 0x11
    $pad = [0xEC, 0x11]; $k = 0;
    while (count($cw) < $dataCount) { $cw[] = $pad[$k % 2]; $k++; }

    // split em blocos + EC. Para as versões suportadas (1..10, nível L) são
    // 1 bloco ou 2 blocos de mesmo tamanho (short == long == dataCount / 2).
    if ($numBlocks === 1) {
        $blocks = [$cw];
    } else {
        $longLen = $dataCount - intdiv($dataCount, 2);
        $blocks = [array_slice($cw, 0, $longLen), array_slice($cw, $longLen)];
    }
    $ecs = array_map(fn($b) => qr_ec_bytes($b, $ecPerBlock), $blocks);
    $all = [];
    $maxData = max(array_map('count', $blocks));
    for ($i = 0; $i < $maxData; $i++) foreach ($blocks as $b) if (isset($b[$i])) $all[] = $b[$i];
    for ($i = 0; $i < $ecPerBlock; $i++) foreach ($ecs as $e) $all[] = $e[$i];
    return $all;
}

// ---- matriz ----
function qr_new_matrix(int $size): array
{
    return array_fill(0, $size, array_fill(0, $size, null));
}

function qr_place_function_patterns(array &$m, int $version): void
{
    $size = count($m);
    // reserva das áreas de formato: 15 módulos em cada cópia (inclui o dark module)
    for ($i = 0; $i <= 5; $i++) { $m[8][$i] = 2; $m[$i][8] = 2; }
    $m[8][7] = 2; $m[7][8] = 2;
    $m[8][8] = 2; $m[8][$size - 8] = 2;
    for ($i = $size - 7; $i < $size; $i++) { $m[8][$i] = 2; $m[$i][8] = 2; }
    // dark module (fixo, não faz parte dos bits de formato)
    $m[$size - 8][8] = 1;
    // finder patterns + separators
    foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$r0, $c0]) {
        for ($r = -1; $r <= 7; $r++) for ($c = -1; $c <= 7; $c++) {
            $rr = $r0 + $r; $cc = $c0 + $c;
            if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) continue;
            $inner = ($r >= 0 && $r <= 6 && $c >= 0 && $c <= 6)
                  && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
            $m[$rr][$cc] = $inner ? 1 : 0;
        }
    }
    // timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        $m[6][$i] = $i % 2 === 0 ? 1 : 0;
        $m[$i][6] = $i % 2 === 0 ? 1 : 0;
    }
    // alignment patterns
    foreach (QR_ALIGN[$version] as $r0) foreach (QR_ALIGN[$version] as $c0) {
        if ($m[$r0][$c0] !== null) continue; // não sobrepor finders/timing
        for ($dr = -2; $dr <= 2; $dr++) for ($dc = -2; $dc <= 2; $dc++) {
            $m[$r0 + $dr][$c0 + $dc] = (max(abs($dr), abs($dc)) !== 1) ? 1 : 0;
        }
    }
}

function qr_masked(int $id, int $r, int $c): bool
{
    return match ($id) {
        0 => ($r + $c) % 2 === 0,
        1 => $r % 2 === 0,
        2 => $c % 3 === 0,
        3 => ($r + $c) % 3 === 0,
        4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0,
        5 => (($r * $c) % 2) + (($r * $c) % 3) === 0,
        6 => ((($r * $c) % 2) + (($r * $c) % 3)) % 2 === 0,
        7 => (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0,
    };
}

function qr_place_data(array $m, array $codewords, int $maskId): array
{
    $size = count($m);
    $out = $m;
    $bitIndex = 0;
    $totalBits = count($codewords) * 8;
    $upward = true;
    for ($col = $size - 1; $col > 0; $col -= 2) {
        if ($col === 6) $col--; // pula a coluna do timing vertical
        for ($i = 0; $i < $size; $i++) {
            $row = $upward ? $size - 1 - $i : $i;
            foreach ([$col, $col - 1] as $c) {
                if ($out[$row][$c] !== null) continue; // módulo de função
                $bit = $bitIndex < $totalBits
                    ? ($codewords[intdiv($bitIndex, 8)] >> (7 - ($bitIndex % 8))) & 1
                    : 0;
                $bitIndex++;
                if (qr_masked($maskId, $row, $c)) $bit ^= 1;
                $out[$row][$c] = $bit;
            }
        }
        $upward = !$upward;
    }
    return $out;
}

function qr_bch_format(int $data5): int
{
    $rem = $data5 << 10;
    for ($i = 14; $i >= 10; $i--) {
        if (($rem >> $i) & 1) $rem ^= 0b10100110111 << ($i - 10);
    }
    return (($data5 << 10) | $rem) ^ 0b101010000010010;
}

function qr_write_format_bits(array $m, int $maskId): array
{
    $size = count($m);
    $bits = qr_bch_format(0b01000 | $maskId); // nível L (01) + máscara
    // bits em ordem MSB-first (bit 14 .. 0)
    $get = fn(int $i) => ($bits >> $i) & 1;
    // canto superior esquerdo
    $pos1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
    foreach ($pos1 as $idx => [$r, $c]) $m[$r][$c] = $get(14 - $idx);
    // cantos direito/inferior: 7 + 8 = 15 bits (bit 13 .. 0)
    $pos2 = [];
    for ($i = 0; $i < 7; $i++) $pos2[] = [$size - 1 - $i, 8];
    for ($i = 0; $i < 8; $i++) $pos2[] = [8, $size - 8 + $i];
    foreach ($pos2 as $idx => [$r, $c]) $m[$r][$c] = $get(14 - $idx);
    return $m;
}

function qr_penalty(array $m): int
{
    $size = count($m);
    $pen = 0;
    // regra 1: sequências de 5+ módulos iguais em linhas e colunas
    foreach ([true, false] as $isRow) {
        for ($a = 0; $a < $size; $a++) {
            $runLen = 1; $runVal = $m[$isRow ? $a : 0][$isRow ? 0 : $a];
            for ($b = 1; $b < $size; $b++) {
                $v = $m[$isRow ? $a : $b][$isRow ? $b : $a];
                if ($v === $runVal) { $runLen++; }
                else { if ($runLen >= 5) $pen += $runLen - 2; $runLen = 1; $runVal = $v; }
            }
            if ($runLen >= 5) $pen += $runLen - 2;
        }
    }
    // regra 2: blocos 2x2 idênticos
    for ($r = 0; $r < $size - 1; $r++) for ($c = 0; $c < $size - 1; $c++) {
        $v = $m[$r][$c];
        if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) $pen += 3;
    }
    // regra 3: padrões 1011101 0000 (ou reverso) nas bordas das linhas/colunas
    $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
    $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($a = 0; $a < $size; $a++) {
        for ($b = 0; $b + 11 <= $size; $b++) {
            $seq = [];
            for ($k = 0; $k < 11; $k++) $seq[] = $m[$a][$b + $k];
            if ($seq === $pat1 || $seq === $pat2) $pen += 40;
            $seq = [];
            for ($k = 0; $k < 11; $k++) $seq[] = $m[$b + $k][$a];
            if ($seq === $pat1 || $seq === $pat2) $pen += 40;
        }
    }
    // regra 4: proporção de módulos escuros
    $dark = 0;
    for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) if ($m[$r][$c]) $dark++;
    $pct = $dark * 100 / ($size * $size);
    $pen += (int)(floor(abs($pct - 50) / 5) * 10);
    return $pen;
}

/** Retorna a matriz final (1 = preto, 0 = branco). */
function qr_encode_matrix(string $text): array
{
    $version = qr_version_for_len(strlen($text));
    $size = 17 + 4 * $version;
    $codewords = qr_data_codewords($text, $version);

    $base = qr_new_matrix($size);
    qr_place_function_patterns($base, $version);

    $best = null; $bestPen = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $m = qr_write_format_bits(qr_place_data($base, $codewords, $mask), $mask);
        $p = qr_penalty($m);
        if ($p < $bestPen) { $bestPen = $p; $best = $m; }
    }
    return $best;
}

/** PNG binário do QR (sem dependências: usa zlib). $modulePx = pixels por módulo. */
function qr_to_png(string $text, int $modulePx = 8): string
{
    $m = qr_encode_matrix($text);
    $size = count($m);
    $quiet = 4;
    $dim = ($size + 2 * $quiet) * $modulePx;

    // scanlines: 1 byte de filtro (0) + RGB por pixel (tudo preto ou branco)
    $rows = '';
    for ($y = 0; $y < $dim; $y++) {
        $row = "\x00";
        $r = intdiv($y, $modulePx) - $quiet;
        for ($x = 0; $x < $dim; $x += $modulePx) {
            $c = intdiv($x, $modulePx) - $quiet;
            $dark = $r >= 0 && $r < $size && $c >= 0 && $c < $size && $m[$r][$c];
            $px = $dark ? "\x00\x00\x00" : "\xff\xff\xff";
            $row .= str_repeat($px, $modulePx);
        }
        $rows .= $row;
    }

    $chunk = function (string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data
             . pack('N', crc32($type . $data));
    };

    return "\x89PNG\r\n\x1a\n"
         . $chunk('IHDR', pack('N*N*C5', $dim, $dim, 8, 2, 0, 0, 0))
         . $chunk('IDAT', gzcompress($rows, 9))
         . $chunk('IEND', '');
}

function qr_to_svg(string $text, int $modulePx = 6): string
{
    $m = qr_encode_matrix($text);
    $size = count($m);
    $quiet = 4;
    $dim = ($size + 2 * $quiet) * $modulePx;
    $rects = '';
    for ($r = 0; $r < $size; $r++) for ($c = 0; $c < $size; $c++) {
        if ($m[$r][$c]) {
            $x = ($c + $quiet) * $modulePx;
            $y = ($r + $quiet) * $modulePx;
            $rects .= "<rect x=\"$x\" y=\"$y\" width=\"$modulePx\" height=\"$modulePx\"/>";
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim
         . '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/>'
         . '<g fill="#000">' . $rects . '</g></svg>';
}

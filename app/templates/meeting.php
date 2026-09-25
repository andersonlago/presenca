<?php include __DIR__ . '/layout_top.php'; ?>
<h1 class="page-title"><?= e($meeting['title']) ?></h1>
<div class="card">
  <div class="qr-meta" style="margin-top:0">
    <div class="kv"><b>Início do registro</b><?= e($meeting['starts_at']) ?> (UTC)</div>
    <div class="kv"><b>Fim do registro</b><?= e($meeting['ends_at']) ?> (UTC)</div>
  </div>

  <?php if ($myAttendance): ?>
    <div class="flash ok">Presença registrada em <?= e($myAttendance['recorded_at']) ?> (UTC) a partir do IP <?= e($myAttendance['ip']) ?>.</div>
  <?php elseif (!$isOwner): ?>
    <p class="muted">Somente o criador da reunião pode registrar presença nesta versão limitada.</p>
  <?php elseif ($status === 'future'): ?>
    <div class="flash err">O período de registro ainda não começou.</div>
  <?php elseif ($status === 'past'): ?>
    <div class="flash err">O período de registro já encerrou.</div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit">Registrar minha presença agora</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($isOwner): ?>
<div class="card" id="qr-card">
  <h2>QR Code de presença</h2>
  <p class="muted">
    Quem escanear este QR abre a página pública de registro (nome + CPF) e tem a presença
    registrada com dia/hora e IP. O código do QR muda a cada <?= (int)$qrMinutes ?> minuto(s)
    enquanto esta página ficar aberta — evite compartilhar foto/print do QR fora da reunião.
  </p>
  <div class="qr-flex">
    <div id="qr-box"><?= $qrSvg /* SVG gerado localmente, sem HTML de usuário */ ?></div>
    <div class="qr-meta" style="margin:0; grid-template-columns:1fr">
      <div class="kv"><b>Link direto</b><a href="<?= e($qrUrl) ?>" style="word-break:break-all"><?= e($qrUrl) ?></a></div>
      <div class="kv"><b>Código atual do QR</b><strong id="qr-code-label"><?= e($meeting['checkin_code']) ?></strong></div>
      <div class="kv"><b>Próxima atualização</b><span id="qr-countdown">—</span></div>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn btn-accent" href="/meeting.php?id=<?= (int)$meeting['id'] ?>&qr=1" target="_blank">Baixar PNG do QR</a>
    <form method="post" style="margin:0">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="regen_qr">
      <button type="submit" class="btn-outline" style="background:#fff">Gerar novo código do QR agora</button>
    </form>
  </div>
  <script>
    // QR dinâmico: regenera o SVG no navegador a cada janela de rotação.
    (function () {
      const MEETING_ID = <?= (int)$meeting['id'] ?>;
      const ROTATE_MS = <?= (int)$qrMinutes * 60 * 1000 ?>;
      const box = document.getElementById('qr-box');
      const label = document.getElementById('qr-code-label');
      const cd = document.getElementById('qr-countdown');
      const TOKEN = '<?= e($meeting['public_token']) ?>';

      // ---- Gerador de QR embutido (port do PHP, ISO/IEC 18004, ECC L, versões 1..4) ----
      const BLOCKS = {1:[26,7,1,19],2:[44,10,1,34],3:[70,15,1,55],4:[100,20,1,80]};
      const BYTE_CAP = {1:17,2:32,3:53,4:78};
      const ALIGN = {1:[],2:[6,18],3:[6,22],4:[6,26]};
      let EXP = null, LOG = null;
      function gf() {
        if (EXP) return;
        EXP = new Array(512).fill(0); LOG = new Array(256).fill(0);
        let x = 1;
        for (let i = 0; i < 255; i++) { EXP[i] = x; LOG[x] = i; x <<= 1; if (x & 0x100) x ^= 0x11d; }
        for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
      }
      function polyMul(a, b) {
        gf();
        const r = new Array(a.length + b.length - 1).fill(0);
        for (let i = 0; i < a.length; i++) { if (!a[i]) continue;
          for (let j = 0; j < b.length; j++) { if (!b[j]) continue;
            r[i + j] ^= EXP[LOG[a[i]] + LOG[b[j]]]; } }
        return r;
      }
      function genPoly(deg) {
        let g = [1];
        for (let i = 0; i < deg; i++) g = polyMul(g, [1, 1 << i]);
        return g;
      }
      function ecBytes(data, ecCount) {
        gf();
        const gen = genPoly(ecCount);
        const res = data.concat(new Array(ecCount).fill(0));
        for (let i = 0; i < data.length; i++) { const f = data[i]; if (!f) continue;
          for (let j = 0; j < gen.length; j++) { if (!gen[j]) continue;
            res[i + j] ^= EXP[LOG[f] + LOG[gen[j]]]; } }
        return res.slice(-ecCount);
      }
      function versionFor(n) { for (const v in BYTE_CAP) { if (n <= BYTE_CAP[v]) return +v; } throw 'texto longo'; }
      function dataCodewords(bytes, version) {
        const [, ecPerBlock, numBlocks, dataCount] = BLOCKS[version];
        const bits = [];
        const push = (val, len) => { for (let i = len - 1; i >= 0; i--) bits.push((val >> i) & 1); };
        push(0b0100, 4); push(bytes.length, 8);
        for (const b of bytes) push(b, 8);
        push(0, Math.min(4, dataCount * 8 - bits.length));
        if (bits.length % 8) push(0, 8 - (bits.length % 8));
        const cw = [];
        for (let i = 0; i < bits.length; i += 8) { let by = 0; for (let j = 0; j < 8; j++) by = (by << 1) | bits[i + j]; cw.push(by); }
        const pad = [0xEC, 0x11]; let k = 0;
        while (cw.length < dataCount) cw.push(pad[k++ % 2]);
        return cw.concat(ecBytes(cw, ecPerBlock)); // versões 1..4 nível L: bloco único
      }
      function placePatterns(m, version) {
        const size = m.length;
        for (let i = 0; i <= 5; i++) { m[8][i] = 2; m[i][8] = 2; }
        m[8][7] = 2; m[7][8] = 2; m[8][8] = 2; m[8][size - 8] = 2;
        for (let i = size - 7; i < size; i++) { m[8][i] = 2; m[i][8] = 2; }
        m[size - 8][8] = 1;
        for (const [r0, c0] of [[0,0],[size-7,0],[0,size-7]])
          for (let r = -1; r <= 7; r++) for (let c = -1; c <= 7; c++) {
            const rr = r0 + r, cc = c0 + c;
            if (rr < 0 || rr >= size || cc < 0 || cc >= size) continue;
            const inner = (r >= 0 && r <= 6 && c >= 0 && c <= 6) &&
              (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4));
            m[rr][cc] = inner ? 1 : 0;
          }
        for (let i = 8; i < size - 8; i++) { m[6][i] = i % 2 === 0 ? 1 : 0; m[i][6] = i % 2 === 0 ? 1 : 0; }
        for (const r0 of ALIGN[version]) for (const c0 of ALIGN[version]) {
          if (m[r0][c0] !== null) continue;
          for (let dr = -2; dr <= 2; dr++) for (let dc = -2; dc <= 2; dc++)
            m[r0 + dr][c0 + dc] = (Math.max(Math.abs(dr), Math.abs(dc)) !== 1) ? 1 : 0;
        }
      }
      function masked(id, r, c) {
        switch (id) {
          case 0: return (r + c) % 2 === 0;
          case 1: return r % 2 === 0;
          case 2: return c % 3 === 0;
          case 3: return (r + c) % 3 === 0;
          case 4: return (Math.floor(r / 2) + Math.floor(c / 3)) % 2 === 0;
          case 5: return ((r * c) % 2) + ((r * c) % 3) === 0;
          case 6: return (((r * c) % 2) + ((r * c) % 3)) % 2 === 0;
          default: return ((((r + c) % 2) + ((r * c) % 3)) % 2) === 0;
        }
      }
      function placeData(base, cw, maskId) {
        const size = base.length;
        const m = base.map(row => row.slice());
        let bi = 0; const total = cw.length * 8; let up = true;
        for (let col = size - 1; col > 0; col -= 2) {
          if (col === 6) col--;
          for (let i = 0; i < size; i++) {
            const row = up ? size - 1 - i : i;
            for (const c of [col, col - 1]) {
              if (m[row][c] !== null) continue;
              let bit = bi < total ? (cw[Math.floor(bi / 8)] >> (7 - (bi % 8))) & 1 : 0;
              bi++;
              if (masked(maskId, row, c)) bit ^= 1;
              m[row][c] = bit;
            }
          }
          up = !up;
        }
        return m;
      }
      function bchFormat(d5) {
        let rem = d5 << 10;
        for (let i = 14; i >= 10; i--) if ((rem >> i) & 1) rem ^= (0b10100110111 << (i - 10));
        return ((d5 << 10) | rem) ^ 0b101010000010010;
      }
      function writeFormat(m, maskId) {
        const size = m.length, bits = bchFormat(0b01000 | maskId);
        const get = i => (bits >> i) & 1;
        const p1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        p1.forEach(([r, c], idx) => m[r][c] = get(14 - idx));
        const p2 = [];
        for (let i = 0; i < 7; i++) p2.push([size - 1 - i, 8]);
        for (let i = 0; i < 8; i++) p2.push([8, size - 8 + i]);
        p2.forEach(([r, c], idx) => m[r][c] = get(14 - idx));
        return m;
      }
      function penalty(m) {
        const size = m.length; let pen = 0;
        for (const isRow of [true, false])
          for (let a = 0; a < size; a++) {
            let run = 1, v = isRow ? m[a][0] : m[0][a];
            for (let b = 1; b < size; b++) {
              const cur = isRow ? m[a][b] : m[b][a];
              if (cur === v) run++; else { if (run >= 5) pen += run - 2; run = 1; v = cur; }
            }
            if (run >= 5) pen += run - 2;
          }
        for (let r = 0; r < size - 1; r++) for (let c = 0; c < size - 1; c++) {
          const v = m[r][c];
          if (v === m[r][c+1] && v === m[r+1][c] && v === m[r+1][c+1]) pen += 3;
        }
        const pat1 = [1,0,1,1,1,0,1,0,0,0,0], pat2 = [0,0,0,0,1,0,1,1,1,0,1];
        for (let a = 0; a < size; a++) for (let b = 0; b + 11 <= size; b++) {
          let s1 = [], s2 = [];
          for (let k = 0; k < 11; k++) { s1.push(m[a][b+k]); s2.push(m[b+k][a]); }
          if (String(s1) === String(pat1) || String(s1) === String(pat2)) pen += 40;
          if (String(s2) === String(pat1) || String(s2) === String(pat2)) pen += 40;
        }
        let dark = 0;
        for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) if (m[r][c]) dark++;
        pen += Math.floor(Math.abs(dark * 100 / (size * size) - 50) / 5) * 10;
        return pen;
      }
      function qrMatrix(text) {
        const bytes = Array.from(new TextEncoder().encode(text));
        const version = versionFor(bytes.length);
        const size = 17 + 4 * version;
        const cw = dataCodewords(bytes, version);
        const base = Array.from({length: size}, () => new Array(size).fill(null));
        placePatterns(base, version);
        let best = null, bestPen = Infinity;
        for (let mask = 0; mask < 8; mask++) {
          const m = writeFormat(placeData(base, cw, mask), mask);
          const p = penalty(m);
          if (p < bestPen) { bestPen = p; best = m; }
        }
        return best;
      }
      function qrSvg(text, px) {
        const m = qrMatrix(text), size = m.length, quiet = 4, dim = (size + 2 * quiet) * px;
        let rects = '';
        for (let r = 0; r < size; r++) for (let c = 0; c < size; c++) if (m[r][c])
          rects += `<rect x="${(c + quiet) * px}" y="${(r + quiet) * px}" width="${px}" height="${px}"/>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${dim} ${dim}" width="${dim}" height="${dim}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><g fill="#000">${rects}</g></svg>`;
      }
      // ---- fim do gerador ----

      function draw(code) {
        const url = location.origin + '/checkin.php?token=' + TOKEN + '&c=' + code;
        box.innerHTML = qrSvg(url, 6);
        label.textContent = code;
      }
      let lastWin = -1;
      async function refresh() {
        try {
          const r = await fetch('/meeting.php?id=' + MEETING_ID + '&code=1', {credentials: 'same-origin'});
          const j = await r.json();
          if (j.code) draw(j.code);
        } catch (e) { /* servidor indisponível: mantém o último QR */ }
      }
      function tick() {
        const now = Date.now();
        const win = Math.floor(now / ROTATE_MS);   // janelas alinhadas ao relógio (igual ao servidor)
        if (win !== lastWin) { lastWin = win; refresh(); }
        cd.textContent = Math.ceil((ROTATE_MS - (now % ROTATE_MS)) / 1000) + 's';
      }
      tick(); setInterval(tick, 1000);
    })();
  </script>
</div>
<?php endif; ?>

<?php if ($isOwner): ?>
  <h2 class="page-title" style="font-size:1.25rem">Lista de presenças</h2>
  <?php if (!$attendees && !$publicAttendees): ?>
    <div class="card"><p class="muted" style="margin:0">Nenhum registro ainda.</p></div>
  <?php else: ?>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Nome / E-mail</th><th>Dia/Hora do registro (UTC)</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($attendees as $a): ?>
        <tr><td><?= e($a['email']) ?> <span class="badge">conta</span></td><td><?= e($a['recorded_at']) ?></td><td><?= e($a['ip']) ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ($publicAttendees as $a): ?>
        <tr><td><?= e($a['name']) ?> · <?= e($a['email']) ?> · CPF <?= e($a['cpf']) ?> <span class="badge">QR</span></td><td><?= e($a['recorded_at']) ?></td><td><?= e($a['ip']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>

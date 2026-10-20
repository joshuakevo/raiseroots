@extends('layouts.app')
@section('title', 'Document Stamp')
@section('breadcrumb')
    <li class="breadcrumb-item active">Document Stamp</li>
@endsection
@php
    $stampOrg    = strtoupper(\App\Models\SystemSetting::get('org_name', 'ElTech Finance'));
    $stampBottom = strtoupper(trim((string) \App\Models\SystemSetting::get('org_address', '')));
@endphp
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h6 class="fw-semibold mb-0"><i class="bi bi-patch-check text-primary me-2"></i>Document Stamp</h6>
        <small class="text-muted">Upload a PDF or image, place a dated stamp on it, and download it. The document never leaves this browser; nothing is uploaded or stored.</small>
    </div>
</div>

<div class="row g-3">
    {{-- Settings --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header small fw-semibold py-2">1. Document</div>
            <div class="card-body">
                <input type="file" id="docFile" class="form-control form-control-sm" accept="application/pdf,image/png,image/jpeg">
                <div class="form-text">PDF, PNG or JPG.</div>
                <div id="docError" class="alert alert-danger small py-2 mt-2 mb-0 d-none"></div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header small fw-semibold py-2">2. Stamp</div>
            <div class="card-body small">
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Shape</label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="shape" id="shapeRound" value="round" checked>
                        <label class="btn btn-outline-secondary" for="shapeRound"><i class="bi bi-circle me-1"></i>Round</label>
                        <input type="radio" class="btn-check" name="shape" id="shapeRect" value="rect">
                        <label class="btn btn-outline-secondary" for="shapeRect"><i class="bi bi-square me-1"></i>Rectangle</label>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Organisation (top text)</label>
                    <input type="text" id="stTop" class="form-control form-control-sm" maxlength="60" value="{{ $stampOrg }}">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Stamp word</label>
                    <select id="stWordSel" class="form-select form-select-sm">
                        @foreach(['APPROVED', 'RECEIVED', 'PAID', 'VERIFIED', 'CERTIFIED TRUE COPY', 'ENTERED', 'CANCELLED'] as $w)
                            <option value="{{ $w }}">{{ $w }}</option>
                        @endforeach
                        <option value="__custom">Custom…</option>
                    </select>
                    <input type="text" id="stWordCustom" class="form-control form-control-sm mt-1 d-none" maxlength="40" placeholder="Your wording">
                </div>
                <div class="mb-2" id="bottomWrap">
                    <label class="form-label fw-semibold mb-1">Bottom text <span class="text-muted fw-normal">(round stamp)</span></label>
                    <input type="text" id="stBottom" class="form-control form-control-sm" maxlength="50" value="{{ $stampBottom }}" placeholder="e.g. KAMPALA - UGANDA">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-7">
                        <label class="form-label fw-semibold mb-1">Date</label>
                        <input type="date" id="stDate" class="form-control form-control-sm" value="{{ today()->toDateString() }}">
                    </div>
                    <div class="col-5 d-flex align-items-end">
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" id="stShowDate" checked>
                            <label class="form-check-label" for="stShowDate">Show date</label>
                        </div>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1">Colour</label>
                    <div class="d-flex gap-2" id="colourPick">
                        @foreach(['#1d3fb8' => 'Blue', '#c1121f' => 'Red', '#0f7b3e' => 'Green', '#5b21b6' => 'Purple', '#111827' => 'Black'] as $hex => $name)
                            <button type="button" class="stamp-swatch {{ $loop->first ? 'active' : '' }}" data-colour="{{ $hex }}" title="{{ $name }}" style="background: {{ $hex }}"></button>
                        @endforeach
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1 d-flex justify-content-between">Size <span class="text-muted fw-normal" id="sizeVal"></span></label>
                    <input type="range" class="form-range" id="stSize" min="10" max="50" value="24">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1 d-flex justify-content-between">Tilt <span class="text-muted fw-normal" id="tiltVal"></span></label>
                    <input type="range" class="form-range" id="stTilt" min="-25" max="25" value="-8">
                </div>
                <div class="mb-2">
                    <label class="form-label fw-semibold mb-1 d-flex justify-content-between">Ink strength <span class="text-muted fw-normal" id="opacityVal"></span></label>
                    <input type="range" class="form-range" id="stOpacity" min="50" max="100" value="88">
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="stTexture" checked>
                    <label class="form-check-label" for="stTexture">Rubber-stamp ink texture</label>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header small fw-semibold py-2">3. Apply &amp; download</div>
            <div class="card-body small">
                <label class="form-label fw-semibold mb-1">Stamp which pages?</label>
                <select id="stPages" class="form-select form-select-sm mb-3">
                    <option value="current">This page only (the one shown)</option>
                    <option value="first">First page</option>
                    <option value="last">Last page</option>
                    <option value="all">Every page</option>
                </select>
                <button type="button" id="downloadBtn" class="btn btn-primary w-100" disabled>
                    <i class="bi bi-download me-1"></i>Download stamped document
                </button>
                <div class="form-text">Drag the stamp on the preview to place it. The same position is used on every stamped page.</div>
            </div>
        </div>
    </div>

    {{-- Preview --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="small fw-semibold">Preview</span>
                <div class="d-flex align-items-center gap-2 d-none" id="pageNav">
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="prevPage"><i class="bi bi-chevron-left"></i></button>
                    <span class="small" id="pageLabel"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="nextPage"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
            <div class="card-body bg-light d-flex justify-content-center" style="min-height: 460px">
                <div id="emptyState" class="text-muted text-center align-self-center">
                    <i class="bi bi-file-earmark-arrow-up fs-1 d-block mb-2"></i>Choose a document to start
                </div>
                <div id="stage" class="position-relative d-none shadow-sm" style="line-height:0">
                    <canvas id="pageCanvas"></canvas>
                    <img id="stampOverlay" alt="Stamp" draggable="false">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .stamp-swatch { width: 28px; height: 28px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px #d1d5db; cursor: pointer; padding: 0; }
    .stamp-swatch.active { box-shadow: 0 0 0 2px #2563eb; }
    #stage { max-width: 100%; background: #fff; touch-action: none; }
    #pageCanvas { max-width: 100%; height: auto; display: block; }
    #stampOverlay { position: absolute; cursor: grab; user-select: none; touch-action: none; }
    #stampOverlay.dragging { cursor: grabbing; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25)); }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
<script>
(function () {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const $ = id => document.getElementById(id);
    const state = {
        kind: null,          // 'pdf' | 'image'
        fileName: 'document',
        mime: null,
        pdfBytes: null,      // original PDF bytes (kept for pdf-lib)
        pdf: null,           // pdf.js document (preview)
        image: null,         // HTMLImageElement for image documents
        page: 1, pageCount: 1,
        cx: 0.74, cy: 0.80,  // stamp centre as fractions of the page shown
        colour: '#1d3fb8',
        stampCanvas: null,
    };

    // ── Stamp drawing ──────────────────────────────────────────────────────
    const MONTHS = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];
    function dateText() {
        const v = $('stDate').value;
        if (!v) return '';
        const [y, m, d] = v.split('-').map(Number);
        return d + ' ' + MONTHS[m - 1] + ' ' + y;
    }
    function word() {
        return $('stWordSel').value === '__custom' ? ($('stWordCustom').value.trim().toUpperCase() || 'APPROVED') : $('stWordSel').value;
    }
    function options() {
        return {
            shape: document.querySelector('input[name="shape"]:checked').value,
            top: $('stTop').value.trim().toUpperCase(),
            bottom: $('stBottom').value.trim().toUpperCase(),
            word: word(),
            date: $('stShowDate').checked ? dateText() : '',
            colour: state.colour,
            tilt: Number($('stTilt').value),
            opacity: Number($('stOpacity').value) / 100,
            texture: $('stTexture').checked,
        };
    }

    // Text along a circle, centred on centerAngle; top arc reads clockwise, bottom anti-clockwise (both upright).
    function arcText(ctx, text, cx, cy, r, centerAngle, isTop, fontPx, maxAngle) {
        if (!text) return;
        let size = fontPx, widths, total, spacing;
        const chars = [...text];
        do {
            ctx.font = `bold ${size}px Arial, Helvetica, sans-serif`;
            spacing = size * 0.14;
            widths = chars.map(c => ctx.measureText(c).width);
            total = widths.reduce((a, b) => a + b, 0) + spacing * (chars.length - 1);
            size *= 0.94;
        } while (total / r > maxAngle && size > 8);
        let angle = isTop ? centerAngle - (total / r) / 2 : centerAngle + (total / r) / 2;
        chars.forEach((ch, i) => {
            const half = (widths[i] / 2) / r;
            const a = isTop ? angle + half : angle - half;
            ctx.save();
            ctx.translate(cx + r * Math.cos(a), cy + r * Math.sin(a));
            ctx.rotate(isTop ? a + Math.PI / 2 : a - Math.PI / 2);
            ctx.fillText(ch, 0, 0);
            ctx.restore();
            angle = isTop ? angle + (widths[i] + spacing) / r : angle - (widths[i] + spacing) / r;
        });
    }

    // Fit text into maxWidth: one line, or two lines if it has spaces and one line gets too small.
    function fitLines(ctx, text, maxWidth, maxFont, minFont) {
        const fits = (lines, size) => {
            ctx.font = `900 ${size}px Arial, Helvetica, sans-serif`;
            return lines.every(l => ctx.measureText(l).width <= maxWidth);
        };
        let size = maxFont;
        while (size > minFont && !fits([text], size)) size -= 2;
        if (fits([text], size) || !text.includes(' ')) return { lines: [text], size: Math.max(size, minFont) };
        const words = text.split(' ');
        let best = [text], bestDiff = Infinity;
        for (let i = 1; i < words.length; i++) {
            const a = words.slice(0, i).join(' '), b = words.slice(i).join(' ');
            ctx.font = `900 ${maxFont}px Arial`;
            const diff = Math.abs(ctx.measureText(a).width - ctx.measureText(b).width);
            if (diff < bestDiff) { bestDiff = diff; best = [a, b]; }
        }
        size = maxFont;
        while (size > minFont && !fits(best, size)) size -= 2;
        return { lines: best, size };
    }

    function star(ctx, x, y, r) {
        ctx.beginPath();
        for (let i = 0; i < 10; i++) {
            const rr = i % 2 ? r * 0.45 : r, a = -Math.PI / 2 + i * Math.PI / 5;
            ctx[i ? 'lineTo' : 'moveTo'](x + rr * Math.cos(a), y + rr * Math.sin(a));
        }
        ctx.closePath();
        ctx.fill();
    }

    function drawBase(o) {
        const S = 760;
        const round = o.shape === 'round';
        const W = round ? S : Math.round(S * 1.9), H = round ? S : Math.round(S * 0.82);
        const c = document.createElement('canvas');
        c.width = W; c.height = H;
        const x = c.getContext('2d');
        x.strokeStyle = x.fillStyle = o.colour;
        x.textAlign = 'center'; x.textBaseline = 'middle';
        const circle = r => { x.beginPath(); x.arc(W / 2, H / 2, r, 0, Math.PI * 2); x.stroke(); };

        if (round) {
            const cx = W / 2, cy = H / 2, R = S / 2 - 16;
            x.lineWidth = S * 0.03;  circle(R);
            x.lineWidth = S * 0.011; circle(R - S * 0.048);
            const rIn = R * 0.6;
            x.lineWidth = S * 0.011; circle(rIn);
            const rText = (R - S * 0.048 + rIn) / 2;
            arcText(x, o.top, cx, cy, rText, -Math.PI / 2, true, S * 0.082, 2.75);
            arcText(x, o.bottom, cx, cy, rText, Math.PI / 2, false, S * 0.064, 1.9);
            star(x, cx - rText, cy, S * 0.028);
            star(x, cx + rText, cy, S * 0.028);

            const fit = fitLines(x, o.word, rIn * 1.72, S * 0.13, S * 0.05);
            const lineH = fit.size * 1.05;
            const blockH = lineH * fit.lines.length + (o.date ? S * 0.1 : 0);
            let y = cy - blockH / 2 + lineH / 2;
            x.font = `900 ${fit.size}px Arial, Helvetica, sans-serif`;
            fit.lines.forEach(l => { x.fillText(l, cx, y); y += lineH; });
            if (o.date) {
                x.lineWidth = S * 0.008;
                x.beginPath(); x.moveTo(cx - rIn * 0.78, y - lineH * 0.42); x.lineTo(cx + rIn * 0.78, y - lineH * 0.42); x.stroke();
                x.font = `bold ${S * 0.068}px Arial, Helvetica, sans-serif`;
                x.fillText(o.date, cx, y + S * 0.03);
            }
        } else {
            const pad = S * 0.03;
            x.lineWidth = S * 0.03;  x.strokeRect(pad, pad, W - pad * 2, H - pad * 2);
            x.lineWidth = S * 0.011; x.strokeRect(pad + S * 0.045, pad + S * 0.045, W - (pad + S * 0.045) * 2, H - (pad + S * 0.045) * 2);
            const inner = W - (pad + S * 0.045) * 2 - S * 0.08;
            if (o.top) {
                const t = fitLines(x, o.top, inner, S * 0.075, S * 0.035);
                x.font = `bold ${t.size}px Arial, Helvetica, sans-serif`;
                x.fillText(t.lines.join(' '), W / 2, H * 0.24);
            }
            const fit = fitLines(x, o.word, inner, S * 0.17, S * 0.06);
            x.font = `900 ${fit.size}px Arial, Helvetica, sans-serif`;
            const lineH = fit.size * 1.02;
            let y = H * 0.52 - (lineH * (fit.lines.length - 1)) / 2;
            fit.lines.forEach(l => { x.fillText(l, W / 2, y); y += lineH; });
            if (o.date) {
                x.font = `bold ${S * 0.07}px Arial, Helvetica, sans-serif`;
                x.fillText(o.date, W / 2, H * 0.8);
            }
        }

        // Rubber-stamp look: knock tiny specks out of the ink.
        if (o.texture) {
            x.globalCompositeOperation = 'destination-out';
            let seed = 7;
            const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
            for (let i = 0; i < 2600; i++) {
                x.globalAlpha = 0.25 + rnd() * 0.75;
                x.beginPath();
                x.arc(rnd() * W, rnd() * H, 0.6 + rnd() * 2.4, 0, Math.PI * 2);
                x.fill();
            }
            x.globalAlpha = 1;
            x.globalCompositeOperation = 'source-over';
        }
        return c;
    }

    // Final stamp: tilted and at the chosen ink strength, on a transparent canvas.
    function buildStamp() {
        const o = options();
        const base = drawBase(o);
        const a = o.tilt * Math.PI / 180;
        const w = base.width, h = base.height;
        const bw = Math.ceil(Math.abs(w * Math.cos(a)) + Math.abs(h * Math.sin(a)));
        const bh = Math.ceil(Math.abs(w * Math.sin(a)) + Math.abs(h * Math.cos(a)));
        const c = document.createElement('canvas');
        c.width = bw; c.height = bh;
        const x = c.getContext('2d');
        x.globalAlpha = o.opacity;
        x.translate(bw / 2, bh / 2);
        x.rotate(a);
        x.drawImage(base, -w / 2, -h / 2);
        state.stampCanvas = c;
        $('stampOverlay').src = c.toDataURL('image/png');
        placeOverlay();
    }

    // ── Preview & dragging ──────────────────────────────────────────────────
    const canvas = $('pageCanvas'), overlay = $('stampOverlay'), stage = $('stage');

    function sizeFraction() { return Number($('stSize').value) / 100; }

    function placeOverlay() {
        if (!state.stampCanvas || stage.classList.contains('d-none')) return;
        const rect = canvas.getBoundingClientRect();
        const w = rect.width * sizeFraction();
        const h = w * state.stampCanvas.height / state.stampCanvas.width;
        overlay.style.width = w + 'px';
        overlay.style.height = h + 'px';
        overlay.style.left = (state.cx * rect.width - w / 2) + 'px';
        overlay.style.top = (state.cy * rect.height - h / 2) + 'px';
    }

    let drag = null;
    overlay.addEventListener('pointerdown', e => {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        drag = { rect, dx: e.clientX - (rect.left + state.cx * rect.width), dy: e.clientY - (rect.top + state.cy * rect.height) };
        overlay.setPointerCapture(e.pointerId);
        overlay.classList.add('dragging');
    });
    overlay.addEventListener('pointermove', e => {
        if (!drag) return;
        state.cx = Math.min(1, Math.max(0, (e.clientX - drag.dx - drag.rect.left) / drag.rect.width));
        state.cy = Math.min(1, Math.max(0, (e.clientY - drag.dy - drag.rect.top) / drag.rect.height));
        placeOverlay();
    });
    overlay.addEventListener('pointerup', () => { drag = null; overlay.classList.remove('dragging'); });
    window.addEventListener('resize', placeOverlay);

    async function renderPage() {
        if (state.kind === 'pdf') {
            const page = await state.pdf.getPage(state.page);
            const base = page.getViewport({ scale: 1 });
            const target = Math.min(stage.parentElement.clientWidth - 32, 900);
            const vp = page.getViewport({ scale: (target / base.width) * (window.devicePixelRatio || 1) });
            canvas.width = vp.width; canvas.height = vp.height;
            canvas.style.width = (vp.width / (window.devicePixelRatio || 1)) + 'px';
            await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
            $('pageLabel').textContent = `Page ${state.page} of ${state.pageCount}`;
        } else {
            canvas.width = state.image.naturalWidth; canvas.height = state.image.naturalHeight;
            canvas.style.width = Math.min(stage.parentElement.clientWidth - 32, 900, state.image.naturalWidth) + 'px';
            canvas.getContext('2d').drawImage(state.image, 0, 0);
        }
        placeOverlay();
    }

    function showError(msg) { const el = $('docError'); el.textContent = msg; el.classList.toggle('d-none', !msg); }

    $('docFile').addEventListener('change', async e => {
        const file = e.target.files[0];
        showError('');
        if (!file) return;
        state.fileName = file.name.replace(/\.[^.]+$/, '');
        state.mime = file.type;
        try {
            if (file.type === 'application/pdf') {
                const bytes = new Uint8Array(await file.arrayBuffer());
                state.pdfBytes = bytes;
                state.pdf = await pdfjsLib.getDocument({ data: bytes.slice() }).promise;
                state.kind = 'pdf'; state.pageCount = state.pdf.numPages; state.page = 1;
            } else if (['image/png', 'image/jpeg'].includes(file.type)) {
                state.image = await new Promise((res, rej) => {
                    const img = new Image();
                    img.onload = () => res(img); img.onerror = rej;
                    img.src = URL.createObjectURL(file);
                });
                state.kind = 'image'; state.pageCount = 1; state.page = 1;
            } else {
                return showError('Please choose a PDF, PNG or JPG file.');
            }
        } catch (err) {
            state.kind = null;
            return showError(err && err.name === 'PasswordException'
                ? 'This PDF is password-protected. Remove the password first, then stamp it.'
                : 'Could not open this file. It may be damaged or in an unsupported format.');
        }
        $('emptyState').classList.add('d-none');
        stage.classList.remove('d-none');
        $('pageNav').classList.toggle('d-none', state.kind !== 'pdf' || state.pageCount < 2);
        $('stPages').disabled = state.kind !== 'pdf';
        $('downloadBtn').disabled = false;
        await renderPage();
        buildStamp();
    });

    $('prevPage').addEventListener('click', () => { if (state.page > 1) { state.page--; renderPage(); } });
    $('nextPage').addEventListener('click', () => { if (state.page < state.pageCount) { state.page++; renderPage(); } });

    // ── Settings → redraw ────────────────────────────────────────────────────
    function syncLabels() {
        $('sizeVal').textContent = $('stSize').value + '% of page width';
        $('tiltVal').textContent = $('stTilt').value + '°';
        $('opacityVal').textContent = $('stOpacity').value + '%';
        $('bottomWrap').classList.toggle('d-none', document.querySelector('input[name="shape"]:checked').value !== 'round');
        $('stWordCustom').classList.toggle('d-none', $('stWordSel').value !== '__custom');
    }
    ['stTop', 'stBottom', 'stWordSel', 'stWordCustom', 'stDate', 'stShowDate', 'stTilt', 'stOpacity', 'stTexture', 'shapeRound', 'shapeRect']
        .forEach(id => $(id).addEventListener('input', () => { syncLabels(); buildStamp(); }));
    $('stSize').addEventListener('input', () => { syncLabels(); placeOverlay(); });
    document.querySelectorAll('.stamp-swatch').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('.stamp-swatch').forEach(s => s.classList.remove('active'));
        b.classList.add('active');
        state.colour = b.dataset.colour;
        buildStamp();
    }));
    syncLabels();
    buildStamp();

    // ── Export ─────────────────────────────────────────────────────────────
    function download(bytesOrBlob, name, type) {
        const blob = bytesOrBlob instanceof Blob ? bytesOrBlob : new Blob([bytesOrBlob], { type });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
    }

    async function exportPdf() {
        const { PDFDocument, degrees } = PDFLib;
        const doc = await PDFDocument.load(state.pdfBytes, { ignoreEncryption: true });
        const png = await doc.embedPng(await new Promise(r => state.stampCanvas.toBlob(b => b.arrayBuffer().then(r), 'image/png')));
        const pages = doc.getPages();
        const which = $('stPages').value;
        const targets = which === 'all' ? pages.map((_, i) => i)
            : which === 'first' ? [0] : which === 'last' ? [pages.length - 1] : [state.page - 1];
        const ratio = state.stampCanvas.height / state.stampCanvas.width;

        targets.forEach(i => {
            const page = pages[i];
            const { width: W, height: H } = page.getSize();
            const rot = ((page.getRotation().angle % 360) + 360) % 360;
            // Page as it's displayed (rotated pages swap width/height).
            const DW = (rot === 90 || rot === 270) ? H : W, DH = (rot === 90 || rot === 270) ? W : H;
            const w = DW * sizeFraction(), h = w * ratio;
            const dx = state.cx * DW, dy = state.cy * DH;
            // Displayed point -> unrotated page coordinates (PDF origin bottom-left).
            const [X, Y] = rot === 90 ? [dy, dx] : rot === 180 ? [W - dx, dy] : rot === 270 ? [W - dy, H - dx] : [dx, H - dy];
            // Counter-rotate the stamp so it reads upright once the viewer applies the page rotation.
            const t = rot * Math.PI / 180;
            const x0 = X - (w / 2 * Math.cos(t) - h / 2 * Math.sin(t));
            const y0 = Y - (w / 2 * Math.sin(t) + h / 2 * Math.cos(t));
            page.drawImage(png, { x: x0, y: y0, width: w, height: h, rotate: degrees(rot) });
        });

        download(await doc.save(), state.fileName + '-stamped.pdf', 'application/pdf');
    }

    function exportImage() {
        const img = state.image;
        const c = document.createElement('canvas');
        c.width = img.naturalWidth; c.height = img.naturalHeight;
        const x = c.getContext('2d');
        if (state.mime === 'image/jpeg') { x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); }
        x.drawImage(img, 0, 0);
        const w = c.width * sizeFraction(), h = w * state.stampCanvas.height / state.stampCanvas.width;
        x.drawImage(state.stampCanvas, state.cx * c.width - w / 2, state.cy * c.height - h / 2, w, h);
        const jpeg = state.mime === 'image/jpeg';
        c.toBlob(b => download(b, state.fileName + '-stamped.' + (jpeg ? 'jpg' : 'png')), jpeg ? 'image/jpeg' : 'image/png', 0.95);
    }

    $('downloadBtn').addEventListener('click', async () => {
        const btn = $('downloadBtn'), html = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Stamping…';
        try {
            if (state.kind === 'pdf') await exportPdf(); else exportImage();
        } catch (err) {
            console.error(err);
            showError('Could not stamp this document. If it is a protected or unusual PDF, try printing it to a new PDF first.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = html;
        }
    });
})();
</script>
@endpush

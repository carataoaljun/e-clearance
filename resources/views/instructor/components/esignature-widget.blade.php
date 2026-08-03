<div class="modal-bg" id="esigModal" style="z-index:1100;">
    <div class="modal" style="max-width:560px;width:96%;">
        <div class="modal-header">
            <div class="modal-title"><i class="bi bi-pen-fill" style="color:var(--accent2);"></i> My E-Signature</div>
            <button class="modal-close" onclick="closeEsigModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" style="padding:18px 20px;">

            <!-- Status chip -->
            <div id="esigStatusChip" style="margin-bottom:14px;display:none;"></div>

            <!-- Saved preview -->
            <div id="esigSavedPreview" style="display:none;margin-bottom:16px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
                            color:var(--muted,#64748b);margin-bottom:8px;">
                    <i class="bi bi-clock-history"></i> Currently Saved Signature
                </div>
                <div style="background:var(--surface,#0e1420);border:1px solid var(--border,#1e2a40);
                            border-radius:10px;padding:14px;text-align:center;">
                    <img id="esigSavedImg" src="" alt="saved signature"
                         style="max-width:260px;max-height:90px;object-fit:contain;filter:invert(1) brightness(1.6);">
                </div>
                <div id="esigSavedDate" style="font-size:11px;color:var(--muted);margin-top:6px;text-align:center;"></div>
                <div style="text-align:center;margin-top:10px;">
                    <button onclick="deleteEsig()" class="esig-btn esig-btn-danger">
                        <i class="bi bi-trash3-fill"></i> Delete Saved Signature
                    </button>
                </div>
            </div>

            <!-- Draw pad -->
            <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
                        color:var(--muted,#64748b);margin-bottom:8px;">
                <i class="bi bi-pencil-fill"></i> Draw Your Signature Below
            </div>

            <div style="position:relative;border:2px solid var(--border,#1e2a40);
                        border-radius:12px;overflow:hidden;background:#fff;cursor:crosshair;">
                <canvas id="esigCanvas" width="510" height="170"
                        style="display:block;width:100%;touch-action:none;"></canvas>
                <div id="esigHint" style="position:absolute;inset:0;display:flex;align-items:center;
                     justify-content:center;pointer-events:none;transition:opacity .3s;">
                    <span style="font-size:13px;color:#ccc;font-style:italic;">Sign here…</span>
                </div>
            </div>

            <!-- Pen controls -->
            <div style="display:flex;gap:10px;align-items:center;margin-top:10px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:11px;color:var(--muted);">Color</label>
                    <input type="color" id="esigColor" value="#000000"
                           style="width:32px;height:28px;border:none;border-radius:6px;cursor:pointer;background:none;">
                </div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <label style="font-size:11px;color:var(--muted);">Size</label>
                    <input type="range" id="esigSize" min="1" max="8" value="2"
                           style="width:80px;accent-color:var(--accent);">
                    <span id="esigSizeVal" style="font-size:11px;color:var(--muted);width:18px;">2</span>
                </div>
                <button onclick="clearCanvas()" class="esig-btn esig-btn-ghost" style="margin-left:auto;">
                    <i class="bi bi-eraser-fill"></i> Clear
                </button>
            </div>

            <!-- OR upload image -->
            <div style="display:flex;align-items:center;gap:10px;margin-top:14px;">
                <div style="flex:1;height:1px;background:var(--border,#1e2a40);"></div>
                <span style="font-size:11px;color:var(--muted);white-space:nowrap;">or upload signature image</span>
                <div style="flex:1;height:1px;background:var(--border,#1e2a40);"></div>
            </div>
            <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <label class="esig-btn esig-btn-ghost" style="cursor:pointer;">
                    <i class="bi bi-image"></i> Choose Image
                    <input type="file" id="esigUploadFile" accept="image/*"
                           style="display:none;" onchange="loadSigFromFile(this)">
                </label>
                <span id="esigUploadName" style="font-size:11px;color:var(--muted);"></span>
            </div>

            <!-- Preview of canvas before save -->
            <div id="esigPreviewWrap" style="display:none;margin-top:14px;">
                <div style="font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;
                            color:var(--muted);margin-bottom:6px;"><i class="bi bi-eye"></i> Preview</div>
                <div style="background:var(--surface,#0e1420);border:1px solid var(--border);
                            border-radius:10px;padding:12px;text-align:center;">
                    <img id="esigPreviewImg" src="" alt="preview"
                         style="max-width:260px;max-height:80px;object-fit:contain;filter:invert(1) brightness(1.6);">
                </div>
            </div>

        </div>
        <div class="modal-footer" style="gap:10px;">
            <button class="btn-cancel" onclick="closeEsigModal()">Cancel</button>
            <button onclick="previewEsig()" class="esig-btn esig-btn-secondary">
                <i class="bi bi-eye-fill"></i> Preview
            </button>
            <button onclick="saveEsig()" class="esig-btn esig-btn-primary" id="esigSaveBtn">
                <i class="bi bi-floppy-fill"></i> Save Signature
            </button>
        </div>
    </div>
</div>

<!-- Extra styles (scoped with esig- prefix) -->
<style>
.esig-btn {
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: opacity .15s, transform .1s;
    font-family: inherit;
}
.esig-btn:hover   { opacity: .85; transform: translateY(-1px); }
.esig-btn:active  { transform: translateY(0); }
.esig-btn-primary   { background: linear-gradient(135deg, var(--accent,#3b82f6), #6366f1); color: #fff; }
.esig-btn-secondary { background: rgba(99,102,241,.15); color: #818cf8; border: 1px solid rgba(99,102,241,.3); }
.esig-btn-danger    { background: rgba(244,63,94,.12); color: #f43f5e; border: 1px solid rgba(244,63,94,.25); }
.esig-btn-ghost     { background: rgba(100,116,139,.12); color: var(--muted,#64748b); border: 1px solid rgba(100,116,139,.25); }
.esig-status-ok   { background: rgba(16,185,129,.12); color: #10b981; border: 1px solid rgba(16,185,129,.3); border-radius: 8px; padding: 8px 14px; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.esig-status-none { background: rgba(245,158,11,.1); color: #f59e0b; border: 1px solid rgba(245,158,11,.3); border-radius: 8px; padding: 8px 14px; font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
</style>

<script>
/* ═══════════════════════════════════════════════════
   E-SIGNATURE WIDGET  (no dependencies except canvas)
═══════════════════════════════════════════════════ */
(function () {
    /* ── State ── */
    let canvas, ctx, drawing = false, isEmpty = true;
    let penColor = '#000', penSize = 2;
    let lastX = 0, lastY = 0;

    /* ── Open / Close ── */
    window.openEsigModal = function () {
        loadCanvas();
        loadSavedSignature();
        document.getElementById('esigModal').classList.add('open');
    };
    window.closeEsigModal = function () {
        document.getElementById('esigModal').classList.remove('open');
    };

    /* ── Canvas setup ── */
    function loadCanvas() {
        canvas = document.getElementById('esigCanvas');
        ctx    = canvas.getContext('2d');
        ctx.lineJoin = 'round';
        ctx.lineCap  = 'round';

        /* Retina fix */
        const rect = canvas.getBoundingClientRect();
        if (rect.width > 0 && canvas.width !== rect.width * devicePixelRatio) {
            canvas.width  = rect.width  * devicePixelRatio;
            canvas.height = rect.height * devicePixelRatio;
            ctx.scale(devicePixelRatio, devicePixelRatio);
        }
        attachEvents();
    }

    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: src.clientX - r.left, y: src.clientY - r.top };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        const p = getPos(e);
        lastX = p.x; lastY = p.y;
        ctx.beginPath();
        ctx.arc(p.x, p.y, penSize / 2, 0, Math.PI * 2);
        ctx.fillStyle = penColor;
        ctx.fill();
        isEmpty = false;
        document.getElementById('esigHint').style.opacity = '0';
    }

    function moveDraw(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = getPos(e);
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(p.x, p.y);
        ctx.strokeStyle = penColor;
        ctx.lineWidth   = penSize;
        ctx.stroke();
        lastX = p.x; lastY = p.y;
    }

    function endDraw() { drawing = false; }

    function attachEvents() {
        canvas.addEventListener('mousedown',  startDraw);
        canvas.addEventListener('mousemove',  moveDraw);
        canvas.addEventListener('mouseup',    endDraw);
        canvas.addEventListener('mouseleave', endDraw);
        canvas.addEventListener('touchstart', startDraw, { passive: false });
        canvas.addEventListener('touchmove',  moveDraw,  { passive: false });
        canvas.addEventListener('touchend',   endDraw);
    }

    /* ── Controls ── */
    window.clearCanvas = function () {
        if (!ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        isEmpty = true;
        document.getElementById('esigHint').style.opacity = '1';
        document.getElementById('esigPreviewWrap').style.display = 'none';
        document.getElementById('esigUploadName').textContent = '';
    };

    document.getElementById('esigColor').addEventListener('input', e => { penColor = e.target.value; });
    document.getElementById('esigSize').addEventListener('input', e => {
        penSize = +e.target.value;
        document.getElementById('esigSizeVal').textContent = penSize;
    });

    /* ── Upload from file ── */
    window.loadSigFromFile = function (input) {
        const file = input.files[0];
        if (!file) return;
        document.getElementById('esigUploadName').textContent = file.name;
        const reader = new FileReader();
        reader.onload = function (ev) {
            const img = new Image();
            img.onload = function () {
                if (!canvas) loadCanvas();
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                const scale = Math.min(canvas.offsetWidth / img.width, canvas.offsetHeight / img.height);
                const dw = img.width * scale, dh = img.height * scale;
                const dx = (canvas.offsetWidth  - dw) / 2;
                const dy = (canvas.offsetHeight - dh) / 2;
                ctx.drawImage(img, dx, dy, dw, dh);
                isEmpty = false;
                document.getElementById('esigHint').style.opacity = '0';
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    };

    /* ── Preview ── */
    window.previewEsig = function () {
        if (!canvas || isEmpty) { esigToast('danger', 'Please draw your signature first.'); return; }
        const data = canvas.toDataURL('image/png');
        document.getElementById('esigPreviewImg').src = data;
        document.getElementById('esigPreviewWrap').style.display = 'block';
    };

    /* ── Save ── */
    window.saveEsig = function () {
        if (!canvas || isEmpty) { esigToast('danger', 'Please draw your signature before saving.'); return; }
        const data = canvas.toDataURL('image/png');
        const btn  = document.getElementById('esigSaveBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

        fetch('{{ route("esignature.save") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' },
            body: JSON.stringify({ signature_data: data })
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy-fill"></i> Save Signature';
            if (res.success) {
                if (window.showSuccessModal) window.showSuccessModal(res.message || 'E-signature saved successfully.');
                else esigToast('success', '<i class="bi bi-check-circle-fill"></i> ' + res.message);
                loadSavedSignature();
            } else {
                esigToast('danger', '<i class="bi bi-exclamation-circle-fill"></i> ' + res.message);
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-floppy-fill"></i> Save Signature';
            esigToast('danger', 'Network error. Please try again.');
        });
    };

    /* ── Delete ── */
    window.deleteEsig = function () {
        if (!confirm('Delete your saved e-signature? This cannot be undone.')) return;
        fetch('{{ route("esignature.delete") }}', { method: 'DELETE', headers: { 'X-CSRF-TOKEN': window.CSRF_TOKEN, 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                if (window.showSuccessModal) window.showSuccessModal(res.message || 'E-signature deleted successfully.');
                else esigToast('success', '<i class="bi bi-trash3-fill"></i> Signature deleted.');
                document.getElementById('esigSavedPreview').style.display = 'none';
                const chip = document.getElementById('esigStatusChip');
                chip.style.display = 'flex';
                chip.innerHTML = '<span class="esig-status-none"><i class="bi bi-exclamation-triangle-fill"></i> No e-signature saved yet.</span>';
            }
        });
    };

    /* ── Load saved signature from server ── */
    function loadSavedSignature() {
        fetch('{{ route("esignature.get") }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(res => {
            const chip    = document.getElementById('esigStatusChip');
            const preview = document.getElementById('esigSavedPreview');
            chip.style.display = 'flex';
            if (res.has_signature) {
                chip.innerHTML = '<span class="esig-status-ok"><i class="bi bi-check-circle-fill"></i> E-signature is saved and active.</span>';
                document.getElementById('esigSavedImg').src  = res.signature_data;
                document.getElementById('esigSavedDate').textContent = 'Last updated: ' + res.updated_at;
                preview.style.display = 'block';
            } else {
                chip.innerHTML = '<span class="esig-status-none"><i class="bi bi-exclamation-triangle-fill"></i> No e-signature saved yet.</span>';
                preview.style.display = 'none';
            }
        })
        .catch(() => {});
    }

    /* ── Toast helper ── */
    function esigToast(type, html) {
        let t = document.getElementById('toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'toast';
            document.body.appendChild(t);
        }
        t.innerHTML = html;
        t.className = 'show ' + type;
        clearTimeout(t._et);
        t._et = setTimeout(() => t.className = '', 3200);
    }

    /* ── Close on overlay click ── */
    document.getElementById('esigModal').addEventListener('click', function (e) {
        if (e.target === this) closeEsigModal();
    });
})();
</script>

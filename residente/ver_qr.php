<?php
// residente/ver_qr.php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);

$user = current_user();

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    die('Código no proporcionado');
}

/**
 * URL ABSOLUTA al panel del guardia
 * (esto es CLAVE para que WhatsApp / copiar funcionen bien)
 */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];
$urlAcceso = $scheme . '://' . $host . '/guardia/control_accesos.php?code=' . urlencode($code);

function h($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>QR de acceso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- QR local (NO API externa) -->
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center text-slate-50 p-4">

<div class="w-full max-w-md rounded-3xl border border-white/15 bg-white/5 backdrop-blur-2xl shadow-2xl overflow-hidden">

    <!-- HEADER -->
    <div class="p-6 border-b border-white/10">
        <h1 class="text-lg font-semibold">QR de acceso</h1>
        <p class="text-xs text-slate-300 mt-1">
            Muestra este QR al guardia en la entrada.
            El código abre el panel de control con tu acceso cargado.
        </p>
    </div>

    <!-- CONTENIDO -->
    <div class="p-6 space-y-4 text-center">

        <!-- QR -->
        <div class="flex justify-center">
            <div class="bg-white p-4 rounded-3xl shadow-lg">
                <div id="qrcode"></div>
            </div>
        </div>

        <!-- CÓDIGO -->
        <div>
            <div class="text-[11px] text-slate-300 mb-1">Código de acceso</div>
            <div class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-black/40 px-3 py-2">
                <code id="codeEl" class="text-sm font-semibold tracking-wider">
                    <?= h($code) ?>
                </code>
                <button onclick="copyText(document.getElementById('codeEl').textContent)"
                        class="px-3 py-1.5 text-[11px] rounded-2xl bg-white/10 border border-white/15 hover:bg-white/20">
                    Copiar
                </button>
            </div>
        </div>

        <!-- URL -->
        <div>
            <div class="text-[11px] text-slate-300 mb-1">URL de acceso</div>
            <div class="rounded-2xl border border-white/10 bg-black/30 p-3 text-left">
                <div class="flex gap-2">
                    <span id="urlEl" class="text-[11px] break-all text-slate-100">
                        <?= h($urlAcceso) ?>
                    </span>
                    <button onclick="copyText(document.getElementById('urlEl').textContent)"
                            class="shrink-0 px-3 py-1.5 text-[11px] rounded-2xl bg-white/10 border border-white/15 hover:bg-white/20">
                        Copiar
                    </button>
                </div>
            </div>
        </div>

        <!-- ACCIONES -->
        <div class="grid grid-cols-2 gap-2 pt-2">
            <button onclick="shareWhatsApp()"
                    class="rounded-2xl border border-emerald-400/30 bg-emerald-500/15 py-2 text-xs text-emerald-100 hover:bg-emerald-500/25">
                WhatsApp
            </button>

            <button onclick="downloadQR()"
                    class="rounded-2xl border border-white/15 bg-white/10 py-2 text-xs hover:bg-white/20">
                Descargar QR
            </button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast"
     class="hidden fixed bottom-5 right-5 z-50 rounded-2xl border border-white/15 bg-slate-950/90 px-4 py-3 text-[11px] text-slate-100 shadow-2xl">
    Copiado ✅
</div>

<script>
/* ===== QR ===== */
const urlAcceso = <?= json_encode($urlAcceso) ?>;

new QRCode(document.getElementById("qrcode"), {
    text: urlAcceso,
    width: 260,
    height: 260,
    correctLevel: QRCode.CorrectLevel.H
});

/* ===== UTILIDADES ===== */
function toast(msg){
    const t = document.getElementById('toast');
    t.textContent = msg || 'Listo ✅';
    t.classList.remove('hidden');
    setTimeout(()=>t.classList.add('hidden'), 1400);
}

async function copyText(text){
    try{
        await navigator.clipboard.writeText(text);
        toast('Copiado ✅');
    }catch(e){
        toast('No se pudo copiar ❌');
    }
}

function shareWhatsApp(){
    const code = document.getElementById('codeEl').textContent;
    const text = `Acceso al residencial\nCódigo: ${code}\n\n${urlAcceso}`;
    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}

function downloadQR(){
    const canvas = document.querySelector('#qrcode canvas');
    if (!canvas) return toast('QR no disponible ❌');

    const link = document.createElement('a');
    link.download = `qr-acceso-${document.getElementById('codeEl').textContent}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
    toast('Descargado ✅');
}
</script>

</body>
</html>

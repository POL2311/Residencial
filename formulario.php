<?php
// Conexión a la base de datos
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'asher_db';
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
  die("Conexión fallida: " . $conn->connect_error);
}

$mensajeExito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nombre = $_POST['nombre'] ?? '';
  $direccion = $_POST['direccion'] ?? '';
  $telefono_principal = $_POST['telefono_principal'] ?? '';
  $telefono_secundario = $_POST['telefono_secundario'] ?? '';
  $correo = $_POST['correo'] ?? '';
  $consume_sustancias = $_POST['consume_sustancias'] ?? '';

  if ($nombre && $direccion && $telefono_principal && $consume_sustancias) {
    $stmt = $conn->prepare("INSERT INTO aspirantes (nombre, direccion, telefono_principal, telefono_secundario, correo, consume_sustancias) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nombre, $direccion, $telefono_principal, $telefono_secundario, $correo, $consume_sustancias);
    $stmt->execute();
    $stmt->close();
    $mensajeExito = true;
  }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Únete a Seguridad Asher</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#2E5D73] text-white font-sans">
  <!-- Líneas doradas superiores finas con separación -->
  <div class="w-full h-0.5 bg-[#d4af37] mt-4"></div>
  <div class="w-full h-0.5 bg-[#d4af37] mb-6"></div>

  <div class="max-w-2xl mx-auto p-6 shadow-xl mt-6">
    <!-- Logo con separación superior -->
    <div class="flex justify-center mb-4 mt-6">
      <img src="logo.png" alt="Logo Seguridad Asher" class="h-[50px]">
    </div>

    <div class="text-center mb-6">
      <h1 class="text-3xl italic font-semibold">SEGURIDAD ASHER</h1>
      <p class="text-xl mt-2 text-[#d4af37]">Únete a nuestro equipo</p>
      <p class="text-sm">Servicios en Toluca, Metepec, San Mateo y alrededores</p>
    </div>

    <?php if ($mensajeExito): ?>
      <div class="text-center space-y-6">
        <p class="text-lg font-semibold">Gracias por enviar tu solicitud.</p>
        <p class="text-base">Tu información ha sido registrada correctamente y será revisada por nuestro equipo. Si deseas iniciar el proceso por llamada, puedes comunicarte directamente con nosotros.</p>
        <p class="text-lg font-bold">📞 <a href="tel:7228890713" class="text-[#d4af37] underline">722 889 0713</a></p>
        <p class="italic">Cmdte. Simón Malvaes C.</p>
      </div>
    <?php else: ?>

    <p class="text-center mb-6 px-4 text-sm text-white text-justify">
      Tu dirección nos permitirá mostrarte vacantes cercanas en tu zona y facilitar el proceso de ubicación para entrevistas o asignaciones.
    </p>

    <form method="POST" class="space-y-4" id="registroForm">
      <div>
        <label class="block font-medium mb-1">Nombre completo *</label>
        <input type="text" name="nombre" required class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white placeholder-white px-4 py-2 rounded-lg focus:outline-none">
      </div>

      <div>
        <label class="block font-medium mb-1">Dirección completa *</label>
        <textarea name="direccion" required rows="3" class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white placeholder-white px-4 py-2 rounded-lg focus:outline-none"></textarea>
      </div>

      <div>
        <label class="block font-medium mb-1">Teléfono principal *</label>
        <input type="tel" name="telefono_principal" required class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white placeholder-white px-4 py-2 rounded-lg focus:outline-none">
      </div>

      <div>
        <label class="block font-medium mb-1">Teléfono secundario</label>
        <input type="tel" name="telefono_secundario" class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white placeholder-white px-4 py-2 rounded-lg focus:outline-none">
      </div>

      <div>
        <label class="block font-medium mb-1">Correo electrónico (opcional)</label>
        <input type="email" name="correo" class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white placeholder-white px-4 py-2 rounded-lg focus:outline-none">
      </div>

      <div>
        <label class="block font-medium mb-1">¿Consumes sustancias? *</label>
        <select name="consume_sustancias" required class="w-full border border-[#d4af37] bg-white bg-opacity-10 text-white px-4 py-2 rounded-lg focus:outline-none">
          <option value="">Selecciona una opción</option>
          <option value="No">No</option>
          <option value="Sí">Sí</option>
        </select>
        <p class="text-sm text-red-300 mt-1">* Se realizará prueba de antidoping al ingresar.</p>
      </div>

      <div>
        <label class="inline-flex items-center">
          <input type="checkbox" required class="mr-2">
          Acepto la política de privacidad y tratamiento de datos
        </label>
      </div>

      <div class="text-center">
        <button type="submit" class="mt-4 bg-white text-[#2E5D73] font-semibold px-6 py-2 rounded-lg hover:bg-[#d4af37] hover:text-white transition-all">Enviar solicitud</button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- Línea dorada inferior delgada fija -->
  <div class="w-full h-0.5 bg-[#d4af37] mt-10"></div>
</body>
</html>
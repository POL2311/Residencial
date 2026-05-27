# OS Gate Operador - Android APK Debug

Esta guia prepara la primera APK Android de OS Gate Operador con PWA + Capacitor. No reemplaza la web actual: el contenedor carga el dashboard Guardia existente por HTTPS.

## 1. Reemplazar dominio

Antes de compilar, cambia `TU-DOMINIO` por el dominio real HTTPS en:

- `mobile/operador/capacitor.config.json`
- `mobile/operador/www/index.html`

Ejemplo:

```json
"url": "https://os-gate.com/guardia/php/dashboard.php"
```

El dominio debe tener certificado HTTPS valido. Camara y GPS no son confiables en Android WebView si el origen no es seguro.

## 2. Instalar dependencias

Desde la raiz del proyecto:

```bash
cd mobile/operador
npm install
```

## 3. Crear proyecto Android

```bash
npx cap add android
npx cap sync android
npx cap open android
```

Tambien puedes usar los scripts:

```bash
npm run cap:add:android
npm run cap:sync:android
npm run cap:open:android
```

## 4. Permisos Android

Despues de `npx cap add android`, revisa `mobile/operador/android/app/src/main/AndroidManifest.xml` y agrega o confirma estos permisos fuera de la etiqueta `<application>`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

Capacitor suele incluir `INTERNET`, pero conviene verificarlo antes de probar QR, GPS y evidencia.

## 5. Generar APK debug

Opcion Android Studio:

1. Abre el proyecto con `npx cap open android`.
2. Espera la sincronizacion de Gradle.
3. Selecciona un dispositivo fisico.
4. Ejecuta la app con `Run`.
5. Para APK debug usa `Build > Build Bundle(s) / APK(s) > Build APK(s)`.

Opcion terminal:

```bash
cd mobile/operador/android
./gradlew assembleDebug
```

APK esperada:

```text
mobile/operador/android/app/build/outputs/apk/debug/app-debug.apk
```

No uses esta APK debug como release publico. El firmado release se prepara despues.

## 6. Pruebas minimas en dispositivo

1. Instalar APK debug en Android fisico.
2. Iniciar sesion como Guardia RetailOps.
3. Abrir Accesos y escanear QR `op:pr:*`.
4. Escanear pase temporal `op:vr:*`.
5. Abrir Rondines, permitir GPS y escanear `op:round_point:*`.
6. Escanear orden de servicio `op:so:*`.
7. Adjuntar evidencia con camara si el flujo lo solicita.
8. Confirmar en Admin que bitacora y reportes reciben eventos.

## 7. Riesgos conocidos

- Cookies y sesion: Android WebView normalmente conserva cookies por dominio, pero politicas de SameSite, redirecciones o subdominios pueden romper login persistente.
- HTTPS: camara, GPS y cookies seguras requieren HTTPS real; evita `http://` y certificados autofirmados.
- CORS: al cargar `server.url`, las llamadas deben quedarse en el mismo dominio de la app web. Si se separan API y frontend, habra que revisar CORS.
- Permisos: aunque la web use `navigator.geolocation` y camara web, Android necesita permisos declarados y aceptados por el usuario.
- Conectividad: esta primera APK no tiene cola offline; si no hay red, el dashboard remoto no carga.
- Play Store: una app que solo sea wrapper web puede requerir justificar capacidades nativas. OS Gate Operador debe demostrar uso real de QR, GPS y evidencia.

## 8. Pendientes fuera de esta fase

- APK/AAB release firmado.
- Iconos Android definitivos.
- Token API movil si cookies fallan en WebView.
- Cola offline para accesos y rondines.
- Deteccion de ubicacion simulada.
- Push notifications.

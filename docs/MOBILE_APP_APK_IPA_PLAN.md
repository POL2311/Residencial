# OS Gate Operador - Plan APK/IPA

## Arquitectura recomendada

La ruta recomendada para Guardia/Operador es **PWA responsive + Capacitor**.

Esta opción reutiliza el backend PHP, las plantillas HTML y los módulos existentes de Guardia. La app móvil compila como contenedor nativo y apunta a una URL HTTPS del panel `guardia/php/dashboard.php`, manteniendo login, cookies de sesión, CSRF, `allowed_views` y permisos por `service_profile.php`.

## Por qué no React Native o Flutter ahora

- Requerirían reescribir pantallas, navegación y consumo de APIs.
- Duplicarían validaciones que ya existen en PHP.
- Harían más lento el MVP empresarial de RetailOps.
- La prioridad actual es tener flujo real de QR, GPS, evidencia, rondines, bitácora e incidentes.

## Dependencias futuras

```bash
npm install @capacitor/core @capacitor/cli
npm install @capacitor/android @capacitor/ios
```

Opcionales si se decide usar plugins nativos en vez de APIs web:

```bash
npm install @capacitor/camera @capacitor/geolocation
```

## Estructura sugerida

```text
mobile/
  operador/
    capacitor.config.json
    android/
    ios/
```

El `webDir` puede apuntar a una carpeta mínima con un `index.html` que redirija al backend HTTPS, o usarse `server.url` en Capacitor para cargar directamente la URL remota del panel Guardia.

## Configuración Capacitor sugerida

```json
{
  "appId": "com.osgate.operador",
  "appName": "OS Gate Operador",
  "webDir": "www",
  "server": {
    "url": "https://TU-DOMINIO/guardia/php/dashboard.php",
    "cleartext": false
  }
}
```

## Comandos Android

```bash
npx cap init "OS Gate Operador" "com.osgate.operador"
npx cap add android
npx cap sync android
npx cap open android
```

Desde Android Studio:

- Revisar permisos.
- Probar en dispositivo físico.
- Generar APK/AAB firmado para distribución.

## Comandos iOS

```bash
npx cap add ios
npx cap sync ios
npx cap open ios
```

Desde Xcode:

- Configurar Team y Bundle Identifier.
- Revisar permisos en `Info.plist`.
- Probar en iPhone físico.
- Generar archivo para TestFlight/App Store.

## Permisos Android

Agregar/verificar en `AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

## Permisos iOS

Agregar/verificar en `Info.plist`:

```xml
<key>NSCameraUsageDescription</key>
<string>OS Gate usa la cámara para escanear códigos QR operativos.</string>
<key>NSLocationWhenInUseUsageDescription</key>
<string>OS Gate usa tu ubicación para validar rondines y eventos operativos.</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>OS Gate puede adjuntar evidencia fotográfica a eventos operativos.</string>
```

## Backend y sesión

La versión 1 mantiene:

- Login PHP existente.
- Cookies de sesión.
- CSRF para mutaciones.
- HTTPS obligatorio.
- APIs PHP existentes bajo `guardia/php/api/`.

Si iOS/WebView limita cookies o sesión en una instalación específica, la evolución recomendada es crear autenticación por token para app móvil, con refresh token y expiración controlada.

## PWA mínima incluida

Se agregan:

- `guardia/manifest.webmanifest`
- `guardia/sw.js`
- `guardia/js/pwa.js`

El service worker es conservador:

- No cachea APIs.
- No cachea respuestas PHP dinámicas.
- Solo deja base PWA y cacheo oportunista de assets estáticos.

## Riesgos

- Cámara y GPS requieren HTTPS o contexto seguro.
- En iOS, permisos de cámara/GPS deben declararse y probarse en dispositivo real.
- Un contenedor Capacitor con `server.url` depende de conectividad.
- App Store puede rechazar apps que parezcan solo un wrapper web si no hay uso claro de capacidades nativas; OS Gate Operador mitiga esto con QR, GPS y evidencia.
- La operación offline no está cubierta en esta fase.

## Pendientes futuros

- Token API móvil.
- Cola offline para accesos/rondines.
- Sincronización diferida.
- Detección de mock location en Android.
- Firma de eventos con timestamp.
- Push notifications.
- Iconos 192/512 reales para tiendas.

## Pruebas en dispositivo físico

1. Entrar por HTTPS a `guardia/php/dashboard.php`.
2. Instalar PWA desde navegador móvil.
3. Iniciar sesión como Guardia RetailOps.
4. Abrir Accesos y escanear QR.
5. Abrir Rondines e iniciar ruta.
6. Permitir GPS.
7. Escanear punto QR.
8. Adjuntar evidencia con cámara.
9. Finalizar rondín.
10. Validar en Admin que bitácora, reportes e historial recibieron eventos.

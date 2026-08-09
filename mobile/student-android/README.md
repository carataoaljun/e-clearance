# MCC e-Clearance Student Android App

This Android application provides the **student panel only** for the Laravel project in `C:\wamp64\www\e-clearance`.

The app uses the existing responsive Laravel student portal inside a restricted Android WebView. This means student login, password recovery, dashboard, clearance requests, office and subject submissions, remarks, account settings, chat, and clearance documents continue to use the same Laravel controllers, sessions, CSRF protection, authorization, and database records as the website.

## Included Android features

- Student routes only (`/student/...`)
- Persistent Laravel login session through first-party cookies
- File selection for subject and office uploads
- Authenticated document downloads to the Android Downloads folder
- Android back-button navigation
- Loading and connection-error states
- Fixed official HTTPS server address
- First-party redirects stay inside the app; external sites open in the browser
- Invalid TLS certificates are rejected
- Release builds require HTTPS

## Online server

Debug and release builds connect only to the live student portal:

```text
https://mcceclearance.com/student/login
```

Version 1.2 ignores server addresses saved by older installations so launching
the app cannot redirect from a local address into the external browser.

## Open in Android Studio

1. Start Android Studio.
2. Choose **Open**.
3. Select:

   ```text
   C:\wamp64\www\e-clearance\mobile\student-android
   ```

4. Let Gradle sync.
5. Select the `app` run configuration and an Android emulator or connected phone.
6. Click **Run**.

The project uses Java 17, Android Gradle Plugin 9.0.1, Gradle 9.1, compile SDK 36.1, and supports Android 8.0 or newer.

For local Android development, temporarily change `default_server_root` in
`app/src/main/res/values/strings.xml` and rebuild a debug APK. Do not publish a
build containing a local address.

## Build and install the debug APK

From this directory in PowerShell:

```powershell
$env:JAVA_HOME='C:\Program Files\Android\Android Studio\jbr'
$env:ANDROID_HOME='C:\Users\Aljun\AppData\Local\Android\Sdk'
.\gradlew.bat assembleDebug
```

Generated APK:

```text
app\build\outputs\apk\debug\app-debug.apk
```

Install it on a running emulator or USB-connected phone:

```powershell
& "$env:ANDROID_HOME\platform-tools\adb.exe" install -r app\build\outputs\apk\debug\app-debug.apk
```

## Production configuration

Before publishing:

1. Deploy Laravel to a real HTTPS domain.
2. Confirm `https://mcceclearance.com` remains configured in `app/src/main/res/values/strings.xml`.
3. Create a private Android signing key and configure release signing in `app/build.gradle.kts` without committing passwords or the keystore.
4. Build an Android App Bundle with `gradlew.bat bundleRelease`.
5. Test login, uploads, downloads, chat, password recovery, and logout against production.

Do not enable cleartext HTTP or bypass certificate validation in a production build.

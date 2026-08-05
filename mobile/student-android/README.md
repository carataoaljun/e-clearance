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
- Configurable Laravel server address
- External links open outside the app
- Invalid TLS certificates are rejected
- Release builds require HTTPS

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

## Use with WAMP on the Android emulator

The debug app defaults to:

```text
http://10.0.2.2/e-clearance/public
```

`10.0.2.2` is the Android emulator alias for the Windows host computer. Start WAMP and confirm this URL works in the emulator browser:

```text
http://10.0.2.2/e-clearance/public/student/login
```

If your WAMP virtual host uses a different path, tap the **gear icon** in the app and enter the correct root address without `/student/login`.

## Use with a physical Android phone on localhost

`localhost` on a phone means the phone itself, so use the Windows computer's LAN address:

1. Connect the computer and phone to the same Wi-Fi network.
2. Run `ipconfig` and find the computer's IPv4 address, for example `192.168.1.25`.
3. Make sure Apache is reachable through Windows Firewall.
4. Install and run the debug APK.
5. Tap the gear icon and set:

   ```text
   http://192.168.1.25/e-clearance/public
   ```

6. The app will open `/student/login` automatically.

HTTP is accepted only by debug builds for local development. Use a valid HTTPS URL for production.

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
2. Replace `https://your-clearance-domain.example` in `app/src/main/res/values/strings.xml`, or enter the deployed address from the app's gear icon.
3. Create a private Android signing key and configure release signing in `app/build.gradle.kts` without committing passwords or the keystore.
4. Build an Android App Bundle with `gradlew.bat bundleRelease`.
5. Test login, uploads, downloads, chat, password recovery, and logout against production.

Do not enable cleartext HTTP or bypass certificate validation in a production build.

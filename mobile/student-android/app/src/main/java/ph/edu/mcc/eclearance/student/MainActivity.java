package ph.edu.mcc.eclearance.student;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.app.DownloadManager;
import android.content.ActivityNotFoundException;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.provider.Settings;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.window.OnBackInvokedCallback;
import android.window.OnBackInvokedDispatcher;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.RenderProcessGoneDetail;
import android.webkit.SslErrorHandler;
import android.webkit.URLUtil;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebStorage;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Button;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.ImageButton;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import java.util.Locale;

public final class MainActivity extends Activity {
    private static final String PREFERENCES = "mcc_student_app";
    private static final String SERVER_ROOT_KEY = "server_root";
    private static final String SAVED_WEBVIEW_STATE = "student_webview_state";
    private static final int FILE_CHOOSER_REQUEST = 4101;
    private static final int STORAGE_PERMISSION_REQUEST = 4102;

    private WebView webView;
    private ProgressBar pageProgress;
    private LinearLayout errorPanel;
    private TextView serverCaption;
    private ValueCallback<Uri[]> fileChooserCallback;
    private PendingDownload pendingDownload;
    private OnBackInvokedCallback backInvokedCallback;
    private String serverRoot;
    private boolean mainFrameFailed;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(getColor(R.color.mcc_navy));
        getWindow().setNavigationBarColor(getColor(R.color.mcc_surface));
        getWindow().getDecorView().setSystemUiVisibility(View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR);

        serverRoot = preferences().getString(SERVER_ROOT_KEY, getString(R.string.default_server_root));
        setContentView(createScreen());
        configureWebView();
        registerPredictiveBackCallback();

        Bundle webState = savedInstanceState == null ? null : savedInstanceState.getBundle(SAVED_WEBVIEW_STATE);
        if (webState == null || webView.restoreState(webState) == null) {
            loadStudentPortal();
        }
    }

    private View createScreen() {
        LinearLayout screen = new LinearLayout(this);
        screen.setOrientation(LinearLayout.VERTICAL);
        screen.setBackgroundColor(getColor(R.color.mcc_surface));
        screen.setFitsSystemWindows(true);

        LinearLayout toolbar = new LinearLayout(this);
        toolbar.setGravity(Gravity.CENTER_VERTICAL);
        toolbar.setPadding(dp(12), dp(7), dp(6), dp(7));
        toolbar.setBackgroundColor(Color.WHITE);
        screen.addView(toolbar, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            dp(54)
        ));

        ImageView mark = new ImageView(this);
        mark.setImageResource(R.drawable.mcc_eclearance_logo);
        mark.setContentDescription(getString(R.string.app_name));
        toolbar.addView(mark, new LinearLayout.LayoutParams(dp(36), dp(36)));

        LinearLayout titleGroup = new LinearLayout(this);
        titleGroup.setOrientation(LinearLayout.VERTICAL);
        titleGroup.setPadding(dp(9), 0, dp(8), 0);
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        toolbar.addView(titleGroup, titleParams);

        TextView title = new TextView(this);
        title.setText(R.string.app_name);
        title.setTextColor(getColor(R.color.mcc_navy));
        title.setTextSize(15);
        title.setTypeface(title.getTypeface(), android.graphics.Typeface.BOLD);
        titleGroup.addView(title);

        serverCaption = new TextView(this);
        serverCaption.setText(R.string.app_subtitle);
        serverCaption.setTextColor(Color.rgb(102, 124, 147));
        serverCaption.setTextSize(10);
        serverCaption.setSingleLine(true);
        titleGroup.addView(serverCaption);

        ImageButton reload = toolbarButton(R.drawable.ic_refresh, "Reload student portal");
        reload.setOnClickListener(view -> {
            hideError();
            webView.reload();
        });
        toolbar.addView(reload, new LinearLayout.LayoutParams(dp(42), dp(42)));

        ImageButton settingsButton = toolbarButton(R.drawable.ic_settings, getString(R.string.server_settings));
        settingsButton.setOnClickListener(view -> showServerSettings());
        toolbar.addView(settingsButton, new LinearLayout.LayoutParams(dp(42), dp(42)));

        pageProgress = new ProgressBar(this, null, android.R.attr.progressBarStyleHorizontal);
        pageProgress.setMax(100);
        pageProgress.setProgressTintList(android.content.res.ColorStateList.valueOf(getColor(R.color.mcc_blue)));
        screen.addView(pageProgress, new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(3)));

        FrameLayout content = new FrameLayout(this);
        screen.addView(content, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            0,
            1f
        ));

        webView = new WebView(this);
        webView.setBackgroundColor(getColor(R.color.mcc_surface));
        content.addView(webView, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        errorPanel = createErrorPanel();
        errorPanel.setVisibility(View.GONE);
        content.addView(errorPanel, new FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.MATCH_PARENT
        ));

        return screen;
    }

    private ImageButton toolbarButton(int icon, String description) {
        ImageButton button = new ImageButton(this);
        button.setImageResource(icon);
        button.setContentDescription(description);
        button.setBackgroundColor(Color.TRANSPARENT);
        button.setPadding(dp(10), dp(10), dp(10), dp(10));
        return button;
    }

    private LinearLayout createErrorPanel() {
        LinearLayout panel = new LinearLayout(this);
        panel.setOrientation(LinearLayout.VERTICAL);
        panel.setGravity(Gravity.CENTER);
        panel.setPadding(dp(30), dp(30), dp(30), dp(30));
        panel.setBackgroundColor(getColor(R.color.mcc_surface));

        ImageView icon = new ImageView(this);
        icon.setImageResource(R.drawable.mcc_eclearance_logo);
        panel.addView(icon, new LinearLayout.LayoutParams(dp(86), dp(86)));

        TextView heading = new TextView(this);
        heading.setText(R.string.connection_error_title);
        heading.setTextColor(getColor(R.color.mcc_navy));
        heading.setTextSize(19);
        heading.setGravity(Gravity.CENTER);
        heading.setTypeface(heading.getTypeface(), android.graphics.Typeface.BOLD);
        LinearLayout.LayoutParams headingParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        headingParams.topMargin = dp(16);
        panel.addView(heading, headingParams);

        TextView body = new TextView(this);
        body.setText(R.string.connection_error_body);
        body.setTextColor(Color.rgb(100, 120, 140));
        body.setTextSize(13);
        body.setGravity(Gravity.CENTER);
        LinearLayout.LayoutParams bodyParams = new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        );
        bodyParams.topMargin = dp(8);
        panel.addView(body, bodyParams);

        Button retry = new Button(this);
        retry.setText(R.string.retry);
        retry.setTextColor(Color.WHITE);
        retry.setBackgroundTintList(android.content.res.ColorStateList.valueOf(getColor(R.color.mcc_blue)));
        retry.setOnClickListener(view -> loadStudentPortal());
        LinearLayout.LayoutParams retryParams = new LinearLayout.LayoutParams(dp(150), dp(46));
        retryParams.topMargin = dp(18);
        panel.addView(retry, retryParams);

        Button configure = new Button(this);
        configure.setText(R.string.server_settings);
        configure.setTextColor(getColor(R.color.mcc_navy));
        configure.setBackgroundTintList(android.content.res.ColorStateList.valueOf(Color.WHITE));
        configure.setOnClickListener(view -> showServerSettings());
        LinearLayout.LayoutParams configureParams = new LinearLayout.LayoutParams(dp(180), dp(46));
        configureParams.topMargin = dp(5);
        panel.addView(configure, configureParams);

        return panel;
    }

    @SuppressWarnings("SetJavaScriptEnabled")
    private void configureWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(true);
        settings.setJavaScriptCanOpenWindowsAutomatically(false);
        settings.setSupportMultipleWindows(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
        settings.setMediaPlaybackRequiresUserGesture(true);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setUserAgentString(settings.getUserAgentString() + " MCCStudentAndroid/1.0");
        WebView.setWebContentsDebuggingEnabled(isDebuggable());
        settings.setSafeBrowsingEnabled(true);

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        cookies.setAcceptThirdPartyCookies(webView, false);

        webView.setWebViewClient(new StudentWebViewClient());
        webView.setWebChromeClient(new StudentWebChromeClient());
        webView.setDownloadListener(new StudentDownloadListener());
    }

    private void loadStudentPortal() {
        hideError();
        if (!hasNetworkConnection()) {
            showError();
            return;
        }
        webView.loadUrl(serverRoot + "/student/login");
    }

    private void showServerSettings() {
        EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setHint(R.string.server_address_hint);
        input.setText(serverRoot);
        input.setSelectAllOnFocus(true);
        input.setPadding(dp(12), dp(10), dp(12), dp(10));

        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(dp(20), dp(4), dp(20), 0);
        content.addView(input, new LinearLayout.LayoutParams(
            ViewGroup.LayoutParams.MATCH_PARENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        ));

        TextView help = new TextView(this);
        help.setText(isDebuggable()
            ? "Emulator default: http://10.0.2.2/e-clearance/public\nPhysical phone: use your computer's LAN IP."
            : "A release build requires an HTTPS address for the deployed Laravel system.");
        help.setTextColor(Color.rgb(100, 120, 140));
        help.setTextSize(11);
        help.setPadding(0, dp(8), 0, 0);
        content.addView(help);

        AlertDialog dialog = new AlertDialog.Builder(this)
            .setTitle(R.string.server_settings)
            .setView(content)
            .setNegativeButton(android.R.string.cancel, null)
            .setPositiveButton(android.R.string.ok, null)
            .create();

        dialog.setOnShowListener(ignored -> dialog.getButton(AlertDialog.BUTTON_POSITIVE).setOnClickListener(view -> {
            String normalized = normalizeServerRoot(input.getText().toString());
            String error = validateServerRoot(normalized);
            if (error != null) {
                input.setError(error);
                return;
            }

            if (!normalized.equals(serverRoot)) {
                serverRoot = normalized;
                preferences().edit().putString(SERVER_ROOT_KEY, serverRoot).apply();
                CookieManager.getInstance().removeAllCookies(null);
                CookieManager.getInstance().flush();
                WebStorage.getInstance().deleteAllData();
                webView.clearCache(true);
            }
            dialog.dismiss();
            loadStudentPortal();
        }));
        dialog.show();
    }

    private String normalizeServerRoot(String value) {
        String normalized = value.trim();
        while (normalized.endsWith("/")) {
            normalized = normalized.substring(0, normalized.length() - 1);
        }
        if (normalized.endsWith("/student/login")) {
            normalized = normalized.substring(0, normalized.length() - "/student/login".length());
        } else if (normalized.endsWith("/student")) {
            normalized = normalized.substring(0, normalized.length() - "/student".length());
        }
        return normalized;
    }

    private String validateServerRoot(String value) {
        Uri uri = Uri.parse(value);
        String scheme = uri.getScheme();
        if (scheme == null || uri.getHost() == null || uri.getHost().isBlank()) {
            return "Enter a complete server address.";
        }
        if (!"https".equalsIgnoreCase(scheme) && !"http".equalsIgnoreCase(scheme)) {
            return "Use an HTTP or HTTPS address.";
        }
        if (!isDebuggable() && "http".equalsIgnoreCase(scheme)) {
            return "Release builds require HTTPS.";
        }
        if (uri.getQuery() != null || uri.getFragment() != null) {
            return "Remove query parameters and fragments from the server address.";
        }
        return null;
    }

    private boolean isAllowedStudentUrl(Uri destination) {
        if (destination == null || "about".equalsIgnoreCase(destination.getScheme())) {
            return true;
        }
        Uri root = Uri.parse(serverRoot);
        if (!sameOrigin(root, destination)) {
            return false;
        }

        String rootPath = root.getPath() == null ? "" : root.getPath();
        while (rootPath.endsWith("/")) {
            rootPath = rootPath.substring(0, rootPath.length() - 1);
        }
        String studentPath = rootPath + "/student";
        String destinationPath = destination.getPath() == null ? "" : destination.getPath();
        return destinationPath.equals(studentPath) || destinationPath.startsWith(studentPath + "/");
    }

    private boolean sameOrigin(Uri left, Uri right) {
        return equalsIgnoreCase(left.getScheme(), right.getScheme())
            && equalsIgnoreCase(left.getHost(), right.getHost())
            && effectivePort(left) == effectivePort(right);
    }

    private int effectivePort(Uri uri) {
        if (uri.getPort() >= 0) {
            return uri.getPort();
        }
        return "https".equalsIgnoreCase(uri.getScheme()) ? 443 : 80;
    }

    private boolean equalsIgnoreCase(String left, String right) {
        return left != null && right != null && left.equalsIgnoreCase(right);
    }

    private void openExternal(Uri uri) {
        String scheme = uri.getScheme() == null ? "" : uri.getScheme().toLowerCase(Locale.ROOT);
        if (!scheme.equals("http") && !scheme.equals("https") && !scheme.equals("mailto") && !scheme.equals("tel")) {
            Toast.makeText(this, "This link type is not supported.", Toast.LENGTH_SHORT).show();
            return;
        }
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (ActivityNotFoundException exception) {
            Toast.makeText(this, "No application can open this link.", Toast.LENGTH_SHORT).show();
        }
    }

    private boolean hasNetworkConnection() {
        ConnectivityManager manager = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        Network network = manager.getActiveNetwork();
        if (network == null) {
            return false;
        }
        NetworkCapabilities capabilities = manager.getNetworkCapabilities(network);
        return capabilities != null && (capabilities.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_ETHERNET)
            || capabilities.hasTransport(NetworkCapabilities.TRANSPORT_VPN));
    }

    private void showError() {
        mainFrameFailed = true;
        pageProgress.setVisibility(View.GONE);
        errorPanel.setVisibility(View.VISIBLE);
    }

    private void hideError() {
        mainFrameFailed = false;
        errorPanel.setVisibility(View.GONE);
    }

    private boolean isDebuggable() {
        return (getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;
    }

    private SharedPreferences preferences() {
        return getSharedPreferences(PREFERENCES, Context.MODE_PRIVATE);
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    private void registerPredictiveBackCallback() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return;
        }
        backInvokedCallback = this::handleBackNavigation;
        getOnBackInvokedDispatcher().registerOnBackInvokedCallback(
            OnBackInvokedDispatcher.PRIORITY_DEFAULT,
            backInvokedCallback
        );
    }

    private void handleBackNavigation() {
        if (webView.canGoBack()) {
            webView.goBack();
        } else {
            finishAfterTransition();
        }
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        Bundle webState = new Bundle();
        webView.saveState(webState);
        outState.putBundle(SAVED_WEBVIEW_STATE, webState);
        super.onSaveInstanceState(outState);
    }

    @Override
    @SuppressWarnings("deprecation")
    @SuppressLint("GestureBackNavigation")
    public void onBackPressed() {
        handleBackNavigation();
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode != FILE_CHOOSER_REQUEST || fileChooserCallback == null) {
            return;
        }
        Uri[] result = WebChromeClient.FileChooserParams.parseResult(resultCode, data);
        fileChooserCallback.onReceiveValue(result);
        fileChooserCallback = null;
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode != STORAGE_PERMISSION_REQUEST || pendingDownload == null) {
            return;
        }
        PendingDownload download = pendingDownload;
        pendingDownload = null;
        if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
            enqueueDownload(download);
        } else {
            Toast.makeText(this, "Storage permission is required to save this file.", Toast.LENGTH_LONG).show();
        }
    }

    @Override
    protected void onDestroy() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU && backInvokedCallback != null) {
            getOnBackInvokedDispatcher().unregisterOnBackInvokedCallback(backInvokedCallback);
            backInvokedCallback = null;
        }
        if (fileChooserCallback != null) {
            fileChooserCallback.onReceiveValue(null);
            fileChooserCallback = null;
        }
        if (webView != null) {
            webView.stopLoading();
            webView.setWebChromeClient(null);
            webView.setWebViewClient(null);
            webView.destroy();
        }
        super.onDestroy();
    }

    private final class StudentWebViewClient extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
            Uri destination = request.getUrl();
            if (isAllowedStudentUrl(destination)) {
                return false;
            }
            openExternal(destination);
            return true;
        }

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            mainFrameFailed = false;
            errorPanel.setVisibility(View.GONE);
            pageProgress.setVisibility(View.VISIBLE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            CookieManager.getInstance().flush();
            pageProgress.setVisibility(View.GONE);
            if (!mainFrameFailed) {
                errorPanel.setVisibility(View.GONE);
            }
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request.isForMainFrame()) {
                showError();
            }
        }

        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, android.net.http.SslError error) {
            handler.cancel();
            showError();
            Toast.makeText(MainActivity.this, "The server certificate could not be verified.", Toast.LENGTH_LONG).show();
        }

        @Override
        public boolean onRenderProcessGone(WebView view, RenderProcessGoneDetail detail) {
            ((ViewGroup) view.getParent()).removeView(view);
            view.destroy();
            Toast.makeText(MainActivity.this, "The portal was restarted after a rendering problem.", Toast.LENGTH_LONG).show();
            recreate();
            return true;
        }
    }

    private final class StudentWebChromeClient extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int newProgress) {
            pageProgress.setProgress(newProgress);
            pageProgress.setVisibility(newProgress >= 100 ? View.GONE : View.VISIBLE);
        }

        @Override
        public boolean onShowFileChooser(
            WebView view,
            ValueCallback<Uri[]> callback,
            FileChooserParams fileChooserParams
        ) {
            if (fileChooserCallback != null) {
                fileChooserCallback.onReceiveValue(null);
            }
            fileChooserCallback = callback;
            try {
                Intent chooser = fileChooserParams.createIntent();
                chooser.addCategory(Intent.CATEGORY_OPENABLE);
                startActivityForResult(chooser, FILE_CHOOSER_REQUEST);
                return true;
            } catch (ActivityNotFoundException exception) {
                fileChooserCallback = null;
                Toast.makeText(MainActivity.this, "No file picker is available.", Toast.LENGTH_LONG).show();
                return false;
            }
        }

        @Override
        public void onPermissionRequest(android.webkit.PermissionRequest request) {
            request.deny();
        }
    }

    private final class StudentDownloadListener implements DownloadListener {
        @Override
        public void onDownloadStart(
            String url,
            String userAgent,
            String contentDisposition,
            String mimeType,
            long contentLength
        ) {
            Uri uri = Uri.parse(url);
            if (!isAllowedStudentUrl(uri)) {
                openExternal(uri);
                return;
            }

            PendingDownload download = new PendingDownload(
                url,
                userAgent,
                contentDisposition,
                mimeType
            );

            if (Build.VERSION.SDK_INT <= Build.VERSION_CODES.P
                && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
                pendingDownload = download;
                requestPermissions(
                    new String[] {Manifest.permission.WRITE_EXTERNAL_STORAGE},
                    STORAGE_PERMISSION_REQUEST
                );
                return;
            }
            enqueueDownload(download);
        }
    }

    private void enqueueDownload(PendingDownload download) {
        try {
            String fileName = URLUtil.guessFileName(
                download.url,
                download.contentDisposition,
                download.mimeType
            ).replaceAll("[\\\\/:*?\"<>|]", "_");

            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(download.url));
            request.setTitle(fileName);
            request.setDescription("MCC e-Clearance student document");
            request.setMimeType(download.mimeType);
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setAllowedOverMetered(true);
            request.setAllowedOverRoaming(false);
            request.addRequestHeader("User-Agent", download.userAgent);

            String cookies = CookieManager.getInstance().getCookie(download.url);
            if (cookies != null && !cookies.isBlank()) {
                request.addRequestHeader("Cookie", cookies);
            }

            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, fileName);
            DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            manager.enqueue(request);
            Toast.makeText(this, "Downloading " + fileName, Toast.LENGTH_LONG).show();
        } catch (Exception exception) {
            Toast.makeText(this, "The document could not be downloaded.", Toast.LENGTH_LONG).show();
        }
    }

    private static final class PendingDownload {
        private final String url;
        private final String userAgent;
        private final String contentDisposition;
        private final String mimeType;

        private PendingDownload(String url, String userAgent, String contentDisposition, String mimeType) {
            this.url = url;
            this.userAgent = userAgent;
            this.contentDisposition = contentDisposition;
            this.mimeType = mimeType;
        }
    }
}

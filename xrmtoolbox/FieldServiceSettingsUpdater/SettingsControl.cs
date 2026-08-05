using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Threading.Tasks;
using System.Windows.Forms;
using McTools.Xrm.Connection;
using Microsoft.Web.WebView2.Core;
using Microsoft.Web.WebView2.WinForms;
using Microsoft.Xrm.Sdk;
using XrmToolBox.Extensibility;
using XrmToolBox.Extensibility.Interfaces;

namespace FieldServiceSettingsUpdater
{
    /// <summary>
    /// WebView2 host for the shared Field Service Settings Updater HTML. On every connection
    /// change we push the org URL + a fresh OAuth access token into the page as
    /// window.XTB_CONFIG, then (re)load it. The page detects XTB_CONFIG, switches to the
    /// modern light/dark theme, and calls the Web API directly with the bearer token.
    /// </summary>
    public class SettingsControl : PluginControlBase, IGitHubPlugin, IHelpPlugin
    {
        public string RepositoryName => "field-service-settings-updater";
        public string UserName => "TheMarkChristie";
        public string HelpUrl => "https://github.com/TheMarkChristie/field-service-settings-updater#readme";

        private readonly WebView2 _web;
        private bool _webReady;
        private string _orgUrl;
        private string _token;

        public SettingsControl()
        {
            _web = new WebView2 { Dock = DockStyle.Fill };
            Controls.Add(_web);
            Load += async (s, e) => await InitWebViewAsync();
        }

        private async Task InitWebViewAsync()
        {
            // Isolate the WebView2 user-data folder under %LOCALAPPDATA% so it works even when
            // the dll runs from a read-only Plugins folder.
            var udf = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
                "FieldServiceSettingsUpdater", "WebView2");
            Directory.CreateDirectory(udf);

            var env = await CoreWebView2Environment.CreateAsync(null, udf);
            await _web.EnsureCoreWebView2Async(env);
            _webReady = true;

            // Open record deep-links (window.open / target=_blank) in the user's default
            // browser instead of a blank embedded WebView2 window.
            _web.CoreWebView2.NewWindowRequested += (s, e) =>
            {
                e.Handled = true;
                try { Process.Start(new ProcessStartInfo(e.Uri) { UseShellExecute = true }); }
                catch (Exception ex) { LogError("Failed to open external link: " + ex.Message); }
            };

            await PushConfigAndNavigateAsync();
        }

        /// <summary>Resolve org URL + token from the connection and (re)load the app.</summary>
        private async Task PushConfigAndNavigateAsync()
        {
            if (!_webReady) return;

            // Inject config BEFORE any document script runs.
            var configJs = string.IsNullOrEmpty(_orgUrl)
                ? "window.XTB_CONFIG = undefined;"
                : $"window.XTB_CONFIG = {{ baseUrl: {JsString(_orgUrl)}, token: {JsString(_token)} }};";

            await _web.CoreWebView2.AddScriptToExecuteOnDocumentCreatedAsync(configJs);

            var html = Path.Combine(
                Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location) ?? ".",
                "app", "field-service-settings-updater.html");

            if (File.Exists(html))
                _web.CoreWebView2.Navigate(new Uri(html).AbsoluteUri);
            else
                _web.CoreWebView2.NavigateToString(
                    "<h3 style='font-family:Segoe UI'>app/field-service-settings-updater.html not found next to the plugin dll.</h3>" +
                    "<p>Run <code>node build.js</code> so the HTML is copied into the output 'app' folder (see the .csproj copy step).</p>");
        }

        private static string JsString(string s) =>
            s == null ? "null" : "\"" + s.Replace("\\", "\\\\").Replace("\"", "\\\"") + "\"";

        /// <summary>Called by XrmToolBox whenever the active org connection changes.</summary>
        public override void UpdateConnection(
            IOrganizationService newService, ConnectionDetail detail,
            string actionName = "", object parameter = null)
        {
            base.UpdateConnection(newService, detail, actionName, parameter);

            try
            {
                _orgUrl = detail?.WebApplicationUrl?.TrimEnd('/')
                          ?? detail?.OrganizationServiceUrl?.Replace("/XRMServices/2011/Organization.svc", "").TrimEnd('/');

                // ServiceClient exposes the OAuth bearer token used by the page's fetch calls.
                var svc = detail?.ServiceClient;
                _token = svc?.CurrentAccessToken;

                if (string.IsNullOrEmpty(_token))
                    LogWarning("No OAuth access token available on this connection — " +
                               "use an OAuth/MFA connection so the page can call the Web API.");
            }
            catch (Exception ex)
            {
                LogError("Failed to resolve connection token: " + ex.Message);
            }

            _ = PushConfigAndNavigateAsync();   // fire-and-forget reload with the new config
        }
    }
}

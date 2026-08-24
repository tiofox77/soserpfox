// ==========================================================================
//  soserp - agente da bandeja do sistema (system tray)
// ==========================================================================
//
//  Icone junto ao relogio que vigia, de forma continua:
//    - servicos Apache + MySQL a correr
//    - a aplicacao a responder (health-check local)
//    - ligacao a internet (e ha quanto tempo esta offline)
//    - integridade dos ficheiros PHP (flag do servico de integridade)
//    - integridade da licenca e da DATA (relogio recuado)
//  Notifica por balao quando algo muda para pior e deixa o estado a vista no
//  tooltip e no menu. Compilado com csc.exe (existe em qualquer Windows).
//
//  Uso: soserp-tray.exe --dir "C:\soserp" --port 8080

using System;
using System.Diagnostics;
using System.Drawing;
using System.IO;
using System.Net;
using System.ServiceProcess;
using System.Windows.Forms;

public class SoserpTray : Form
{
    static string InstallDir = @"C:\soserp";
    static int Port = 8080;

    NotifyIcon icone;
    Timer timer;
    string ultimoEstado = "";
    ToolStripMenuItem itemEstado;
    Icon iconeMarca;

    // Icone da marca (soserp.ico ao lado do executavel); se faltar, cai no do
    // sistema para nunca ficar sem icone na bandeja.
    Icon IconeDaMarca()
    {
        if (iconeMarca != null) return iconeMarca;
        try
        {
            string f = Path.Combine(InstallDir, "soserp.ico");
            if (!File.Exists(f))
                f = Path.Combine(Path.GetDirectoryName(Application.ExecutablePath), "soserp.ico");
            if (File.Exists(f)) { iconeMarca = new Icon(f); return iconeMarca; }
        }
        catch { }
        iconeMarca = SystemIcons.Application;
        return iconeMarca;
    }

    [STAThread]
    public static void Main(string[] args)
    {
        for (int i = 0; i < args.Length - 1; i++)
        {
            if (args[i] == "--dir") InstallDir = args[i + 1];
            if (args[i] == "--port") Port = int.Parse(args[i + 1]);
        }
        Application.EnableVisualStyles();
        Application.Run(new SoserpTray());
    }

    public SoserpTray()
    {
        // Janela invisivel: isto vive so na bandeja.
        this.WindowState = FormWindowState.Minimized;
        this.ShowInTaskbar = false;
        this.Visible = false;

        var menu = new ContextMenuStrip();
        itemEstado = new ToolStripMenuItem("A verificar...") { Enabled = false };
        menu.Items.Add(itemEstado);
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Abrir soserp", null, (s, e) => Abrir());
        menu.Items.Add("Verificar agora", null, (s, e) => Verificar());
        menu.Items.Add("Reiniciar servicos", null, (s, e) => ReiniciarServicos());
        menu.Items.Add(new ToolStripSeparator());
        menu.Items.Add("Ocultar icone", null, (s, e) => { icone.Visible = false; Application.Exit(); });

        icone = new NotifyIcon();
        icone.Icon = IconeDaMarca();
        icone.Text = "soserp";
        icone.Visible = true;
        icone.ContextMenuStrip = menu;
        icone.DoubleClick += (s, e) => Abrir();

        timer = new Timer();
        timer.Interval = 60000; // 1 minuto
        timer.Tick += (s, e) => Verificar();
        timer.Start();

        Verificar();
    }

    void Abrir()
    {
        try { Process.Start("http://localhost:" + Port); } catch { }
    }

    void ReiniciarServicos()
    {
        foreach (string nome in new string[] { "soserp-mysql", "soserp-apache" })
        {
            try
            {
                var sc = new ServiceController(nome);
                if (sc.Status != ServiceControllerStatus.Running)
                {
                    sc.Start();
                    sc.WaitForStatus(ServiceControllerStatus.Running, TimeSpan.FromSeconds(30));
                }
            }
            catch { }
        }
        Verificar();
    }

    bool ServicoACorrer(string nome)
    {
        try { return new ServiceController(nome).Status == ServiceControllerStatus.Running; }
        catch { return false; }
    }

    bool AppResponde()
    {
        try
        {
            var req = (HttpWebRequest)WebRequest.Create("http://127.0.0.1:" + Port + "/up");
            req.Timeout = 6000;
            using (var r = (HttpWebResponse)req.GetResponse())
                return (int)r.StatusCode == 200;
        }
        catch { return false; }
    }

    bool TemInternet()
    {
        try
        {
            var req = (HttpWebRequest)WebRequest.Create("https://soserp.vip/up");
            req.Timeout = 8000;
            req.Method = "HEAD";
            using (var r = (HttpWebResponse)req.GetResponse())
                return true;
        }
        catch { return false; }
    }

    // O servico de integridade levanta esta flag quando um ficheiro PHP muda.
    bool IntegridadeFalhou()
    {
        return File.Exists(Path.Combine(InstallDir, @"app\storage\app\integridade-falha.flag"));
    }

    // O vigia grava aqui o resultado de `licenca:ver --json`.
    string EstadoLicenca(out string motivo, out int diasOffline)
    {
        motivo = ""; diasOffline = -1;
        try
        {
            string f = Path.Combine(InstallDir, @"app\storage\app\estado-licenca.json");
            if (!File.Exists(f)) return "desconhecido";
            string j = File.ReadAllText(f);
            motivo = Entre(j, "\"motivo\":", ",");
            string est = Entre(j, "\"estado\":", ",").Trim().Trim('"');
            string dias = Entre(j, "\"dias_offline\":", ",").Trim();
            int d; if (int.TryParse(dias, out d)) diasOffline = d;
            return string.IsNullOrEmpty(est) ? "desconhecido" : est;
        }
        catch { return "desconhecido"; }
    }

    static string Entre(string texto, string chave, string fim)
    {
        int i = texto.IndexOf(chave);
        if (i < 0) return "";
        i += chave.Length;
        int j = texto.IndexOf(fim, i);
        if (j < 0) j = texto.Length;
        return texto.Substring(i, j - i).Trim().Trim('"');
    }

    void Verificar()
    {
        bool mysql = ServicoACorrer("soserp-mysql");
        bool apache = ServicoACorrer("soserp-apache");
        bool app = AppResponde();
        bool net = TemInternet();
        bool integridade = !IntegridadeFalhou();
        string motivo; int diasOffline;
        string lic = EstadoLicenca(out motivo, out diasOffline);

        bool bloqueado = (lic == "bloqueada" || lic == "invalida" || !integridade);
        bool aviso = (lic == "aviso" || lic == "banner" || lic == "so_leitura" || !net || !app || !mysql || !apache);

        string titulo;
        ToolTipIcon tipoBalao;
        if (bloqueado) { titulo = "soserp BLOQUEADO"; tipoBalao = ToolTipIcon.Error; }
        else if (aviso) { titulo = "soserp - atencao"; tipoBalao = ToolTipIcon.Warning; }
        else { titulo = "soserp a funcionar"; tipoBalao = ToolTipIcon.Info; }

        string linhas =
            (apache && mysql ? "Servicos: OK" : "Servicos: " + (apache ? "" : "Apache parado ") + (mysql ? "" : "MySQL parado")) + "\n" +
            (app ? "Aplicacao: responde" : "Aplicacao: NAO responde") + "\n" +
            (net ? "Internet: ligada" : "Internet: SEM LIGACAO" + (diasOffline >= 0 ? " (" + diasOffline + " dias offline)" : "")) + "\n" +
            (integridade ? "Ficheiros: integros" : "Ficheiros: ADULTERADOS") + "\n" +
            "Licenca: " + lic.ToUpper() + (string.IsNullOrEmpty(motivo) ? "" : " - " + motivo);

        icone.Icon = IconeDaMarca();   // marca sempre; a severidade vai no balao e no tooltip
        string tip = titulo + "\n" + linhas;
        icone.Text = tip.Length > 62 ? tip.Substring(0, 62) : tip;  // limite do Windows
        itemEstado.Text = titulo;

        // So notifica quando o estado MUDA - senao virava spam de 1 em 1 minuto.
        string agora = bloqueado + "|" + aviso + "|" + lic + "|" + net + "|" + integridade + "|" + app;
        if (agora != ultimoEstado)
        {
            ultimoEstado = agora;
            if (bloqueado || aviso)
                icone.ShowBalloonTip(10000, titulo, linhas, tipoBalao);
        }
    }
}

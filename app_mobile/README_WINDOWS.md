# SOS ERP — Faturação :: Instalar e correr no Windows (sem emulador)

Há **dois caminhos**. Escolhe um.

---

## Opção A — App nativa Windows (.exe)   ✅ recomendado

Corre como aplicação de ambiente de trabalho real (janela própria, BD offline em ficheiro).

### Pré-requisito (uma vez)
Visual Studio com **"Desktop development with C++"** (ou Build Tools).
O script trata disso, ou instala manualmente:
```powershell
winget install --id Microsoft.VisualStudio.2022.BuildTools -e `
  --override "--quiet --wait --add Microsoft.VisualStudio.Workload.VCTools --includeRecommended"
```

### Correr (modo desenvolvimento)
```powershell
cd app_mobile
powershell -ExecutionPolicy Bypass -File run_windows.ps1
```
O script ativa o desktop, verifica/instala o C++ e arranca a app.

### Gerar o executável distribuível
```powershell
powershell -ExecutionPolicy Bypass -File build_windows.ps1
```
Produz `build\windows\x64\runner\Release\soserp_faturacao.exe` **+** `soserp_faturacao_windows.zip`.
Para instalar noutro PC: copia a pasta **Release** inteira e executa o `.exe` (não precisa de Flutter no destino).

> A BD offline usa SQLite via FFI (`sqflite_common_ffi`) — incluído automaticamente no desktop.

---

## Opção B — PWA (sem instalar nada)   ⚡ mais rápido

Não precisa de Visual Studio nem de emulador. Corre no Edge/Chrome e pode ser **instalada como app de Windows**.

```powershell
cd app_mobile
powershell -ExecutionPolicy Bypass -File install_pwa.ps1
```
Depois, no Edge: ícone **"Instalar"** na barra de endereço (ou menu ⋯ → *Aplicações → Instalar este site como aplicação*).
Fica no menu Iniciar com janela própria e funciona offline.

> Limitação da PWA: a BD offline do POS (sqflite) não corre em browser — o login e a navegação funcionam; para POS offline completo usa a Opção A (nativa) ou a app Android.

---

## Notas
- **Não é preciso emulador Android** em nenhuma das opções.
- Backend: a app aponta para `https://soserp.vip` (ver `lib/core/config.dart`). Para um backend local, muda `AppConfig.baseUrl`.
- Login usa token (rota `POST /api/v1/auth/login`). Há também **"Ver demonstração (offline)"** para experimentar sem backend.

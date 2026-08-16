<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * O template do aviso que o dono da plataforma recebe quando nasce uma empresa.
 *
 * Fica na base de dados, ao lado dos outros, para poder ser reescrito no painel
 * de emails sem tocar em código. O serviço que o usa tem texto próprio para o
 * caso de esta linha faltar — um aviso interno não pode depender de uma linha
 * que alguém pode apagar — mas o normal é ser esta a mandar.
 *
 * Re-executável: actualiza a linha se ela já existir, e não duplica.
 *
 *     php artisan db:seed --class=AvisoNovaEmpresaTemplateSeeder
 */
class AvisoNovaEmpresaTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::updateOrCreate(
            ['slug' => 'nova-empresa-admin'],
            [
                'name'        => 'Nova empresa (aviso ao administrador)',
                'subject'     => 'Nova empresa: {empresa_nome}',
                'description' => 'Enviado a quem administra a plataforma sempre que uma empresa se regista.',
                'is_active'   => true,
                'variables'   => [
                    'empresa_nome', 'empresa_nif', 'empresa_email', 'empresa_telefone',
                    'empresa_regime', 'registada_em', 'app_name', 'url_empresas',
                ],
                'body_text'   => "Nova empresa registada no {app_name}.\n\n"
                    . "Nome: {empresa_nome}\nNIF: {empresa_nif}\nEmail: {empresa_email}\n"
                    . "Telefone: {empresa_telefone}\nRegime: {empresa_regime}\nRegistada: {registada_em}\n\n"
                    . "Ver empresas: {url_empresas}",
                'body_html'   => $this->corpo(),
            ]
        );
    }

    private function corpo(): string
    {
        return <<<'HTML'
<div style="font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;max-width:540px;margin:0 auto;">
  <div style="background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:16px 16px 0 0;padding:22px 26px;">
    <h1 style="margin:0;color:#fff;font-size:20px;">Nova empresa registada</h1>
    <p style="margin:4px 0 0;color:#ddd6fe;font-size:13px;">{app_name}</p>
  </div>

  <div style="border:1px solid #e2e8f0;border-top:0;border-radius:0 0 16px 16px;padding:24px 26px;">
    <table style="border-collapse:collapse;font-size:14px;width:100%;">
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">Nome</td>
          <td style="padding:7px 0;color:#0f172a;font-weight:600;">{empresa_nome}</td></tr>
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">NIF</td>
          <td style="padding:7px 0;color:#0f172a;font-weight:600;">{empresa_nif}</td></tr>
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">Email</td>
          <td style="padding:7px 0;color:#0f172a;">{empresa_email}</td></tr>
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">Telefone</td>
          <td style="padding:7px 0;color:#0f172a;">{empresa_telefone}</td></tr>
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">Regime</td>
          <td style="padding:7px 0;color:#0f172a;">{empresa_regime}</td></tr>
      <tr><td style="padding:7px 16px 7px 0;color:#64748b;">Registada</td>
          <td style="padding:7px 0;color:#0f172a;">{registada_em}</td></tr>
    </table>

    <p style="margin:24px 0 0;">
      <a href="{url_empresas}" style="background:#4f46e5;color:#fff;padding:11px 20px;border-radius:10px;
         text-decoration:none;font-weight:600;font-size:14px;display:inline-block;">Ver empresas</a>
    </p>
  </div>
</div>
HTML;
    }
}

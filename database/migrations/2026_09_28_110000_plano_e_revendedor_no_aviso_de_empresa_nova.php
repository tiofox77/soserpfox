<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O AVISO DE EMPRESA NOVA passa a dizer o plano escolhido e o revendedor
 * (28/09/2026).
 *
 * O modelo `nova-empresa-admin` vive na base e pode ter sido reescrito no
 * painel de emails — por isso não se volta a gravar o do seeder por cima.
 * Acrescentam-se só as duas linhas (Plano e Revendedor) antes da «Registada»,
 * ou no fim da tabela se a linha tiver sido mexida. Se já lá estiverem, nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_templates')) {
            return;
        }

        $modelo = DB::table('email_templates')->where('slug', 'nova-empresa-admin')->first();

        // Sem modelo, o serviço usa o texto do código, que já tem as duas linhas.
        if (! $modelo) {
            return;
        }

        $mudancas = [];

        $html = (string) $modelo->body_html;
        if (! str_contains($html, '{plano}')) {
            $linhas = '<tr><td style="padding:7px 16px 7px 0;color:#64748b;">Plano</td>'
                . "\n          " . '<td style="padding:7px 0;color:#0f172a;font-weight:600;">{plano}</td></tr>'
                . "\n      " . '<tr><td style="padding:7px 16px 7px 0;color:#64748b;">Revendedor</td>'
                . "\n          " . '<td style="padding:7px 0;color:#0f172a;">{revendedor}</td></tr>'
                . "\n      ";
            $ancora = '<tr><td style="padding:7px 16px 7px 0;color:#64748b;">Registada</td>';

            if (str_contains($html, $ancora)) {
                $mudancas['body_html'] = str_replace($ancora, $linhas . $ancora, $html);
            } elseif (str_contains($html, '</table>')) {
                $mudancas['body_html'] = preg_replace('~</table>~', $linhas . '</table>', $html, 1);
            }
        }

        $texto = (string) $modelo->body_text;
        if ($texto !== '' && ! str_contains($texto, '{plano}')) {
            $mudancas['body_text'] = str_contains($texto, 'Registada: {registada_em}')
                ? str_replace('Registada: {registada_em}', "Plano: {plano}\nRevendedor: {revendedor}\nRegistada: {registada_em}", $texto)
                : rtrim($texto) . "\n\nPlano: {plano}\nRevendedor: {revendedor}";
        }

        $variaveis = json_decode((string) $modelo->variables, true);
        if (is_array($variaveis) && ! in_array('plano', $variaveis, true)) {
            $mudancas['variables'] = json_encode(array_values(array_unique([...$variaveis, 'plano', 'revendedor'])));
        }

        if ($mudancas) {
            DB::table('email_templates')->where('id', $modelo->id)->update($mudancas + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Duas linhas a mais num email não partem nada; não se tiram.
    }
};

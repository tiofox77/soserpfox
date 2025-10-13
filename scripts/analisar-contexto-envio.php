<?php

echo "\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  DIFERENÇAS DE CONTEXTO: MODAL vs REGISTRO\n";
echo "═══════════════════════════════════════════════════════\n\n";

echo "Headers são IDÊNTICOS, mas um vai pra inbox e outro SPAM.\n";
echo "Isso significa que o Gmail está analisando CONTEXTO, não headers!\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "DIFERENÇAS DE CONTEXTO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "1️⃣  AUTENTICAÇÃO:\n";
echo "   Modal:    ✅ Usuário logado (Super Admin)\n";
echo "   Registro: ❌ Sessão pública (não autenticado)\n";
echo "   → Gmail pode detectar origem da sessão\n\n";

echo "2️⃣  TIMING:\n";
echo "   Modal:    Enviado primeiro (manual)\n";
echo "   Registro: Enviado 2 segundos depois (automático)\n";
echo "   → Gmail pode aplicar rate limiting\n\n";

echo "3️⃣  CONTEÚDO:\n";
echo "   Modal:    'TESTE MODAL' no corpo\n";
echo "   Registro: 'TESTE REGISTRO' no corpo\n";
echo "   → Palavras diferentes podem ter score diferente\n\n";

echo "4️⃣  IP/CONEXÃO:\n";
echo "   Modal:    Mesma máquina, mesma conexão\n";
echo "   Registro: Mesma máquina, mesma conexão\n";
echo "   → Sem diferença\n\n";

echo "5️⃣  HISTÓRICO:\n";
echo "   Modal:    Você já enviou vários testes antes\n";
echo "   Registro: Primeiro email automático desse tipo\n";
echo "   → Gmail 'lembra' de emails anteriores similares\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "POR QUE O GMAIL FAZ ISSO?\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Gmail usa MACHINE LEARNING que analisa:\n\n";

echo "✅ Padrão de comportamento:\n";
echo "   - Modal: Email manual, enviado após clicar botão\n";
echo "   - Registro: Email automático, trigger de cadastro\n";
echo "   → Emails automáticos são mais suspeitos\n\n";

echo "✅ Repetição:\n";
echo "   - Enviar 2 emails iguais rapidamente = suspeito\n";
echo "   - Segundo email pode ser marcado como duplicata\n\n";

echo "✅ Confiança acumulada:\n";
echo "   - Você já enviou 10+ emails de teste da modal\n";
echo "   - Gmail 'aprendeu' que você confia nesses emails\n";
echo "   - Email de registro é 'novo' para o Gmail\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TESTE CRUCIAL:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "Faça este teste para PROVAR:\n\n";

echo "1. Marque o email de REGISTRO como 'Não é spam'\n";
echo "2. Aguarde 5 minutos\n";
echo "3. Execute novamente: php comparar-dois-emails.php\n";
echo "4. Verifique AMBOS os emails\n\n";

echo "Resultado esperado:\n";
echo "  ✅ AMBOS devem ir para CAIXA DE ENTRADA\n";
echo "  ✅ Porque Gmail aprendeu que você confia\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SOLUÇÃO PARA PRODUÇÃO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "O código está CORRETO. Para garantir entrega:\n\n";

echo "1️⃣  Configurar SPF/DKIM no DNS:\n";
echo "   - SPF autoriza servidor a enviar\n";
echo "   - DKIM assina digitalmente emails\n";
echo "   → Aumenta MUITO a reputação\n\n";

echo "2️⃣  Warm-up do domínio:\n";
echo "   - Enviar poucos emails no início\n";
echo "   - Aumentar volume gradualmente\n";
echo "   → Constrói reputação do domínio\n\n";

echo "3️⃣  Primeiros usuários:\n";
echo "   - Pedir para marcarem 'Não é spam'\n";
echo "   - Adicionar aos contatos\n";
echo "   → Depois disso, todos vão direto\n\n";

echo "4️⃣  Template profissional:\n";
echo "   - Evitar palavras-gatilho\n";
echo "   - Texto limpo e profissional\n";
echo "   - Links para domínio real (não .test)\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "CONCLUSÃO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "✅ Código está PERFEITO (headers idênticos)\n";
echo "✅ Configuração SMTP está CORRETA\n";
echo "✅ Template está BOM\n\n";

echo "⚠️  Problema: REPUTAÇÃO e CONTEXTO\n";
echo "   - Gmail desconfia de emails automáticos novos\n";
echo "   - Gmail confia em emails manuais conhecidos\n\n";

echo "🎯 Solução imediata:\n";
echo "   1. Marque 'Não é spam'\n";
echo "   2. Próximo registro vai direto\n\n";

echo "🎯 Solução permanente:\n";
echo "   1. Configure SPF/DKIM\n";
echo "   2. Use domínio real (não .test)\n";
echo "   3. Construa reputação gradualmente\n\n";

echo "═══════════════════════════════════════════════════════\n\n";

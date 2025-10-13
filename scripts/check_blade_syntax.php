<?php

$file = 'resources/views/landing/home.blade.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

echo "🔍 VERIFICANDO SINTAXE BLADE\n";
echo str_repeat('=', 50) . "\n\n";

// Stack para rastrear blocos abertos
$stack = [];
$errors = [];

foreach ($lines as $lineNum => $line) {
    $lineNumber = $lineNum + 1;
    
    // Detectar abertura de blocos
    if (preg_match('/@if\s*\(/', $line)) {
        $stack[] = ['type' => 'if', 'line' => $lineNumber, 'content' => trim($line)];
    }
    if (preg_match('/@foreach\s*\(/', $line)) {
        $stack[] = ['type' => 'foreach', 'line' => $lineNumber, 'content' => trim($line)];
    }
    if (preg_match('/@php\b/', $line)) {
        $stack[] = ['type' => 'php', 'line' => $lineNumber, 'content' => trim($line)];
    }
    
    // Detectar fechamento de blocos
    if (preg_match('/@endif\b/', $line)) {
        $found = false;
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['type'] === 'if') {
                array_splice($stack, $i, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $errors[] = "Linha $lineNumber: @endif sem @if correspondente";
        }
    }
    
    if (preg_match('/@endforeach\b/', $line)) {
        $found = false;
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['type'] === 'foreach') {
                array_splice($stack, $i, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $errors[] = "Linha $lineNumber: @endforeach sem @foreach correspondente";
        }
    }
    
    if (preg_match('/@endphp\b/', $line)) {
        $found = false;
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['type'] === 'php') {
                array_splice($stack, $i, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $errors[] = "Linha $lineNumber: @endphp sem @php correspondente";
        }
    }
}

// Verificar blocos não fechados
if (!empty($stack)) {
    echo "❌ BLOCOS NÃO FECHADOS:\n";
    foreach ($stack as $item) {
        echo "  Linha {$item['line']}: @{$item['type']} não foi fechado\n";
        echo "    {$item['content']}\n\n";
    }
} else {
    echo "✅ Todos os blocos estão balanceados!\n\n";
}

// Mostrar erros
if (!empty($errors)) {
    echo "❌ ERROS ENCONTRADOS:\n";
    foreach ($errors as $error) {
        echo "  $error\n";
    }
} else {
    echo "✅ Nenhum erro de fechamento encontrado!\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
echo "Total de linhas: " . count($lines) . "\n";

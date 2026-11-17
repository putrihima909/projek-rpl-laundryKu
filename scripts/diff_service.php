<?php
$orig = file('backup_ai_fix/app/Services/Ai/AiAssistantService.php');
$curr = file('app/Services/Ai/AiAssistantService.php');

$diff = [];
// Print lines that differ
echo "Orig count: " . count($orig) . ", Curr count: " . count($curr) . "\n";
foreach ($curr as $i => $l) {
    if (!isset($orig[$i]) || $orig[$i] !== $l) {
        echo "Line " . ($i + 1) . ":\n";
        echo "  - " . ($orig[$i] ?? "EOF\n");
        echo "  + " . $l;
    }
}


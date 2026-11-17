<?php
require_once __DIR__ . '/../app/Helpers/Env.php';

// Generate unified diff using php
function getUnifiedDiff($oldText, $newText, $oldName, $newName) {
    $oldLines = explode("\n", str_replace("\r\n", "\n", $oldText));
    $newLines = explode("\n", str_replace("\r\n", "\n", $newText));
    
    $diff = [];
    $diff[] = "--- a/" . $oldName;
    $diff[] = "+++ b/" . $newName;
    
    // Find matching blocks
    $i = 0; $j = 0;
    $lenOld = count($oldLines);
    $lenNew = count($newLines);
    
    while ($i < $lenOld || $j < $lenNew) {
        if ($i < $lenOld && $j < $lenNew && $oldLines[$i] === $newLines[$j]) {
            $i++;
            $j++;
            continue;
        }
        
        $startI = max(0, $i - 3);
        $startJ = max(0, $j - 3);
        
        $hunkOld = [];
        $hunkNew = [];
        
        while (($i < $lenOld || $j < $lenNew)) {
            if ($i < $lenOld && $j < $lenNew && $oldLines[$i] === $newLines[$j]) {
                // Check if match continues for at least 3 lines
                $match = true;
                for ($k = 0; $k < 3; $k++) {
                    if (!isset($oldLines[$i+$k]) || !isset($newLines[$j+$k]) || $oldLines[$i+$k] !== $newLines[$j+$k]) {
                        $match = false;
                        break;
                    }
                }
                if ($match) break;
            }
            if ($i < $lenOld && $j < $lenNew && $oldLines[$i] !== $newLines[$j]) {
                $hunkOld[] = $oldLines[$i++];
                $hunkNew[] = $newLines[$j++];
            } elseif ($i < $lenOld) {
                $hunkOld[] = $oldLines[$i++];
            } elseif ($j < $lenNew) {
                $hunkNew[] = $newLines[$j++];
            }
        }
        
        $diff[] = "@@ -" . ($startI + 1) . "," . count($hunkOld) . " +" . ($startJ + 1) . "," . count($hunkNew) . " @@";
        foreach ($hunkOld as $line) {
            $diff[] = "-" . $line;
        }
        foreach ($hunkNew as $line) {
            $diff[] = "+" . $line;
        }
    }
    
    return implode("\n", $diff);
}

<?php
/**
 * Conservative dynamic property fixer:
 * - Scans files for $this->prop = ... assignments
 * - For each class in file, if property not declared, inserts a protected property docblock stub.
 * NOTE: Heuristic — review each change manually.
 */

if ($argc < 2) {
    echo "Usage: php dynamic_property_fixer.php <path>\n";
    exit(1);
}

$path = $argv[1];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($path)
);

$filesChanged = 0;
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    if ($file->getExtension() !== 'php') continue;
    $filePath = $file->getPathname();
    $content = file_get_contents($filePath);
    if ($content === false) continue;

    preg_match_all('/\\$this->([A-Za-z_][A-Za-z0-9_]*)\\s*=/m', $content, $matches);
    if (empty($matches[1])) continue;
    $props = array_unique($matches[1]);

    $newContent = $content;
    $classPattern = '/(class\\s+[A-Za-z_][A-Za-z0-9_]*(?:\\s+extends\\s+[A-Za-z_][A-Za-z0-9_\\\\]*)?(?:\\s+implements\\s+[A-Za-z0-9_\\\\\\s,]*)?)\\s*\\{/'m;
    if (!preg_match_all($classPattern, $content, $classMatches, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    $offsetDelta = 0;
    foreach ($classMatches[0] as $cm) {
        $classStartPos = $cm[1] + $offsetDelta;
        $openBracePos = strpos($newContent, '{', $classStartPos);
        if ($openBracePos === false) continue;

        $insertPos = $openBracePos + 1;
        $classBodySnippet = substr($newContent, $openBracePos, 2000);
        $declaredProps = [];
        if (preg_match_all('/(?:public|protected|private)\\s+\\$([A-Za-z_][A-Za-z0-9_]*)/m', $classBodySnippet, $declaredMatches)) {
            $declaredProps = $declaredMatches[1];
        }

        $toInsert = "";
        foreach ($props as $p) {
            if (!in_array($p, $declaredProps, true)) {
                $toInsert .= PHP_EOL . "    /** @var mixed */" . PHP_EOL . "    protected \$$p;" . PHP_EOL;
            }
        }

        if ($toInsert !== "") {
            $newContent = substr($newContent, 0, $insertPos) . $toInsert . substr($newContent, $insertPos);
            $offsetDelta += strlen($toInsert);
        }
    }

    if ($newContent !== $content) {
        file_put_contents($filePath, $newContent);
        echo "Modified: $filePath\n";
        $filesChanged++;
    }
}

echo "Done. Files changed: $filesChanged\n";
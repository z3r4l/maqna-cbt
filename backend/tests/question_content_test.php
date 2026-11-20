<?php

require __DIR__ . '/../app/Services/QuestionContent.php';

use App\Services\QuestionContent;

$html = QuestionContent::sanitize('<table><tbody><tr><th>Kolom</th><td style="text-align:center">Nilai</td></tr></tbody></table><hr><script>alert(1)</script>');
if (! str_contains($html, '<table') || ! str_contains($html, '<th>Kolom</th>') || ! str_contains($html, '<hr') || str_contains($html, '<script')) {
    throw new RuntimeException('Question HTML sanitizer assertion failed.');
}

echo "Question content rules: PASS\n";

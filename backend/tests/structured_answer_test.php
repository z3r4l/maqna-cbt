<?php

require __DIR__ . '/../app/Services/StructuredAnswer.php';

use App\Services\StructuredAnswer;

$options = [['id' => 1, 'is_correct' => 1], ['id' => 2, 'is_correct' => 0], ['id' => 3, 'is_correct' => 1]];
$check = static function (bool $condition): void { if (! $condition) throw new RuntimeException('Structured scoring assertion failed.'); };
$check(StructuredAnswer::points('multi_mcq', [1, 3], $options, 3.0) === [3.0, true]);
$check(StructuredAnswer::points('multi_mcq', [1], $options, 3.0) === [0.0, false]);
$check(StructuredAnswer::points('true_false', ['1' => true, '2' => true, '3' => false], $options, 3.0) === [1.0, false]);
$check(StructuredAnswer::points('matching', ['1' => 1, '2' => 3, '3' => 2], $options, 3.0) === [1.0, false]);
$matchingOptions = [
    ['id' => 11, 'match_choice_id' => 101, 'is_correct' => 0],
    ['id' => 12, 'match_choice_id' => 102, 'is_correct' => 0],
];
$matchingChoices = [['id' => 101], ['id' => 102], ['id' => 103]];
$check(StructuredAnswer::normalize('matching', ['11' => 101, '12' => 102], $matchingOptions, $matchingChoices) === ['11' => 101, '12' => 102]);
$check(StructuredAnswer::points('matching', ['11' => 101, '12' => 103], $matchingOptions, 2.0, $matchingChoices) === [1.0, false]);
$check(StructuredAnswer::points('matching', ['11' => 101, '12' => 102], $matchingOptions, 2.0, $matchingChoices) === [2.0, true]);
$check(StructuredAnswer::normalize('multi_mcq', [3, 1], $options) === [1, 3]);
foreach ([['multi_mcq', [1, 1]], ['multi_mcq', [99]], ['true_false', ['1' => 1]], ['matching', ['1' => 2, '3' => 2]]] as [$type, $answer]) {
    try { StructuredAnswer::normalize($type, $answer, $options); throw new RuntimeException('Invalid answer accepted.'); }
    catch (InvalidArgumentException) {}
}
try { StructuredAnswer::normalize('matching', ['11' => 999], $matchingOptions, $matchingChoices); throw new RuntimeException('Invalid matching choice accepted.'); }
catch (InvalidArgumentException) {}
echo "Structured answer rules: PASS\n";

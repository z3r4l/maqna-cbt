<?php

require __DIR__ . '/../app/Services/DynamicExamToken.php';

use App\Services\DynamicExamToken;

$seed = 'fixture-secret';
$at = DynamicExamToken::at($seed, 599);
if (! DynamicExamToken::verify($seed, $at['accessCode'], 599)) throw new RuntimeException('Current token rejected.');
if (! DynamicExamToken::verify($seed, $at['accessCode'], 600)) throw new RuntimeException('Grace token rejected.');
if (DynamicExamToken::verify($seed, $at['accessCode'], 631)) throw new RuntimeException('Expired token accepted.');
if (DynamicExamToken::verify($seed, 'BADCODE0', 599)) throw new RuntimeException('Invalid token accepted.');
if (DynamicExamToken::at($seed, 599)['accessCode'] === DynamicExamToken::at($seed, 600)['accessCode']) throw new RuntimeException('Token did not rotate.');
echo "Dynamic token rotation and grace: PASS\n";

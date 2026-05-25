<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ExitPermit;
use App\Models\User;

$controller = $app->make(App\Http\Controllers\ExitPermitController::class);
$exit = ExitPermit::with(['user', 'requestors'])->find(41);
$user = User::where('nik', '965.04.26')->first();

$ref = new ReflectionMethod(App\Http\Controllers\ExitPermitController::class, 'canSubmitApproval');
$ref->setAccessible(true);

$result = $ref->invoke($controller, $exit, $user);

echo json_encode(['canSubmitApproval' => $result, 'exit_owner' => $exit->user->toArray(), 'requestors' => $exit->requestors->toArray()]);

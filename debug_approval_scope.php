<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('nik', '965.04.26')->first();
if (!$user) {
    echo "user not found\n";
    exit(1);
}
$controller = app()->make(App\Http\Controllers\ExitPermitController::class);
$scopeMethod = new ReflectionMethod($controller, 'managerApprovalScopeForUser');
$scopeMethod->setAccessible(true);
$canMethod = new ReflectionMethod($controller, 'canSubmitApproval');
$canMethod->setAccessible(true);
$permits = App\Models\ExitPermit::with(['user:id,name,nik','requestors:id,exit_permit_id,department'])
    ->whereNull('manager_approved_at')
    ->where('status', 'pending')
    ->get();

echo "user={$user->name} role=" . ($user->role?->code ?? 'null') . "\n";
foreach ($permits as $permit) {
    $can = $canMethod->invoke($controller, $permit, $user);
    $scope = $scopeMethod->invoke($controller, $user);
    echo 'permit=' . $permit->id . ' submitter=' . ($permit->user?->name ?? '-') . ' departments=' . $permit->requestors->pluck('department')->implode(' | ') . ' canSubmitApproval=' . ($can ? 'yes' : 'no') . ' scope=' . json_encode($scope) . "\n";
}

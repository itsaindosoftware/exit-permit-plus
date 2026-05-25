<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('name', 'Wida Mustika Sari')->orWhere('email','wida.mus@example.com')->first();
if (!$user) { echo "user not found\n"; exit(0);} 
$role = $user->role?->code ?? 'null';
echo "user id={$user->id} name={$user->name} nik={$user->nik} role={$role}\n";
$perms = App\Models\ExitPermit::query()
    ->where(function($q){
        $q->whereNotNull('manager_approved_at')->whereNotNull('md_approved_at')->whereNull('hr_verified_at')->where('status','pending');
    })
    ->with(['user:id,name,nik','requestors:id,exit_permit_id,name,department'])
    ->limit(5)
    ->get();
echo "perm count=".$perms->count()."\n";
foreach ($perms as $perm) {
    echo "permit {$perm->id} submitter=".($perm->user?->name ?? '-') ." stage=".($perm->approval_stage ?? 'n/a')." hr_approver_id=".($perm->hr_approver_id ?? 'null')." hr_approver_name=".($perm->hrApprover?->name ?? 'null')."\n";
}

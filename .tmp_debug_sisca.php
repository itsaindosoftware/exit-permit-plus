<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ExitPermit;

$sisca = User::query()->with('role')->whereRaw('LOWER(email) = ?', ['payroll.hr@thaisummit.co.id'])->first();

echo 'sisca_found=' . ($sisca ? '1' : '0') . PHP_EOL;
if ($sisca) {
    echo 'sisca_id=' . $sisca->id . PHP_EOL;
    echo 'sisca_email=' . $sisca->email . PHP_EOL;
    echo 'sisca_role=' . ($sisca->role->code ?? 'null') . PHP_EOL;
}

$permits = ExitPermit::query()->latest('id')->limit(15)->get();
foreach ($permits as $p) {
    $hasY = $p->requestors()->whereRaw("UPPER(COALESCE(reimburs_lunch_box, 'N')) = ?", ['Y'])->exists();
    $checkerEmail = optional($p->attendanceChecker)->email;
    echo sprintf(
        "permit#%d status=%s md=%s hrv=%s checked_at=%s checked_by=%s checker_email=%s valid=%s rto=%s post=%s hasY=%s\n",
        $p->id,
        (string) $p->status,
        $p->md_approved_at ? '1' : '0',
        $p->hr_verified_at ? '1' : '0',
        $p->attendance_checked_at ? '1' : '0',
        $p->attendance_checked_by ? (string) $p->attendance_checked_by : 'null',
        $checkerEmail ?: 'null',
        $p->has_valid_checkin ? '1' : '0',
        $p->returned_to_office ? '1' : '0',
        (string) ($p->post_md_path ?? 'null'),
        $hasY ? '1' : '0'
    );
}

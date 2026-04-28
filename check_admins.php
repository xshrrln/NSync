<?php
require 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Checking Platform Administrators ===\n\n";

$admins = User::role('Platform Administrator')->get();
echo "Platform admins found: " . $admins->count() . "\n";

if ($admins->count() > 0) {
    foreach ($admins as $admin) {
        echo "- ID: $admin->id, Name: $admin->name, Email: $admin->email\n";
    }
} else {
    echo "ERROR: No platform administrators found!\n";
}

echo "\n=== Checking All Users ===\n";
$allUsers = User::limit(5)->get();
echo "Sample users: " . $allUsers->count() . "\n";
foreach ($allUsers as $user) {
    $roles = $user->getRoleNames()->implode(', ') ?: '(no roles)';
    echo "- ID: $user->id, Name: $user->name, Roles: $roles\n";
}

echo "\n=== Checking Notifications Table ===\n";
$notifCount = DB::table('notifications')->count();
echo "Total notifications: $notifCount\n";

$supportNotifs = DB::table('notifications')
    ->where('type', 'App\\Notifications\\AdminSupportTicketNotification')
    ->limit(5)
    ->get();
echo "Support ticket notifications: " . $supportNotifs->count() . "\n";
foreach ($supportNotifs as $notif) {
    echo "- ID: $notif->id, User: $notif->notifiable_id, Read: " . ($notif->read_at ? 'Yes' : 'No') . "\n";
}

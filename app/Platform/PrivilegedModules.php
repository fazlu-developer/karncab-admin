<?php

namespace App\Platform;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Revenue, Commission, Reports, Notifications, Roles, Settings, and Website & branding.
 * Super Admin always has them. Admin and Manager see a module only after it is ticked
 * on their user. State Head and District Head never see them.
 */
final class PrivilegedModules
{
    /** @var array<string, string> */
    public const ABILITIES = [
        'revenue' => 'revenue.view',
        'commission' => 'commission.view',
        'reports' => 'reports.view',
        'notifications' => 'notifications.view',
        'roles' => 'roles.view',
        'settings' => 'settings.view',
        'branding' => 'branding.view',
    ];

    /**
     * Website enquiries, old users, and driver withdraw requests stay with Super Admin / Admin.
     * State Head and District Head settle fleet earnings from Commission settlement instead.
     *
     * @var list<string>
     */
    public const HEAD_HIDDEN = ['leads', 'old-users', 'payout'];

    /** @var array<string, string> */
    public const LABELS = [
        'revenue.view' => 'Revenue',
        'commission.view' => 'Commission',
        'reports.view' => 'Reports',
        'notifications.view' => 'Notifications',
        'roles.view' => 'Roles',
        'settings.view' => 'Settings',
        'branding.view' => 'Website & branding',
    ];

    public static function denied(object $user, string $key): bool
    {
        if (! $user instanceof User) {
            return false;
        }
        if ($key === 'payout' && in_array($user->role, [OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD], true)) {
            return ! $user->can('payout.view') && ! $user->can('payout.approve');
        }
        if (in_array($key, self::HEAD_HIDDEN, true) && in_array($user->role, [OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD], true)) {
            return true;
        }
        if (! isset(self::ABILITIES[$key])) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return false;
        }
        if (in_array($user->role, [OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD], true)) {
            return true;
        }
        if (in_array($user->role, [OperatorRole::ADMIN, OperatorRole::MANAGER], true)) {
            return ! in_array(self::ABILITIES[$key], $user->assignedAbilities(), true);
        }

        return false;
    }

    public static function keyForRequest(Request $request): ?string
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if ($name === 'state.section' || $name === 'state.notify') {
            $section = $name === 'state.notify' ? 'notifications' : (string) $request->route('section');

            return isset(self::ABILITIES[$section]) ? $section : null;
        }
        if (str_starts_with($name, 'leads.')) {
            return 'leads';
        }
        if (str_starts_with($name, 'old-users.')) {
            return 'old-users';
        }
        if (str_starts_with($name, 'withdrawals.')) {
            return 'payout';
        }
        if (str_starts_with($name, 'reports.')) {
            return 'reports';
        }
        if (str_starts_with($name, 'notifications.')) {
            return 'notifications';
        }
        if (str_starts_with($name, 'wallets.commission')) {
            return 'commission';
        }
        if (str_starts_with($name, 'ops.roles')) {
            return 'roles';
        }
        if (str_starts_with($name, 'ops.settings')) {
            return 'settings';
        }
        if (str_starts_with($name, 'ops.branding')) {
            return 'branding';
        }
        if ($name === 'ops.module') {
            $module = (string) $request->route('module');

            return isset(self::ABILITIES[$module]) ? $module : null;
        }

        return null;
    }
}

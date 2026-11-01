@extends('layouts.app')
@section('title', 'Roles & modules')
@section('heading', 'Roles & modules')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="key-round"></i> Roles, modules and permissions</h1>
            <p class="muted">Set what Admin, Manager, State Head, and District Head can open. Super Admin always has every permission. A new user inherits the role you pick. State Head and District Head only see bookings, drivers, and vehicles in their own state or district.</p>
        </div>
    </div>

    <section class="card" id="modules">
        <h2>Manage modules</h2>
        <form method="POST" action="{{ route('ops.roles.modules.store') }}" class="filters" style="align-items:end">
            @csrf
            <div><label>Key</label><input name="key" required placeholder="inventory" pattern="[a-z][a-z0-9_]+"></div>
            <div><label>Label</label><input name="label" required placeholder="Inventory"></div>
            <div><label>Nav group</label><input name="nav_group" value="Operations" placeholder="Operations"></div>
            <div><label>Icon</label><input name="icon" value="layout-grid" placeholder="layout-grid"></div>
            <label style="align-self:end"><input type="checkbox" name="actions[]" value="view" checked> view</label>
            <label style="align-self:end"><input type="checkbox" name="actions[]" value="create" checked> create</label>
            <label style="align-self:end"><input type="checkbox" name="actions[]" value="edit" checked> edit</label>
            <label style="align-self:end"><input type="checkbox" name="actions[]" value="delete" checked> delete</label>
            <button class="btn" type="submit">Add module</button>
        </form>
        <p class="muted" style="margin:10px 0 0">Key becomes permission names such as <code>inventory.view</code>. Custom modules appear in the sidebar for roles that have view.</p>
    </section>

    <section class="card">
        <h2>Modules</h2>
        <p class="muted">Admin, Manager, State Head, and District Head share this menu. What they can open is the permission set below. Create the person from All users and pick the role. Do not tick permissions on the user form.</p>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Module</th><th>Permissions</th><th>Nav</th><th></th></tr></thead>
                <tbody>
                @foreach ($modules as $module)
                    @continue(\App\Platform\OpsNav::retired((string) $module->key))
                    @php $abs = $abilitiesByModule[$module->id] ?? []; @endphp
                    <tr>
                        <td>
                            <strong>{{ $module->label }}</strong>
                            <div class="muted">{{ $module->key }} · {{ $module->nav_group }}{{ $module->is_system ? ' · built-in' : '' }}</div>
                        </td>
                        <td class="muted" style="font-size:12px">{{ implode(' · ', array_map(fn ($row) => $row->ability, $abs)) }}</td>
                        <td>
                            <form method="POST" action="{{ route('ops.roles.modules.update', $module->id) }}" class="filters" style="margin:0">
                                @csrf
                                @method('PUT')
                                <input name="label" value="{{ $module->label }}" required style="min-width:140px">
                                <input name="nav_group" value="{{ $module->nav_group }}" style="min-width:120px">
                                <input name="icon" value="{{ $module->icon }}" style="max-width:110px">
                                <label><input type="checkbox" name="show_in_nav" value="1" @checked($module->show_in_nav)> Nav</label>
                                <label><input type="checkbox" name="active" value="1" @checked($module->active)> Active</label>
                                <button class="btn ghost" type="submit">Save</button>
                            </form>
                        </td>
                        <td>
                            @unless ($module->is_system)
                                <form method="POST" action="{{ route('ops.roles.modules.destroy', $module->id) }}" onsubmit="return confirm('Delete this module and its role ticks?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn danger" type="submit">Delete</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" id="roles">
        <h2>Roles</h2>
        <div class="service-nav">
            @foreach ($roles as $role)
                @php
                    $roleLabel = match ($role) {
                        'SUPER_ADMIN' => 'Super Admin',
                        'ADMIN' => 'Admin',
                        'MANAGER' => 'Manager',
                        'STATE_HEAD' => 'State Head',
                        'DISTRICT_HEAD' => 'District Head',
                        default => str_replace('_', ' ', $role),
                    };
                @endphp
                <a href="{{ route('ops.roles', ['role' => $role]) }}" class="{{ $selectedRole === $role ? 'active' : '' }}">{{ $roleLabel }}</a>
            @endforeach
        </div>
        @if ($locked)
            <p class="muted" style="margin-top:12px">Super Admin is locked to every permission. Pick another role to edit the matrix.</p>
        @endif
        <p class="muted">Tick a module to show it in the menu and open its page. Clear it to hide the menu and block the URL. State Head data stays inside their state. District Head data stays inside their district.</p>
        <form method="POST" action="{{ route('ops.roles.matrix', $selectedRole) }}" style="margin-top:16px">
            @csrf
            @method('PUT')
            @foreach ($menuGroups as $group => $items)
                <h3 class="acl-group">{{ $group }}</h3>
                <div class="acl-grid">
                    @foreach ($items as $item)
                        <article class="acl-card">
                            <header>
                                <i data-lucide="{{ $item['icon'] }}"></i>
                                <strong>{{ $item['label'] }}</strong>
                            </header>
                            <label class="acl-open">
                                <input type="checkbox" name="abilities[]" value="{{ $item['ability'] }}"
                                    @checked(in_array($item['ability'], $granted, true))
                                    @disabled($locked)>
                                Open
                            </label>
                            @if ($item['related'] !== [])
                                <div class="acl-actions">
                                    @foreach ($item['related'] as $row)
                                        <label>
                                            <input type="checkbox" name="abilities[]" value="{{ $row->ability }}"
                                                @checked(in_array($row->ability, $granted, true))
                                                @disabled($locked)>
                                            {{ $row->action }}
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endforeach
            @unless ($locked)
                <button class="btn" type="submit" style="margin-top:16px">Save permissions</button>
            @endunless
        </form>
    </section>
@endsection

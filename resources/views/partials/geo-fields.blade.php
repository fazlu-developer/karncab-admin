@php
    $geo = $kcGeo ?? \App\Platform\GeoCatalog::payload(auth()->user());
    $stateName = $stateName ?? 'state_id';
    $districtName = $districtName ?? 'district_id';
    $stateValue = (string) ($stateValue ?? '');
    $districtValue = (string) ($districtValue ?? '');
    $required = $required ?? false;
    $emptyState = $emptyState ?? 'Select state';
    $emptyDistrict = $emptyDistrict ?? 'Select district';
    $stateLabel = $stateLabel ?? 'State';
    $districtLabel = $districtLabel ?? 'District';
@endphp
<div class="field">
    <label>{{ $stateLabel }}</label>
    <select name="{{ $stateName }}" {{ $required ? 'required' : '' }}>
        <option value="">{{ $emptyState }}</option>
        @foreach ($geo['states'] as $state)
            <option value="{{ $state['id'] }}" @selected((string) $state['id'] === $stateValue)>{{ $state['name'] }}</option>
        @endforeach
    </select>
</div>
<div class="field">
    <label>{{ $districtLabel }}</label>
    <select
        name="{{ $districtName }}"
        data-geo="district"
        data-geo-state="{{ $stateName }}"
        data-geo-selected="{{ $districtValue }}"
        data-geo-placeholder="{{ $emptyDistrict }}"
        data-geo-empty="{{ $required ? '0' : '1' }}"
        {{ $required ? 'required' : '' }}
    >
        <option value="">{{ $emptyDistrict }}</option>
    </select>
</div>

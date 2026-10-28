@php
    $pickerName = $name;
    $pickerOptions = $options ?? [];
    $pickerRequired = ! empty($required);
    $pickerValue = (string) old($pickerName, $value ?? '');
    $pickerEmpty = $empty ?? 'Search name, phone, or vehicle';
    $pickerId = 'np-'.preg_replace('/[^a-zA-Z0-9]/', '-', $pickerName).'-'.substr(md5($pickerName.uniqid('', true)), 0, 6);
@endphp
<input type="search" placeholder="{{ $pickerEmpty }}" oninput="filterNamePicker('{{ $pickerId }}', this.value)" autocomplete="off" style="margin-bottom:6px">
<select id="{{ $pickerId }}" name="{{ $pickerName }}" @if ($pickerRequired) required @endif>
    <option value="">{{ $pickerEmpty }}</option>
    @foreach ($pickerOptions as $opt)
        <option value="{{ $opt['id'] }}" data-search="{{ strtolower(($opt['label'] ?? '').' '.($opt['phone'] ?? '').' '.($opt['vehicle'] ?? '')) }}" @selected($pickerValue !== '' && (string) $opt['id'] === $pickerValue)>{{ $opt['label'] }}</option>
    @endforeach
</select>
<script>
    if (!window.filterNamePicker) {
        window.filterNamePicker = function (id, q) {
            q = (q || '').toLowerCase();
            const select = document.getElementById(id);
            if (!select) return;
            for (const opt of select.options) {
                if (!opt.value) continue;
                const hay = (opt.getAttribute('data-search') || opt.text || '').toLowerCase();
                opt.hidden = q !== '' && !hay.includes(q);
            }
        };
    }
</script>

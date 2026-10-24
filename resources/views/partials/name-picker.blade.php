@php
    $pickerName = $name;
    $pickerOptions = $options ?? [];
    $pickerRequired = ! empty($required);
    $pickerValue = (string) old($pickerName, $value ?? '');
    $pickerEmpty = $empty ?? 'Search name, phone, or ID';
@endphp
<select name="{{ $pickerName }}" @if ($pickerRequired) required @endif>
    <option value="">{{ $pickerEmpty }}</option>
    @foreach ($pickerOptions as $opt)
        <option value="{{ $opt['id'] }}" @selected($pickerValue !== '' && (string) $opt['id'] === $pickerValue)>{{ $opt['label'] }}</option>
    @endforeach
</select>

@php
    $selected = (string) old('user_id', $row['userId'] ?? '');
    $heads = array_merge($stateHeads ?? [], $districtHeads ?? []);
@endphp
<div class="filters">
    <div>
        <label>Head</label>
        <input type="search" placeholder="Search name or phone" oninput="var q=this.value.toLowerCase(); var sel=this.parentElement.querySelector('select'); Array.from(sel.options).forEach(function(opt,i){ if(i===0) return; opt.hidden = q!=='' && opt.text.toLowerCase().indexOf(q)===-1; });">
        <select name="user_id" required>
            <option value="">Choose a head</option>
            @foreach ($heads as $head)
                <option value="{{ $head['id'] }}" @selected($selected === (string) $head['id'])>{{ $head['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Support phone</label>
        <input name="support_phone" value="{{ old('support_phone', $row['supportPhone'] ?? '') }}" placeholder="98xxxxxxxx">
    </div>
    <div>
        <label>Email</label>
        <input name="email" type="email" value="{{ old('email', $row['email'] ?? '') }}">
    </div>
    <div>
        <label>Connect / WhatsApp</label>
        <input name="connect_phone" value="{{ old('connect_phone', $row['connectPhone'] ?? '') }}">
    </div>
    <div>
        <label>Address</label>
        <input name="address" value="{{ old('address', $row['address'] ?? '') }}">
    </div>
    <div>
        <label>Place name</label>
        <input name="place_name" value="{{ old('place_name', $row['placeName'] ?? '') }}" placeholder="Jaipur office">
    </div>
    <div>
        <label>Latitude</label>
        <input name="lat" value="{{ old('lat', $row['lat'] ?? '') }}" placeholder="26.9124">
    </div>
    <div>
        <label>Longitude</label>
        <input name="lng" value="{{ old('lng', $row['lng'] ?? '') }}" placeholder="75.7873">
    </div>
    <div>
        <label>Photo</label>
        @if (!empty($row['photoUrl']))
            <p><img src="{{ $row['photoUrl'] }}" alt="" style="max-height:64px"></p>
            <label><input type="checkbox" name="remove_photo" value="1"> Remove image</label>
        @endif
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif">
    </div>
</div>

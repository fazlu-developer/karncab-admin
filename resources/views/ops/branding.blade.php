@extends('layouts.app')
@section('title', 'Website & branding')
@section('heading', 'Website & branding')
@section('content')
    <div class="hero">
        <div>
            <h1><i data-lucide="image"></i> Website, SEO and logos</h1>
            <p class="muted">These fields update cms_site. The public website, favicon, admin logo and contact block read this form — not a JSON dump.</p>
        </div>
    </div>
    <section class="card">
        <form method="POST" action="{{ route('ops.branding.save') }}" enctype="multipart/form-data">
            @csrf
            <div class="grid-2">
                <div class="field"><label>Site name</label><input name="name" value="{{ old('name', $site['name']) }}" required></div>
                <div class="field"><label>Tagline</label><input name="tagline" value="{{ old('tagline', $site['tagline']) }}"></div>
                <div class="field"><label>SEO title</label><input name="defaultSeoTitle" value="{{ old('defaultSeoTitle', $site['defaultSeoTitle']) }}" maxlength="80"></div>
                <div class="field"><label>SEO description</label><input name="defaultSeoDescription" value="{{ old('defaultSeoDescription', $site['defaultSeoDescription']) }}" maxlength="180"></div>
                <div class="field"><label>Canonical host</label><input name="canonicalHost" value="{{ old('canonicalHost', $site['canonicalHost']) }}" placeholder="https://karnacab.in"></div>
                <div class="field"><label>Contact email</label><input name="contactEmail" type="email" value="{{ old('contactEmail', $site['contactEmail']) }}"></div>
                <div class="field"><label>Contact phone</label><input name="contactPhone" value="{{ old('contactPhone', $site['contactPhone']) }}"></div>
                <div class="field"><label>Address</label><input name="address" value="{{ old('address', $site['address']) }}"></div>
                <div class="field"><label>Facebook URL</label><input name="facebookUrl" value="{{ old('facebookUrl', $site['facebookUrl']) }}"></div>
                <div class="field"><label>Instagram URL</label><input name="instagramUrl" value="{{ old('instagramUrl', $site['instagramUrl']) }}"></div>
                <div class="field"><label>YouTube URL</label><input name="youtubeUrl" value="{{ old('youtubeUrl', $site['youtubeUrl']) }}"></div>
                <div class="field"><label>WhatsApp link</label><input name="whatsappUrl" value="{{ old('whatsappUrl', $site['whatsappUrl']) }}"></div>
                <div class="field"><label>Play Store link</label><input name="playStoreUrl" value="{{ old('playStoreUrl', $site['playStoreUrl']) }}"></div>
                <div class="field"><label>App Store link</label><input name="appStoreUrl" value="{{ old('appStoreUrl', $site['appStoreUrl']) }}"></div>
            </div>
            <div class="field"><label>Footer blurb</label><textarea name="footerBlurb" rows="2">{{ old('footerBlurb', $site['footerBlurb']) }}</textarea></div>
            <div class="field"><label>Map embed HTML (iframe)</label><textarea name="mapEmbed" rows="3">{{ old('mapEmbed', $site['mapEmbed']) }}</textarea></div>
            <div class="grid-2">
                <div class="field">
                    <label>Website logo</label>
                    @if ($site['logoUrl'])<p><img src="{{ $site['logoUrl'] }}" alt="" style="max-height:48px"></p>@endif
                    <input type="file" name="logo" accept="image/*">
                </div>
                <div class="field">
                    <label>Admin logo</label>
                    @if ($site['adminLogoUrl'])<p><img src="{{ $site['adminLogoUrl'] }}" alt="" style="max-height:48px"></p>@endif
                    <input type="file" name="admin_logo" accept="image/*">
                </div>
                <div class="field">
                    <label>Favicon</label>
                    @if ($site['faviconUrl'])<p><img src="{{ $site['faviconUrl'] }}" alt="" style="max-height:32px"></p>@endif
                    <input type="file" name="favicon" accept="image/*">
                </div>
                <div class="field">
                    <label>Customer app icon</label>
                    @if ($site['customerAppLogoUrl'])<p><img src="{{ $site['customerAppLogoUrl'] }}" alt="" style="max-height:48px"></p>@endif
                    <input type="file" name="customer_app_logo" accept="image/*">
                </div>
                <div class="field">
                    <label>Driver app icon</label>
                    @if ($site['driverAppLogoUrl'])<p><img src="{{ $site['driverAppLogoUrl'] }}" alt="" style="max-height:48px"></p>@endif
                    <input type="file" name="driver_app_logo" accept="image/*">
                </div>
                <div class="field">
                    <label>Open Graph image</label>
                    @if ($site['ogImage'])<p><img src="{{ $site['ogImage'] }}" alt="" style="max-height:48px"></p>@endif
                    <input type="file" name="og" accept="image/*">
                </div>
            </div>
            <h2 style="margin-top:18px">Application control</h2>
            <div class="grid-2">
                <div class="field"><label>Customer app version</label><input name="customerAppVersion" value="{{ old('customerAppVersion', $site['customerAppVersion']) }}" placeholder="1.0.4"></div>
                <div class="field"><label>Driver app version</label><input name="driverAppVersion" value="{{ old('driverAppVersion', $site['driverAppVersion']) }}" placeholder="1.0.4"></div>
                <div class="field"><label>Customer Play Store URL</label><input name="customerPlayStoreUrl" value="{{ old('customerPlayStoreUrl', $site['customerPlayStoreUrl']) }}"></div>
                <div class="field"><label>Driver Play Store URL</label><input name="driverPlayStoreUrl" value="{{ old('driverPlayStoreUrl', $site['driverPlayStoreUrl']) }}"></div>
                <div class="field"><label>High alert until</label><input name="highAlertUntil" value="{{ old('highAlertUntil', $site['highAlertUntil']) }}" placeholder="12:00"></div>
            </div>
            <div class="field"><label>High alert message</label><textarea name="highAlertMessage" rows="2" placeholder="Application 12 bje tak chalegi. Please complete your ride before this time.">{{ old('highAlertMessage', $site['highAlertMessage']) }}</textarea></div>
            <label class="field"><input type="checkbox" name="customerMaintenance" value="1" @checked(old('customerMaintenance', $site['customerMaintenance']))> Customer app under maintenance</label>
            <label class="field"><input type="checkbox" name="driverMaintenance" value="1" @checked(old('driverMaintenance', $site['driverMaintenance']))> Driver app under maintenance</label>
            <label class="field"><input type="checkbox" name="customerForceUpdate" value="1" @checked(old('customerForceUpdate', $site['customerForceUpdate']))> Customer app: ask to download the updated app when the version does not match</label>
            <label class="field"><input type="checkbox" name="driverForceUpdate" value="1" @checked(old('driverForceUpdate', $site['driverForceUpdate']))> Driver app: ask to download the updated app when the version does not match</label>
            <label class="field"><input type="checkbox" name="highAlertEnabled" value="1" @checked(old('highAlertEnabled', $site['highAlertEnabled']))> Show high alert in both apps</label>
            <button class="btn" type="submit">Save branding</button>
        </form>
    </section>
@endsection

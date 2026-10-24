<?php

namespace Tests\Unit;

use App\Support\PublicMedia;
use PHPUnit\Framework\TestCase;

class PublicMediaTest extends TestCase
{
    public function test_public_uploads_use_the_admin_host(): void
    {
        $this->assertSame(
            'https://admin.karnaride.in/uploads/ads/banner.jpg?v=12',
            PublicMedia::presentMediaUrl('https://karnaride.in/uploads/ads/banner.jpg', 12),
        );
        $this->assertSame(
            'https://admin.karnaride.in/uploads/services/svc.png',
            PublicMedia::presentMediaUrl('/uploads/services/svc.png'),
        );
        $this->assertSame(
            'https://admin.karnaride.in/uploads/branding/logo.webp',
            PublicMedia::presentMediaUrl('http://127.0.0.1:8001/uploads/branding/logo.webp'),
        );
        $this->assertSame(
            'https://admin.karnaride.in/uploads/ads/ad-4.png',
            PublicMedia::presentMediaUrl('uploads/ads/ad-4.png'),
        );
    }

    public function test_marketing_and_storage_paths_are_not_emitted(): void
    {
        $this->assertNull(PublicMedia::presentMediaUrl('https://karnaride.in/logo.png'));
        $this->assertNull(PublicMedia::presentMediaUrl('https://api.karnaride.in/storage/kyc/a.jpg'));
        $this->assertNull(PublicMedia::presentMediaUrl('/storage/app/public/a.jpg'));
        $this->assertNull(PublicMedia::presentMediaUrl(''));
        $this->assertSame(
            'https://images.unsplash.com/photo-1?w=10',
            PublicMedia::presentMediaUrl('https://images.unsplash.com/photo-1?w=10'),
        );
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppContentController extends Controller
{
    /**
     * @return list<array{slug: string, title: string, lede: string, body: string}>
     */
    public static function catalog(): array
    {
        return [
            ['slug' => 'about-us', 'title' => 'About Us', 'lede' => 'KarnaCab is a ride and delivery network for cities across India.', 'body' => "KarnaCab connects riders with verified drivers for city rides, outstation trips, rentals, parcels and more.\n\nWe operate with local partners so pickup, fare and support stay close to the city you book in."],
            ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'lede' => 'How KarnaCab collects, uses and protects your information.', 'body' => "We collect your name, phone number, trip locations and payment details to complete bookings and keep your account secure.\n\nWe do not sell personal data. You can request access or deletion through in-app Support."],
            ['slug' => 'terms-conditions', 'title' => 'Terms & Conditions', 'lede' => 'Rules for using the KarnaCab customer application.', 'body' => "By using KarnaCab you agree to book trips in good faith, pay the quoted fare, and follow driver and safety instructions.\n\nCancellations, waiting charges and tolls follow the fare shown before you confirm the ride."],
            ['slug' => 'return-refund', 'title' => 'Return & Refund', 'lede' => 'Wallet top-ups, cancelled trips and fare adjustments.', 'body' => "Unused wallet balance stays in your KarnaCab wallet.\n\nIf a trip is cancelled as per policy or a fare is charged in error, the amount is returned to the original payment method or wallet after review. Open Support with the booking ID to request a refund."],
            ['slug' => 'software-license', 'title' => 'Software License', 'lede' => 'Licence to use the KarnaCab mobile application.', 'body' => "KarnaCab grants you a personal, non-exclusive licence to use this app for booking transport and related services.\n\nYou may not copy, reverse engineer, or misuse the software. Brand names and content remain the property of KarnaCab."],
        ];
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('safety.edit'), 403);
        $this->ensureTable();
        $this->seed();
        $slugs = array_column(self::catalog(), 'slug');
        $rows = DB::connection('platform')->table('cms_pages')->whereIn('slug', $slugs)->orderBy('sort_order')->get();

        return view('ops.app-content', ['pages' => $rows]);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin') || $request->user()?->can('safety.edit'), 403);
        $this->ensureTable();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'lede' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
        $row = DB::connection('platform')->table('cms_pages')->where('slug', $slug)->first();
        abort_unless($row, 404);
        $image = $row->image_url ?? null;
        if ($request->boolean('remove_image')) {
            $image = null;
        }
        if ($request->file('image') instanceof UploadedFile) {
            $image = $this->storeImage($request->file('image'), $slug);
        }
        $payload = [
            'title' => $data['title'],
            'lede' => $data['lede'] ?? '',
            'nav_label' => $data['title'],
            'published' => 1,
            'updated_at' => now(),
        ];
        if (Schema::connection('platform')->hasColumn('cms_pages', 'image_url')) {
            $payload['image_url'] = $image;
        }
        if (Schema::connection('platform')->hasColumn('cms_pages', 'body')) {
            $payload['body'] = json_encode(['html' => $data['body'] ?? ''], JSON_UNESCAPED_UNICODE);
        }
        DB::connection('platform')->table('cms_pages')->where('slug', $slug)->update($payload);

        return back()->with('status', $data['title'].' updated. Customer app Account pages will show this content.');
    }

    private function storeImage(UploadedFile $file, string $slug): string
    {
        $dir = public_path('uploads/app-pages');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $name = $slug.'-'.Str::lower(Str::random(8)).'.'.$ext;
        $file->move($dir, $name);
        $apiDir = dirname(base_path()).DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'app-pages';
        if (is_dir(dirname($apiDir, 3))) {
            if (! is_dir($apiDir)) {
                mkdir($apiDir, 0775, true);
            }
            @copy($dir.DIRECTORY_SEPARATOR.$name, $apiDir.DIRECTORY_SEPARATOR.$name);
        }

        return '/uploads/app-pages/'.$name;
    }

    private function seed(): void
    {
        $sort = 80;
        foreach (self::catalog() as $item) {
            $exists = DB::connection('platform')->table('cms_pages')->where('slug', $item['slug'])->exists();
            if ($exists) {
                $sort++;
                continue;
            }
            $row = [
                'slug' => $item['slug'],
                'title' => $item['title'],
                'lede' => $item['lede'],
                'nav_group' => 'legal',
                'nav_label' => $item['title'],
                'sort_order' => $sort++,
                'published' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::connection('platform')->hasColumn('cms_pages', 'body')) {
                $row['body'] = json_encode(['html' => $item['body']], JSON_UNESCAPED_UNICODE);
            }
            if (Schema::connection('platform')->hasColumn('cms_pages', 'template')) {
                $row['template'] = 'app_legal';
            }
            DB::connection('platform')->table('cms_pages')->insert($row);
        }
    }

    private function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('cms_pages')) {
            $schema->create('cms_pages', function ($table) {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->string('eyebrow')->nullable();
                $table->string('seo_title')->nullable();
                $table->string('seo_description', 500)->nullable();
                $table->text('lede')->nullable();
                $table->json('body')->nullable();
                $table->string('image_url', 500)->nullable();
                $table->string('template')->nullable();
                $table->string('lead_type')->nullable();
                $table->string('register_kind')->nullable();
                $table->string('product_key')->nullable();
                $table->string('nav_group')->nullable();
                $table->string('nav_label')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('published')->default(true);
                $table->timestamps();
            });
        }
        if (! $schema->hasColumn('cms_pages', 'image_url')) {
            $schema->table('cms_pages', function ($table) {
                $table->string('image_url', 500)->nullable();
            });
        }
    }
}

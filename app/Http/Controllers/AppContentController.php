<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\StoredUpload;
use Illuminate\View\View;

class AppContentController extends Controller
{
    /**
     * @return list<array{slug: string, title: string, lede: string, body: string}>
     */
    public static function catalog(): array
    {
        return [
            ['slug' => 'about-us', 'title' => 'About Us', 'lede' => 'KARNACAB TRANSPORT SERVICE PRIVATE LIMITED, consumer brand KarnaRide.', 'body' => self::legalBody('about')],
            ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'lede' => 'How KarnaRide collects, uses, shares and protects your information, including location.', 'body' => self::legalBody('privacy-policy')],
            ['slug' => 'terms-conditions', 'title' => 'Terms & Conditions', 'lede' => 'Binding terms for the KarnaRide website and customer app.', 'body' => self::legalBody('terms')],
            ['slug' => 'return-refund', 'title' => 'Cancellation & Refund', 'lede' => 'Cancellation windows and how qualified refunds are paid.', 'body' => self::legalBody('return-refund')],
            ['slug' => 'software-license', 'title' => 'Software License', 'lede' => 'Licence to use the KarnaRide customer application.', 'body' => self::legalBody('software-license')],
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
            'body' => ['nullable', 'string', 'max:50000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable'],
        ]);
        $row = DB::connection('platform')->table('cms_pages')->where('slug', $slug)->first();
        abort_unless($row, 404);
        $image = $row->image_url ?? null;
        if ($request->boolean('remove_image') || $request->file('image') instanceof UploadedFile) {
            StoredUpload::forget($image);
            $image = null;
        }
        if ($request->file('image') instanceof UploadedFile) {
            $image = StoredUpload::replace($request->file('image'), 'app-pages', null, $slug);
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
        $websiteSlug = array_search($slug, \App\Http\Controllers\WebsitePagesController::aliases(), true);
        if (is_string($websiteSlug) && DB::connection('platform')->table('cms_pages')->where('slug', $websiteSlug)->exists()) {
            DB::connection('platform')->table('cms_pages')->where('slug', $websiteSlug)->update([
                'title' => $payload['title'],
                'lede' => $payload['lede'] ?? '',
                'body' => $payload['body'] ?? null,
                'published' => 1,
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', $data['title'].' updated. Customer app Account pages will show this content.');
    }

    private function seed(): void
    {
        $sort = 80;
        foreach (self::catalog() as $item) {
            $exists = DB::connection('platform')->table('cms_pages')->where('slug', $item['slug'])->first();
            if ($exists) {
                $touch = [];
                if (in_array($item['slug'], ['about-us', 'privacy-policy', 'terms-conditions'], true) && ($exists->nav_group ?? '') !== 'app') {
                    $touch['nav_group'] = 'app';
                }
                if (Schema::connection('platform')->hasColumn('cms_pages', 'body') && self::bodyIsEmpty($exists->body ?? null) && $item['body'] !== '') {
                    $touch['body'] = json_encode(['html' => $item['body']], JSON_UNESCAPED_UNICODE);
                    $touch['published'] = 1;
                }
                if ($touch !== []) {
                    $touch['updated_at'] = now();
                    DB::connection('platform')->table('cms_pages')->where('slug', $item['slug'])->update($touch);
                }
                $sort++;
                continue;
            }
            $row = [
                'slug' => $item['slug'],
                'title' => $item['title'],
                'lede' => $item['lede'],
                'nav_group' => in_array($item['slug'], ['about-us', 'privacy-policy', 'terms-conditions'], true) ? 'app' : 'legal',
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

    public static function bodyIsEmpty(mixed $body): bool
    {
        $decoded = is_string($body) ? json_decode($body, true) : $body;
        if (is_array($decoded)) {
            $html = trim((string) ($decoded['html'] ?? $decoded['text'] ?? ''));
            $sections = $decoded['sections'] ?? [];

            return $html === '' && empty($sections);
        }

        return trim((string) $body) === '';
    }

    public static function privacyPolicyBody(): string
    {
        return self::legalBody('privacy-policy');
    }

    public static function legalBody(string $stem): string
    {
        $path = dirname(__DIR__, 4).DIRECTORY_SEPARATOR.'legal'.DIRECTORY_SEPARATOR.$stem.'.php';
        $text = is_file($path) ? require $path : '';

        return is_string($text) ? $text : '';
    }
}

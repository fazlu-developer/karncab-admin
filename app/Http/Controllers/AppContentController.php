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
        $rows = DB::connection('platform')->table('cms_app_pages')->whereIn('slug', $slugs)->orderBy('sort_order')->get();

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
        $row = DB::connection('platform')->table('cms_app_pages')->where('slug', $slug)->first();
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
            'body' => json_encode(['html' => $data['body'] ?? ''], JSON_UNESCAPED_UNICODE),
            'image_url' => $image,
            'updated_at' => now(),
        ];
        DB::connection('platform')->table('cms_app_pages')->where('slug', $slug)->update($payload);

        return back()->with('status', $data['title'].' updated in cms_app_pages. Website CMS is not changed.');
    }

    private function seed(): void
    {
        $sort = 80;
        $db = DB::connection('platform');
        foreach (self::catalog() as $item) {
            $exists = $db->table('cms_app_pages')->where('slug', $item['slug'])->first();
            if ($exists) {
                if (self::bodyIsEmpty($exists->body ?? null) && $item['body'] !== '') {
                    $db->table('cms_app_pages')->where('slug', $item['slug'])->update([
                        'body' => json_encode(['html' => $item['body']], JSON_UNESCAPED_UNICODE),
                        'published' => 1,
                        'updated_at' => now(),
                    ]);
                }
                $sort++;
                continue;
            }
            $legacy = Schema::connection('platform')->hasTable('cms_pages')
                ? $db->table('cms_pages')->where('slug', $item['slug'])->first()
                : null;
            $body = $item['body'];
            $title = $item['title'];
            $lede = $item['lede'];
            $image = null;
            if ($legacy && ! self::bodyIsEmpty($legacy->body ?? null)) {
                $decoded = is_string($legacy->body) ? json_decode($legacy->body, true) : $legacy->body;
                $html = is_array($decoded) ? (string) ($decoded['html'] ?? $decoded['text'] ?? '') : (string) $legacy->body;
                if (trim($html) !== '') {
                    $body = $html;
                }
                $title = $legacy->title ?: $title;
                $lede = $legacy->lede ?: $lede;
                $image = $legacy->image_url ?? null;
            }
            $db->table('cms_app_pages')->insert([
                'slug' => $item['slug'],
                'title' => $title,
                'lede' => $lede,
                'nav_group' => 'app',
                'nav_label' => $title,
                'template' => 'app_legal',
                'body' => json_encode(['html' => $body], JSON_UNESCAPED_UNICODE),
                'image_url' => $image,
                'sort_order' => $sort++,
                'published' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('cms_app_pages')) {
            return;
        }
        $schema->create('cms_app_pages', function ($table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('lede')->nullable();
            $table->json('body')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('template')->nullable();
            $table->string('nav_group')->nullable();
            $table->string('nav_label')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });
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

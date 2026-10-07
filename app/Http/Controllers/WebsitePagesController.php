<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsitePagesController extends Controller
{
    /**
     * @return array<string, string>
     */
    public static function aliases(): array
    {
        return [
            'privacy' => 'privacy-policy',
            'terms' => 'terms-conditions',
            'about' => 'about-us',
        ];
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->ensureTable();
        $this->seed();
        $pages = DB::connection('platform')->table('cms_pages')->orderBy('sort_order')->orderBy('slug')->get();

        return view('ops.website-pages', ['pages' => $pages]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->ensureTable();
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'title' => ['required', 'string', 'max:160'],
            'lede' => ['nullable', 'string', 'max:500'],
            'template' => ['required', 'string', 'max:40'],
            'nav_group' => ['nullable', 'string', 'max:40'],
            'body' => ['nullable', 'string', 'max:50000'],
        ]);
        $slug = Str::slug($data['slug']);
        abort_if(DB::connection('platform')->table('cms_pages')->where('slug', $slug)->exists(), 422, 'That slug already exists.');
        $now = now();
        $max = (int) DB::connection('platform')->table('cms_pages')->max('sort_order');
        DB::connection('platform')->table('cms_pages')->insert($this->row([
            'slug' => $slug,
            'title' => $data['title'],
            'lede' => $data['lede'] ?? '',
            'eyebrow' => $data['title'],
            'nav_group' => $data['nav_group'] ?: 'company',
            'nav_label' => $data['title'],
            'template' => $data['template'],
            'body' => json_encode(['html' => $data['body'] ?? ''], JSON_UNESCAPED_UNICODE),
            'sort_order' => $max + 1,
            'published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        return back()->with('status', 'Page /'.$slug.' created. It is live on the public website.');
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user()?->can('platform.admin'), 403);
        $this->ensureTable();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'lede' => ['nullable', 'string', 'max:500'],
            'eyebrow' => ['nullable', 'string', 'max:160'],
            'template' => ['required', 'string', 'max:40'],
            'nav_group' => ['nullable', 'string', 'max:40'],
            'nav_label' => ['nullable', 'string', 'max:80'],
            'body' => ['nullable', 'string', 'max:50000'],
            'published' => ['nullable'],
        ]);
        $row = DB::connection('platform')->table('cms_pages')->where('slug', $slug)->first();
        abort_unless($row, 404);
        $payload = [
            'title' => $data['title'],
            'lede' => $data['lede'] ?? '',
            'eyebrow' => $data['eyebrow'] ?? ($row->eyebrow ?? ''),
            'template' => $data['template'],
            'nav_group' => $data['nav_group'] ?: ($row->nav_group ?? 'company'),
            'nav_label' => $data['nav_label'] ?: $data['title'],
            'published' => $request->boolean('published') ? 1 : 0,
            'updated_at' => now(),
        ];
        if (Schema::connection('platform')->hasColumn('cms_pages', 'body')) {
            $payload['body'] = json_encode(['html' => $data['body'] ?? ''], JSON_UNESCAPED_UNICODE);
        }
        DB::connection('platform')->table('cms_pages')->where('slug', $slug)->update($payload);
        $alias = self::aliases()[$slug] ?? null;
        if ($alias && DB::connection('platform')->table('cms_pages')->where('slug', $alias)->exists()) {
            DB::connection('platform')->table('cms_pages')->where('slug', $alias)->update([
                'title' => $payload['title'],
                'lede' => $payload['lede'],
                'body' => $payload['body'] ?? null,
                'published' => $payload['published'],
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', $data['title'].' saved. Website and apps read this from cms_pages.');
    }

    private function seed(): void
    {
        $now = now();
        $sort = 1;
        $legal = [
            'privacy' => [
                'title' => 'Privacy Policy',
                'lede' => 'How KarnaRide collects, uses, shares and protects your information, including location.',
                'html' => AppContentController::legalBody('privacy-policy'),
            ],
            'terms' => [
                'title' => 'Terms & Conditions',
                'lede' => 'Binding terms for the KarnaRide website and customer app.',
                'html' => AppContentController::legalBody('terms'),
            ],
            'about' => [
                'title' => 'About KarnaRide',
                'lede' => 'KARNACAB TRANSPORT SERVICE PRIVATE LIMITED, consumer brand KarnaRide.',
                'html' => AppContentController::legalBody('about'),
            ],
            'return-refund' => [
                'title' => 'Cancellation & Refund',
                'lede' => 'Cancellation windows and how qualified refunds are paid.',
                'html' => AppContentController::legalBody('return-refund'),
            ],
            'software-license' => [
                'title' => 'Software License',
                'lede' => 'Licence to use the KarnaRide customer application.',
                'html' => AppContentController::legalBody('software-license'),
            ],
            'contact' => [
                'title' => 'Contact Us',
                'lede' => 'Office, phone and email for KarnaRide support.',
                'html' => AppContentController::legalBody('contact'),
            ],
        ];
        foreach ($legal as $slug => $item) {
            $exists = DB::connection('platform')->table('cms_pages')->where('slug', $slug)->first();
            if (! $exists) {
                DB::connection('platform')->table('cms_pages')->insert($this->row([
                    'slug' => $slug,
                    'title' => $item['title'],
                    'lede' => $item['lede'],
                    'eyebrow' => 'Legal',
                    'nav_group' => 'legal',
                    'nav_label' => match ($slug) {
                        'privacy' => 'Privacy',
                        'terms' => 'Terms',
                        default => $item['title'],
                    },
                    'template' => $slug === 'contact' ? 'contact' : 'legal',
                    'body' => json_encode(['html' => $item['html']], JSON_UNESCAPED_UNICODE),
                    'sort_order' => 90 + $sort,
                    'published' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } elseif (Schema::connection('platform')->hasColumn('cms_pages', 'body') && AppContentController::bodyIsEmpty($exists->body ?? null) && is_string($item['html']) && $item['html'] !== '') {
                DB::connection('platform')->table('cms_pages')->where('slug', $slug)->update([
                    'body' => json_encode(['html' => $item['html']], JSON_UNESCAPED_UNICODE),
                    'published' => 1,
                    'updated_at' => $now,
                ]);
            }
            $sort++;
        }
        foreach (self::aliases() as $web => $app) {
            $source = DB::connection('platform')->table('cms_pages')->where('slug', $web)->first();
            if (! $source) {
                continue;
            }
            if (! DB::connection('platform')->table('cms_pages')->where('slug', $app)->exists()) {
                $copy = (array) $source;
                unset($copy['id']);
                $copy['slug'] = $app;
                $copy['template'] = 'app_legal';
                $copy['nav_group'] = 'app';
                $copy['updated_at'] = $now;
                $copy['created_at'] = $now;
                DB::connection('platform')->table('cms_pages')->insert($this->row($copy));
                continue;
            }
            $appRow = DB::connection('platform')->table('cms_pages')->where('slug', $app)->first();
            if (! $appRow) {
                continue;
            }
            $touch = [];
            if (($appRow->nav_group ?? '') !== 'app') {
                $touch['nav_group'] = 'app';
            }
            if (Schema::connection('platform')->hasColumn('cms_pages', 'body') && AppContentController::bodyIsEmpty($appRow->body ?? null)) {
                $touch['title'] = $source->title;
                $touch['lede'] = $source->lede ?? '';
                $touch['body'] = $source->body ?? null;
                $touch['published'] = 1;
            }
            if ($touch !== []) {
                $touch['updated_at'] = now();
                DB::connection('platform')->table('cms_pages')->where('slug', $app)->update($touch);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row): array
    {
        $schema = Schema::connection('platform');

        return array_filter(
            $row,
            fn ($key) => $schema->hasColumn('cms_pages', $key),
            ARRAY_FILTER_USE_KEY,
        );
    }

    private function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('cms_pages')) {
            return;
        }
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
}

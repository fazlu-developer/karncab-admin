<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class SupportFaqsController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('safety.view'), 403);
        $this->ensureTable();
        $faqs = Schema::connection('platform')->hasTable('support_faqs')
            ? DB::connection('platform')->table('support_faqs')->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        return view('support.faqs', ['faqs' => $faqs]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('safety.edit') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:4000'],
            'audience' => ['required', 'in:customer,driver,all'],
        ]);
        $this->ensureTable();
        $sort = (int) DB::connection('platform')->table('support_faqs')->max('sort_order') + 1;
        DB::connection('platform')->table('support_faqs')->insert([
            'question' => $data['question'],
            'answer' => $data['answer'],
            'audience' => $data['audience'],
            'sort_order' => $sort,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'FAQ published to the customer app.');
    }

    public function update(Request $request, int $faq): RedirectResponse
    {
        abort_unless($request->user()?->can('safety.edit') || $request->user()?->can('platform.admin'), 403);
        DB::connection('platform')->table('support_faqs')->where('id', $faq)->update([
            'active' => $request->boolean('active'),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'FAQ updated.');
    }

    private function ensureTable(): void
    {
        if (Schema::connection('platform')->hasTable('support_faqs')) {
            return;
        }
        Schema::connection('platform')->create('support_faqs', function ($table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('audience', 24)->default('customer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
}

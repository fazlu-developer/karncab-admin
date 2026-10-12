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
            'question_hi' => ['nullable', 'string', 'max:255'],
            'answer_hi' => ['nullable', 'string', 'max:4000'],
            'audience' => ['required', 'in:customer,driver,all'],
        ]);
        $this->ensureTable();
        $sort = (int) DB::connection('platform')->table('support_faqs')->max('sort_order') + 1;
        $row = [
            'question' => $data['question'],
            'answer' => $data['answer'],
            'audience' => $data['audience'],
            'sort_order' => $sort,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::connection('platform')->hasColumn('support_faqs', 'question_hi')) {
            $row['question_hi'] = $data['question_hi'] ?? null;
            $row['answer_hi'] = $data['answer_hi'] ?? null;
        }
        DB::connection('platform')->table('support_faqs')->insert($row);

        return back()->with('status', 'FAQ published. Hindi copy is used when the app language is Hindi.');
    }

    public function update(Request $request, int $faq): RedirectResponse
    {
        abort_unless($request->user()?->can('safety.edit') || $request->user()?->can('platform.admin'), 403);
        $data = $request->validate([
            'question' => ['nullable', 'string', 'max:255'],
            'answer' => ['nullable', 'string', 'max:4000'],
            'question_hi' => ['nullable', 'string', 'max:255'],
            'answer_hi' => ['nullable', 'string', 'max:4000'],
            'active' => ['nullable'],
        ]);
        $payload = ['updated_at' => now()];
        if ($request->has('active') && ! $request->has('question')) {
            $payload['active'] = $request->boolean('active') ? 1 : 0;
        } else {
            if (! empty($data['question'])) {
                $payload['question'] = $data['question'];
            }
            if (! empty($data['answer'])) {
                $payload['answer'] = $data['answer'];
            }
            if (Schema::connection('platform')->hasColumn('support_faqs', 'question_hi')) {
                $payload['question_hi'] = $data['question_hi'] ?? null;
                $payload['answer_hi'] = $data['answer_hi'] ?? null;
            }
            if ($request->has('active')) {
                $payload['active'] = $request->boolean('active') ? 1 : 0;
            }
        }
        DB::connection('platform')->table('support_faqs')->where('id', $faq)->update($payload);

        return back()->with('status', 'FAQ updated.');
    }

    private function ensureTable(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('support_faqs')) {
            $schema->create('support_faqs', function ($table) {
                $table->id();
                $table->string('question');
                $table->text('answer');
                $table->string('question_hi')->nullable();
                $table->text('answer_hi')->nullable();
                $table->string('audience', 24)->default('customer');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            return;
        }
        if (! $schema->hasColumn('support_faqs', 'question_hi')) {
            $schema->table('support_faqs', function ($table) {
                $table->string('question_hi')->nullable();
            });
        }
        if (! $schema->hasColumn('support_faqs', 'answer_hi')) {
            $schema->table('support_faqs', function ($table) {
                $table->text('answer_hi')->nullable();
            });
        }
    }
}

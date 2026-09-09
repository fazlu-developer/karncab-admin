<?php

namespace App\Http\Controllers;

use App\Platform\SupportPolicy;
use App\Services\SupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(private readonly SupportService $tickets) {}

    public function index(Request $request): View
    {
        return view('support.index', $this->tickets->workspace($request->user(), $request->query()));
    }

    public function show(Request $request, int $ticket): View
    {
        abort_unless($request->user()?->can('safety.view'), 403);

        return view('support.show', [
            'ticket' => $this->tickets->one($request->user(), $ticket),
            'catalog' => $this->tickets->catalog(),
        ]);
    }

    public function assign(Request $request, int $ticket): RedirectResponse
    {
        $this->tickets->assign($request->user(), $ticket);

        return back()->with('status', 'Ticket assigned.');
    }

    public function transition(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', SupportPolicy::STATUSES)],
            'resolution' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->tickets->transition($request->user(), $ticket, $data);

        return back()->with('status', 'Ticket moved to '.$data['status'].'.');
    }

    public function reply(Request $request, int $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:2000']]);
        $this->tickets->reply($request->user(), $ticket, $data['body'], true);

        return back()->with('status', 'Reply sent.');
    }

    public function attach(Request $request, int $ticket): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048']]);
        $this->tickets->attach($request->user(), $ticket, $request->file('file'));

        return back()->with('status', 'Attachment saved.');
    }

    public function file(Request $request, int $ticket, int $attachment): Response
    {
        $file = $this->tickets->attachmentFile($request->user(), $ticket, $attachment);

        return response($file['bytes'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
        ]);
    }
}

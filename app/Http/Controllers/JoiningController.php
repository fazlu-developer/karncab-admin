<?php

namespace App\Http\Controllers;

use App\Services\JoiningLetterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class JoiningController extends Controller
{
    public function __construct(private readonly JoiningLetterService $letters) {}

    public function index(Request $request): View
    {
        $this->guard($request);
        $edit = null;
        $editId = (int) $request->query('edit', 0);
        if ($editId > 0) {
            $edit = $this->letters->find($editId);
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'letter_type' => (string) $request->query('letter_type', ''),
            'designation_id' => (int) $request->query('designation_id', 0),
            'per_page' => (int) $request->query('per_page', 10),
        ];
        $letterhead = $this->letters->letterhead();

        return view('joining.index', [
            'letterhead' => $letterhead,
            'logoUrl' => $this->letters->logoPreviewUrl($letterhead),
            'designations' => $this->letters->designations(),
            'activeDesignations' => $this->letters->designations(true),
            'joinings' => $this->letters->joinings($filters),
            'filters' => $filters,
            'edit' => $edit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $this->validateLetter($request);
        $id = $this->letters->store($data);

        return redirect()->route('joining.preview', $id)->with('status', 'Letter issued. Download it as Word or PDF.');
    }

    public function update(Request $request, int $joining): RedirectResponse
    {
        $this->guard($request);
        $this->letters->update($joining, $this->validateLetter($request));

        return redirect()->route('joining.preview', $joining)->with('status', 'Letter updated. Download Word or PDF again so the seal matches the new text.');
    }

    public function destroy(Request $request, int $joining): RedirectResponse
    {
        $this->guard($request);
        $this->letters->destroy($joining);

        return back()->with('status', 'Joining letter removed.');
    }

    public function pdf(Request $request, int $joining): Response
    {
        $this->guard($request);
        $row = $this->letters->find($joining);
        $binary = $this->letters->pdf($joining);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$row->public_ref.'.pdf"',
        ]);
    }

    public function docx(Request $request, int $joining): Response
    {
        $this->guard($request);
        $row = $this->letters->find($joining);
        $binary = $this->letters->docx($joining);

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$row->public_ref.'.docx"',
        ]);
    }

    public function preview(Request $request, int $joining): View
    {
        $this->guard($request);

        return view('joining.preview', $this->letters->present($joining));
    }

    public function letterheadFile(Request $request, string $kind): Response
    {
        $this->guard($request);
        $file = $this->letters->letterheadFile($kind);
        $inline = str_starts_with($file['mime'], 'image/');

        return response($file['bytes'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$file['name'].'"',
        ]);
    }

    public function storeDesignation(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $this->letters->storeDesignation($data['name']);

        return back()->with('status', 'Designation added.');
    }

    public function updateDesignation(Request $request, int $designation): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ]);
        $this->letters->updateDesignation($designation, $data['name'], $request->boolean('active'));

        return back()->with('status', 'Designation updated.');
    }

    public function destroyDesignation(Request $request, int $designation): RedirectResponse
    {
        $this->guard($request);
        $this->letters->destroyDesignation($designation);

        return back()->with('status', 'Designation removed.');
    }

    public function saveLetterhead(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:180'],
            'brand_name' => ['required', 'string', 'max:80'],
            'cin' => ['nullable', 'string', 'max:32'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'office_address' => ['required', 'string', 'max:400'],
            'signatory_name' => ['required', 'string', 'max:120'],
            'signatory_title' => ['required', 'string', 'max:80'],
            'powered_by' => ['nullable', 'string', 'max:80'],
            'clear_header' => ['nullable', 'boolean'],
            'header_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'signature' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
            'coi' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
            'gst' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);
        $this->letters->saveLetterhead($data, $request->file('header_image'), $request->file('signature'), $request->file('coi'), $request->file('gst'));

        return back()->with('status', 'Company letterhead saved. New PDFs use this CIN, GSTIN, and signature.');
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()?->can('joining.view') || $request->user()?->can('platform.admin'), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateLetter(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:180'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:180'],
            'designation_id' => ['required', 'integer'],
            'posting' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:300'],
            'joining_on' => ['required', 'date'],
            'letter_type' => ['required', 'in:offer,appointment'],
            'offered_rupees' => ['nullable', 'numeric', 'min:0'],
            'reporting_to' => ['nullable', 'string', 'max:160'],
        ]);
    }
}

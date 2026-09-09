<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentsController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): View
    {
        return view('payments.index', [
            'payments' => $this->payments->list($request->user(), $request->query()),
            'invoices' => $this->payments->invoices($request->user()),
            'catalog' => $this->payments->catalog(),
            'query' => $request->query(),
        ]);
    }

    public function show(Request $request, int $payment): View
    {
        return view('payments.show', [
            'payment' => $this->payments->one($request->user(), $payment),
        ]);
    }

    public function invoice(Request $request, int $invoice): View
    {
        return view('payments.invoice', [
            'invoice' => $this->payments->invoice($request->user(), $invoice),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'method' => ['required', 'string'],
            'booking_id' => ['required', 'integer'],
            'amount_paise' => ['nullable', 'integer', 'min:1'],
            'intent' => ['nullable', 'in:capture,advance,partial'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $row = $this->payments->initiate($request->user(), $data);

        return redirect()->route('payments.show', $row['id'])->with('status', 'Payment '.$row['status'].'. Capture is server-side only.');
    }

    public function confirmCash(Request $request, int $payment): RedirectResponse
    {
        $this->payments->confirmCash($request->user(), $payment);

        return back()->with('status', 'Cash collected and marked success on the server.');
    }

    public function fail(Request $request, int $payment): RedirectResponse
    {
        $this->payments->markFailed($request->user(), $payment, $request->only('code', 'note'));

        return back()->with('status', 'Payment marked failed.');
    }

    public function refund(Request $request, int $payment): RedirectResponse
    {
        $data = $request->validate([
            'amount_paise' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $row = $this->payments->requestRefund($request->user(), $payment, $data);

        return redirect()->route('payments.show', $row['id'])->with('status', 'Refund requested.');
    }

    public function refundStep(Request $request, int $payment, string $step): RedirectResponse
    {
        abort_unless(in_array($step, ['approved', 'processing', 'completed'], true), 404);
        $this->payments->advanceRefund($request->user(), $payment, $step);

        return back()->with('status', 'Refund moved to '.$step.'.');
    }
}

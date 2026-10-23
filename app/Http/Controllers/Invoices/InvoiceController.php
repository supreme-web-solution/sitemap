<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceKind;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\SaveInvoiceRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Lead;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    /**
     * List the team's invoices.
     */
    public function index(Request $request, Team $currentTeam): Response
    {
        $status = InvoiceStatus::tryFrom((string) $request->query('status'));

        return Inertia::render('invoices/Index', [
            'status' => $status?->value,
            'statuses' => collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $case) => ['value' => $case->value, 'label' => $case->label()])->values(),
            'stats' => [
                'outstandingCents' => (int) $currentTeam->invoices()->outstanding()->sum('total_cents'),
                'overdueCount' => $currentTeam->invoices()->outstanding()->whereDate('due_on', '<', today())->count(),
                'paidThisMonthCents' => (int) $currentTeam->invoices()->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->sum('total_cents'),
            ],
            'hasPaymentAccount' => $currentTeam->paymentAccounts()->exists(),
            'invoices' => $currentTeam->invoices()
                ->with('lead:id,name')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (Invoice $invoice) => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'leadName' => $invoice->lead->name,
                    'status' => $invoice->status->value,
                    'statusLabel' => $invoice->status->label(),
                    'isOverdue' => $invoice->isOverdue(),
                    'total' => Invoice::formatCents($invoice->total_cents),
                    'dueOn' => $invoice->due_on?->toDateString(),
                    'createdAt' => $invoice->created_at->toISOString(),
                ]),
        ]);
    }

    /**
     * Show the form for a new invoice.
     */
    public function create(Request $request, Team $currentTeam): Response
    {
        return Inertia::render('invoices/Edit', [
            'invoice' => null,
            'leads' => $this->leadOptions($currentTeam),
            'defaultLeadId' => $currentTeam->leads()->whereKey($request->integer('lead'))->value('id'),
            'defaultDueOn' => today()->addDays(14)->toDateString(),
            'providers' => $this->connectedProviders($currentTeam),
            'kinds' => InvoiceKind::options(),
        ]);
    }

    /**
     * Create a draft invoice.
     */
    public function store(SaveInvoiceRequest $request, Team $currentTeam): RedirectResponse
    {
        $invoice = DB::transaction(function () use ($request, $currentTeam) {
            $invoice = $currentTeam->invoices()->create([
                'lead_id' => $request->validated('lead_id'),
                'number' => Invoice::nextNumber($currentTeam),
                'status' => InvoiceStatus::Draft,
                'kind' => $request->validated('kind') ?? InvoiceKind::Standard,
                'total_cents' => $request->totalCents(),
                'due_on' => $request->validated('due_on'),
                'notes' => $request->validated('notes'),
            ]);

            $invoice->items()->createMany($request->lineItems());

            return $invoice;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice :number saved as a draft.', ['number' => $invoice->number])]);

        return to_route('invoices.edit', ['current_team' => $currentTeam->slug, 'invoice' => $invoice->id]);
    }

    /**
     * Show an invoice: editable while it is a draft, read-only after.
     */
    public function edit(Team $currentTeam, Invoice $invoice): Response
    {
        $invoice->load('lead:id,name,email', 'items');

        return Inertia::render('invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'statusLabel' => $invoice->status->label(),
                'isOverdue' => $invoice->isOverdue(),
                'kind' => $invoice->kind->value,
                'kindLabel' => $invoice->kind->label(),
                'leadId' => $invoice->lead_id,
                'leadName' => $invoice->lead->name,
                'leadEmail' => $invoice->lead->email,
                'dueOn' => $invoice->due_on?->toDateString(),
                'notes' => $invoice->notes,
                'items' => $invoice->items->map(fn (InvoiceItem $item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_cents / 100,
                ])->values(),
                'total' => Invoice::formatCents($invoice->total_cents),
                'payUrl' => $invoice->status === InvoiceStatus::Draft ? null : $invoice->payUrl(),
                'sentAt' => $invoice->sent_at?->toISOString(),
                'paidAt' => $invoice->paid_at?->toISOString(),
                'paidVia' => $invoice->paid_via,
            ],
            'leads' => $this->leadOptions($currentTeam),
            'defaultLeadId' => null,
            'defaultDueOn' => null,
            'providers' => $this->connectedProviders($currentTeam),
            'kinds' => InvoiceKind::options(),
        ]);
    }

    /**
     * Save changes to a draft invoice.
     */
    public function update(SaveInvoiceRequest $request, Team $currentTeam, Invoice $invoice): RedirectResponse
    {
        $this->ensureDraft($invoice, __('Only drafts can be edited. Void this invoice and create a new one instead.'));

        DB::transaction(function () use ($request, $invoice) {
            $invoice->items()->delete();
            $invoice->items()->createMany($request->lineItems());
            $invoice->update([
                'lead_id' => $request->validated('lead_id'),
                'kind' => $request->validated('kind') ?? $invoice->kind,
                'total_cents' => $request->totalCents(),
                'due_on' => $request->validated('due_on'),
                'notes' => $request->validated('notes'),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice saved.')]);

        return back();
    }

    /**
     * Delete a draft invoice.
     */
    public function destroy(Team $currentTeam, Invoice $invoice): RedirectResponse
    {
        $this->ensureDraft($invoice, __('Only drafts can be deleted. Void an invoice that was already sent.'));

        $invoice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return to_route('invoices.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * @return list<array{id: int, name: string, email: string|null}>
     */
    protected function leadOptions(Team $team): array
    {
        return $team->leads()->orderBy('name')->limit(1000)->get(['id', 'name', 'email'])
            ->map(fn (Lead $lead) => ['id' => $lead->id, 'name' => $lead->name, 'email' => $lead->email])
            ->all();
    }

    /**
     * @return list<string>
     */
    protected function connectedProviders(Team $team): array
    {
        return $team->paymentAccounts()->pluck('provider')->map(fn ($provider) => $provider->label())->values()->all();
    }

    protected function ensureDraft(Invoice $invoice, string $message): void
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => $message]);
        }
    }
}

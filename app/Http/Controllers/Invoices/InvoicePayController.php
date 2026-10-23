<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentProvider;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentAccount;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class InvoicePayController extends Controller
{
    /**
     * Show the invoice to the customer with the ways they can pay it.
     */
    public function show(Request $request, Invoice $invoice): Response
    {
        abort_if($invoice->status === InvoiceStatus::Draft, 404);

        $invoice->load('items', 'team', 'lead:id,name');

        return response()
            ->view('invoices.public', [
                'invoice' => $invoice,
                'providers' => $invoice->isPayable()
                    ? $invoice->team->paymentAccounts()->get()->map(fn (PaymentAccount $account) => $account->provider)->all()
                    : [],
                'notice' => $request->query('status'),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * Send the customer to the provider's payment page.
     */
    public function pay(Invoice $invoice, PaymentProvider $provider): RedirectResponse
    {
        abort_unless($invoice->isPayable(), 404);

        $account = $invoice->team->paymentAccounts()->where('provider', $provider)->first();

        abort_if($account === null, 404);

        $invoice->load('items', 'lead', 'team');

        try {
            $url = $provider->gateway()->startPayment(
                $invoice,
                $account,
                route('invoices.public.return', ['invoice' => $invoice->public_token, 'provider' => $provider->value]),
                route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'cancelled']),
            );
        } catch (PaymentException $exception) {
            Log::warning('Could not start an invoice payment.', ['invoice_id' => $invoice->id, 'provider' => $provider->value, 'message' => $exception->getMessage()]);

            return to_route('invoices.public', ['invoice' => $invoice->public_token, 'status' => 'error']);
        }

        return redirect()->away($url);
    }

    /**
     * Confirm the payment when the customer comes back from the provider.
     */
    public function complete(Request $request, Invoice $invoice, PaymentProvider $provider): RedirectResponse
    {
        $account = $invoice->team->paymentAccounts()->where('provider', $provider)->first();

        abort_if($account === null || $invoice->status === InvoiceStatus::Draft, 404);

        $status = 'pending';

        if ($invoice->status === InvoiceStatus::Paid) {
            $status = 'paid';
        } elseif ($invoice->status === InvoiceStatus::Open) {
            try {
                $invoice->load('items', 'lead', 'team');
                $status = $provider->gateway()->completePayment($invoice, $account, $request) ? 'paid' : 'pending';
            } catch (PaymentException $exception) {
                Log::warning('Could not confirm an invoice payment.', ['invoice_id' => $invoice->id, 'provider' => $provider->value, 'message' => $exception->getMessage()]);
                $status = 'pending';
            }
        }

        return to_route('invoices.public', ['invoice' => $invoice->public_token, 'status' => $status]);
    }
}

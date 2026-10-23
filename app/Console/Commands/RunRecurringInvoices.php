<?php

namespace App\Console\Commands;

use App\Actions\Invoices\GenerateRecurringInvoice;
use App\Models\RecurringInvoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('invoices:run-recurring')]
#[Description('Issue the invoices that recurring billing schedules have come due for')]
class RunRecurringInvoices extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GenerateRecurringInvoice $generate): int
    {
        $issued = 0;

        RecurringInvoice::query()->due()->with(['team', 'lead'])->chunkById(100, function ($schedules) use ($generate, &$issued) {
            foreach ($schedules as $schedule) {
                try {
                    $issued += $generate->handle($schedule) ? 1 : 0;
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        });

        $this->info("Issued {$issued} recurring invoice(s).");

        return self::SUCCESS;
    }
}

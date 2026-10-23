<?php

namespace App\Console\Commands;

use App\Jobs\SendSequenceStep;
use App\Models\SequenceEnrollment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Command;

#[Description('Queue due follow-up sequence emails')]
class RunFollowUpSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequences:run';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        SequenceEnrollment::query()
            ->due()
            ->orderBy('id')
            ->eachById(fn (SequenceEnrollment $enrollment) => SendSequenceStep::dispatch($enrollment), 100);

        return self::SUCCESS;
    }
}

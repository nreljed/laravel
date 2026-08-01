<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendEmail;
use App\Mail\DynamicMailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendEmailTest extends TestCase
{
    public function test_job_sends_dynamic_mailable_with_recipient_and_subject(): void
    {
        Mail::fake();

        $job = new SendEmail('recipient@example.com', 'Hello Subject', 'welcome', ['name' => 'Jane']);
        $job->handle();

        Mail::assertSent(DynamicMailable::class, function (DynamicMailable $mail) {
            return $mail->hasTo('recipient@example.com')
                && $mail->envelope()->subject === 'Hello Subject';
        });
    }

    public function test_job_exposes_retry_configuration(): void
    {
        $job = new SendEmail('recipient@example.com', 'Subject', 'template');

        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
    }

    public function test_failed_job_logs_error(): void
    {
        Log::spy();

        $job = new SendEmail('recipient@example.com', 'Subject', 'template');
        $job->failed(new \RuntimeException('smtp down'));

        Log::shouldHaveReceived('error')->once();
    }
}

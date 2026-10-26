<?php

namespace Nreljed\MarketingModule\Services;

use Illuminate\Support\Facades\Mail;
use Nreljed\MarketingModule\Contracts\EmailChannel;

class LaravelMailChannel implements EmailChannel
{
    public function send(string $to, string $subject, string $html, array $headers = []): void
    {
        Mail::html($html, function ($message) use ($to, $subject, $headers): void {
            $message->to($to)->subject($subject);

            foreach ($headers as $name => $value) {
                $message->getHeaders()->addTextHeader($name, $value);
            }
        });
    }
}

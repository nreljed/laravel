<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\Contact;

class UnsubscribeController
{
    public function __invoke(Request $request, Contact $contact)
    {
        $contact->unsubscribe();

        return response('You have been unsubscribed from marketing emails.', 200);
    }
}

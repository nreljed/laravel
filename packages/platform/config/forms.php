<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Form Submission Catalog
    |--------------------------------------------------------------------------
    |
    | Every public form in the system is declared here. A declaration is the
    | entire contract: validation rules, honeypot, rate limit, admin inbox
    | visibility, CSV export column order, notification fan-out and retention.
    | Adding a form is a config entry plus a front-end form that POSTs to
    | POST /forms/{key} — no controller, no migration, no admin page.
    |
    | Consuming apps publish this file (php artisan vendor:publish --tag
    | core-config) and append their own forms here. A feature provider may also
    | call Forms::register() from boot(); config entries always win on key
    | collisions, so a module can never shadow a core form.
    |
    | Keys
    |   label              Display name in the admin inbox.
    |   description        One-line help text.
    |   permission         Permission slug required to view this form's
    |                      submissions (and its export / bulk actions).
    |   redirect           Route name the visitor is sent back to after
    |                      submitting. Falls back to the form key itself.
    |   fields             Ordered field definitions. Each entry: name, label,
    |                      type (text|email|url|textarea), required, nullable,
    |                      min, max. This list IS the validation contract and
    |                      the stable CSV column order. The first `textarea`
    |                      field is also the inbox preview line (the message
    |                      body shown under the summary), so declare a form's
    |                      long-form content as `textarea`.
    |   identity           payload key => summary column. Maps the three
    |                      denormalised inbox columns (name, email, summary)
    |                      onto declared fields so list, search and DSAR
    |                      lookups stay indexed.
    |   honeypot           Name of the invisible trap field. Discarded
    |                      submissions are never stored and never distinguishable
    |                      from success.
    |   rate_limit         Laravel throttle string applied to the POST route.
    |   dedupe_window      Seconds during which an identical email + payload is
    |                      refused. 0 disables.
    |   notify             Notification type key fanned out to admins. Null for
    |                      forms that stay quiet.
    |   consent            Optional GDPR consent declaration:
    |                      ['label' => ..., 'required' => bool, 'privacy_url' => ?string].
    |                      When present the service requires the checkbox and
    |                      stamps consent_at with the statement shown.
    |   searchable         Extra payload keys searched by the inbox. Identity
    |                      keys are always searched.
    |   retention_days     How long ARCHIVED submissions of this form are kept
    |                      before app:prune-form-submissions removes them.
    |                      Unarchived submissions are never auto-deleted.
    |   anonymise_after_days  When set, the prune command nulls ip/user_agent
    |                      on submissions older than this while keeping the
    |                      row (privacy-friendly, still DSAR-usable).
    |
    */

    'forms' => [

        'contact' => [
            'label' => 'Contact Form',
            'description' => 'Messages sent from the public contact page.',
            'permission' => 'manage-contact',
            'redirect' => 'contact',
            'identity' => [
                'name' => 'name',
                'email' => 'email',
                'summary' => 'subject',
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 190],
                ['name' => 'subject', 'label' => 'Subject', 'type' => 'text', 'nullable' => true, 'max' => 190],
                ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true, 'min' => 10, 'max' => 5000],
            ],
            'honeypot' => 'website',
            'rate_limit' => '3,1',
            'dedupe_window' => 600,
            'notify' => 'contact.received',
            'consent' => null,
            'searchable' => ['message'],
            'retention_days' => 365,
            'anonymise_after_days' => null,
        ],

    ],

];

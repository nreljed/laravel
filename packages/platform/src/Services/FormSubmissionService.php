<?php

namespace Nreljed\Platform\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nreljed\Platform\Jobs\DispatchFormSubmissionNotification;
use Nreljed\Platform\Models\FormSubmission;
use Nreljed\Platform\Support\FormRegistry;

/**
 * The single collect path for every public form.
 *
 * A form's controller is one line: `Forms::submit('contact', $request)`. This
 * service owns everything that must be identical across forms — honeypot,
 * validation built from the registry, duplicate suppression, attribution
 * capture (referrer + UTM), consent stamping, persistence and the admin
 * notification fan-out — and guarantees the visitor always receives the same
 * "thank you" redirect whether their submission was stored, rejected as a
 * duplicate, or swallowed by the honeypot.
 */
class FormSubmissionService
{
    public function __construct(protected FormRegistry $registry) {}

    /**
     * Collect a submission and redirect the visitor back to the form.
     *
     * @throws ValidationException when the payload fails the form's rules
     */
    public function submit(string $formKey, Request $request): RedirectResponse
    {
        $this->collect($formKey, $request);

        return $this->thanks($formKey);
    }

    /**
     * Persist a submission, or return null when it was silently discarded
     * (honeypot hit or duplicate within the dedupe window).
     *
     * @throws ValidationException when the payload fails the form's rules
     */
    public function collect(string $formKey, Request $request): ?FormSubmission
    {
        $definition = $this->registry->resolve($formKey);

        if ($this->isHoneypotHit($definition, $request)) {
            return null;
        }

        $payload = $this->validatedPayload($definition, $request);

        if ($this->isDuplicate($formKey, $definition, $request, $payload)) {
            return null;
        }

        $submission = FormSubmission::create(array_merge([
            'form_key' => $formKey,
            'payload' => $payload,
            'status' => FormSubmission::STATUS_NEW,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'referrer' => Str::limit((string) $request->headers->get('referer'), 512, ''),
            'utm_source' => $this->utm($request, 'utm_source'),
            'utm_medium' => $this->utm($request, 'utm_medium'),
            'utm_campaign' => $this->utm($request, 'utm_campaign'),
            'utm_term' => $this->utm($request, 'utm_term'),
            'utm_content' => $this->utm($request, 'utm_content'),
            'consent_at' => $this->consentAt($definition, $request),
        ], $this->identityColumns($definition, $payload)));

        $this->notify($formKey, $submission);

        return $submission;
    }

    /**
     * The post-submit redirect. Identical for stored, discarded and duplicate
     * submissions so a bot cannot learn what the honeypot caught.
     */
    public function thanks(string $formKey): RedirectResponse
    {
        $definition = $this->registry->get($formKey);
        $redirect = is_array($definition) ? ($definition['redirect'] ?? null) : null;
        $route = is_string($redirect) && $redirect !== '' ? $redirect : $formKey;

        try {
            return redirect()->route($route)->with('status', $formKey.'-sent');
        } catch (\Throwable) {
            return redirect()->back()->with('status', $formKey.'-sent');
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function isHoneypotHit(array $definition, Request $request): bool
    {
        $honeypot = $definition['honeypot'] ?? null;

        return is_string($honeypot) && $honeypot !== '' && $request->filled($honeypot);
    }

    /**
     * Validate against the registry's declared fields and return the trimmed
     * payload keyed by field name — never the raw request, so an undeclared
     * field can never reach storage.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validatedPayload(array $definition, Request $request): array
    {
        $rules = $this->registry->rules($definition);

        $validator = Validator::make(
            $request->all(),
            $rules,
            $this->registry->attributes($definition),
        );

        $attributes = $request->only(array_keys($rules));

        $validator->after(function ($validator) use ($definition, $request) {
            $consent = $definition['consent'] ?? null;

            if (! is_array($consent) || empty($consent['required'])) {
                return;
            }

            if (! $request->boolean('consent')) {
                $validator->errors()->add(
                    'consent',
                    (string) ($consent['label'] ?? 'Please accept the privacy notice.'),
                );
            }
        });

        $validated = $validator->validate();

        $payload = [];

        foreach ($this->registry->fields($definition) as $field) {
            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $value = $validated[$name] ?? ($attributes[$name] ?? null);
            $payload[$name] = is_string($value) ? trim($value) : $value;
        }

        return $payload;
    }

    /**
     * Refuse an identical email + payload replayed inside the dedupe window.
     * This is the double-click guard: the visitor sees the normal thank-you.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     */
    protected function isDuplicate(string $formKey, array $definition, Request $request, array $payload): bool
    {
        $window = (int) ($definition['dedupe_window'] ?? 0);

        if ($window < 1) {
            return false;
        }

        $email = strtolower(trim((string) $request->input('email')));
        $fingerprint = sha1($email.'|'.json_encode($payload));

        return ! Cache::add(
            'form-submissions:dedupe:'.$formKey.':'.$fingerprint,
            1,
            $window,
        );
    }

    /**
     * Map the declared identity fields onto the denormalised inbox columns.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     * @return array<string, string|null>
     */
    protected function identityColumns(array $definition, array $payload): array
    {
        $columns = [];

        foreach ($this->registry->identity($definition) as $column => $source) {
            $value = $payload[$source] ?? null;
            $columns[$column] = is_string($value) && $value !== '' ? Str::limit($value, 190, '') : null;
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function consentAt(array $definition, Request $request): ?Carbon
    {
        $consent = $definition['consent'] ?? null;

        if (! is_array($consent) || ! $request->boolean('consent')) {
            return null;
        }

        return now();
    }

    protected function utm(Request $request, string $key): ?string
    {
        $value = $request->query($key, $request->input($key));

        return is_string($value) && trim($value) !== ''
            ? Str::limit(trim($value), 128, '')
            : null;
    }

    protected function notify(string $formKey, FormSubmission $submission): void
    {
        try {
            DispatchFormSubmissionNotification::dispatch($submission->id, $formKey);
        } catch (\Throwable $e) {
            Log::error(
                '[FormSubmissions] Failed to queue notification for '.$formKey
                .' submission #'.$submission->id.' | Error: '.$e->getMessage()
            );
        }
    }
}

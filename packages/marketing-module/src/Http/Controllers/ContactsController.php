<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Requests\StoreContactRequest;
use Nreljed\MarketingModule\Resources\ContactResource;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

class ContactsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Contact::class);

        $contacts = Contact::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (Contact $contact): array => $this->row($contact))
            ->all();

        return $renderer->renderIndex(ContactResource::KEY, $contacts);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', Contact::class);

        return $renderer->renderForm(
            ContactResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.contacts.store'),
        );
    }

    public function store(StoreContactRequest $request)
    {
        $this->authorize('create', Contact::class);

        Contact::create($request->validated());

        return redirect()->back()->with('status', 'Contact created.');
    }

    public function edit(ResourceRenderer $renderer, Contact $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            ContactResource::KEY,
            $this->row($record),
            'PUT',
            route('dashboard.marketing.contacts.update', $record),
        );
    }

    public function update(StoreContactRequest $request, Contact $record)
    {
        $this->authorize('update', $record);

        $record->update($request->validated());

        return redirect()->back()->with('status', 'Contact updated.');
    }

    public function destroy(Contact $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Contact deleted.');
    }

    /**
     * The row shape the generic index and form pages consume.
     *
     * @return array<string, mixed>
     */
    protected function row(Contact $contact): array
    {
        return [
            'id' => $contact->id,
            'email' => $contact->email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'phone' => $contact->phone,
            'status' => $contact->status?->value,
            'source' => $contact->source,
            'created_at' => $contact->created_at?->toDateTimeString(),
        ];
    }
}

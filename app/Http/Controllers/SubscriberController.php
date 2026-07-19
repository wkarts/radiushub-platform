<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubscriberRequest;
use App\Models\Subscriber;
use App\Services\Audit\AuditLogger;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $subscribers = Subscriber::query()
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = (string) $request->string('q');
                $query->where(function ($nested) use ($term): void {
                    Search::contains($nested, 'name', $term)->orWhereRaw(
                        'LOWER('.$nested->getModel()->qualifyColumn('document').') LIKE ?',
                        ['%'.mb_strtolower(trim($term), 'UTF-8').'%'],
                    );
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('subscribers.index', compact('subscribers'));
    }

    public function store(SubscriberRequest $request, AuditLogger $audit): RedirectResponse
    {
        $subscriber = Subscriber::query()->create($request->validated());
        $audit->record('subscriber.created', $subscriber, [], $subscriber->toArray());
        return back()->with('success', 'Cliente cadastrado.');
    }

    public function update(SubscriberRequest $request, Subscriber $subscriber, AuditLogger $audit): RedirectResponse
    {
        $old = $subscriber->toArray();
        $subscriber->update($request->validated());
        $audit->record('subscriber.updated', $subscriber, $old, $subscriber->fresh()->toArray());
        return back()->with('success', 'Cliente atualizado.');
    }

    public function destroy(Subscriber $subscriber, AuditLogger $audit): RedirectResponse
    {
        abort_if($subscriber->contracts()->whereNotIn('status', ['cancelled'])->exists(), 422, 'Cliente possui contrato não cancelado.');
        $audit->record('subscriber.deleted', $subscriber, $subscriber->toArray(), []);
        $subscriber->delete();
        return back()->with('success', 'Cliente removido.');
    }
}
